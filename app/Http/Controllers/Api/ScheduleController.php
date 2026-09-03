<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\Team;
use App\Support\ShiftRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduleController extends Controller
{
    /**
     * POST /api/schedule/generate {team_id, month:"YYYY-MM", kinds?:["weekday","weekend"]}
     * Crea turnos para cada día del mes a partir de la plantilla semanal de cada
     * empleado del equipo (Lun–Vie usa 'weekday', Sáb–Dom usa 'weekend').
     * No toca turnos existentes; omite los que quedarían idénticos a uno ya guardado.
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'kinds' => ['nullable', 'array'],
            'kinds.*' => ['in:weekday,weekend'],
        ]);

        $kinds = $data['kinds'] ?? ['weekday', 'weekend'];
        $team = Team::findOrFail($data['team_id']);

        $employees = Employee::where('team_id', $team->id)
            ->with('shiftTemplates')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($this->monthDays($data['month']) as $date) {
            $kind = in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true) ? 'weekend' : 'weekday';
            if (! in_array($kind, $kinds, true)) {
                continue;
            }

            foreach ($employees as $employee) {
                $tpl = $employee->shiftTemplates->firstWhere('kind', $kind);
                if (! $tpl || ! $tpl->active) {
                    continue;
                }

                [$done, $why] = $this->makeShift($employee, $team, $date->toDateString(), [
                    'start_time' => substr((string) $tpl->getRawOriginal('start_time'), 0, 5),
                    'end_time' => substr((string) $tpl->getRawOriginal('end_time'), 0, 5),
                    'lunch_start' => $tpl->getRawOriginal('lunch_start') ? substr((string) $tpl->getRawOriginal('lunch_start'), 0, 5) : null,
                    'cobro' => $tpl->cobro,
                ]);

                $done ? $created++ : $skipped++;
            }
        }

        return response()->json(['created' => $created, 'skipped' => $skipped]);
    }

    /**
     * POST /api/shifts/repeat {employee_id, month, weekdays:[0..6], start_time, end_time, lunch_start?, cobro?}
     * weekdays usa la convención de JavaScript getDay(): 0 = domingo … 6 = sábado.
     */
    public function repeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'lunch_start' => ['nullable', 'date_format:H:i'],
            'cobro' => ['nullable', 'in:anticipado,posterior'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $team = $employee->team;
        $weekdays = array_map('intval', $data['weekdays']);

        $created = 0;
        $skipped = 0;

        foreach ($this->monthDays($data['month']) as $date) {
            if (! in_array($date->dayOfWeek, $weekdays, true)) {
                continue;
            }

            [$done] = $this->makeShift($employee, $team, $date->toDateString(), [
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'lunch_start' => $data['lunch_start'] ?? null,
                'cobro' => $data['cobro'] ?? 'anticipado',
            ]);

            $done ? $created++ : $skipped++;
        }

        return response()->json(['created' => $created, 'skipped' => $skipped]);
    }

    /** @return \Illuminate\Support\Carbon[] */
    private function monthDays(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $days = [];
        for ($d = $start->copy(); $d->month === $start->month; $d->addDay()) {
            $days[] = $d->copy();
        }

        return $days;
    }

    /**
     * Crea un turno si no existe uno idéntico (mismo empleado, fecha, entrada y
     * salida) y si el almuerzo cabe. Devuelve [creado(bool), motivo(string)].
     */
    private function makeShift(Employee $employee, ?Team $team, string $date, array $in): array
    {
        if ($team && $team->rule === 'lunch' && ! empty($in['lunch_start'])
            && ! ShiftRules::lunchFits($team, $in['start_time'], $in['end_time'], $in['lunch_start'])) {
            return [false, 'lunch'];
        }

        $dupe = Shift::where('employee_id', $employee->id)
            ->where('work_date', $date)
            ->get()
            ->contains(function (Shift $s) use ($in) {
                return substr((string) $s->getRawOriginal('start_time'), 0, 5) === $in['start_time']
                    && substr((string) $s->getRawOriginal('end_time'), 0, 5) === $in['end_time'];
            });

        if ($dupe) {
            return [false, 'duplicate'];
        }

        $resolved = ShiftRules::resolve($team, $in);

        Shift::create([
            'employee_id' => $employee->id,
            'work_date' => $date,
            'start_time' => $in['start_time'],
            'end_time' => $in['end_time'],
            'break_min' => $resolved['break_min'],
            'break_mode' => $resolved['break_mode'],
            'lunch_start' => $resolved['lunch_start'],
            'cobro' => ($in['cobro'] ?? 'anticipado') === 'posterior' ? 'posterior' : 'anticipado',
        ]);

        return [true, 'created'];
    }
}
