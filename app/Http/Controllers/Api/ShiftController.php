<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ShiftController extends Controller
{
    /** GET /api/shifts?month=YYYY-MM[&team=ID] → filas de turnos del mes */
    public function index(Request $request): JsonResponse
    {
        $month = (string) $request->query('month', '');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return response()->json(['error' => 'Parámetro month inválido (usa YYYY-MM)'], 400);
        }

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $shifts = Shift::query()
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->when($request->filled('team'), function ($q) use ($request) {
                $teamId = (int) $request->query('team');
                $q->whereIn('employee_id', Employee::where('team_id', $teamId)->pluck('id'));
            })
            ->orderBy('work_date')
            ->orderBy('employee_id')
            ->orderBy('id')
            ->get();

        return response()->json($shifts->map->toApiArray()->values());
    }

    /** POST /api/shifts → {id} */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $shift = Shift::create($this->normalize($data));

        return response()->json(['id' => $shift->id]);
    }

    /** PUT /api/shifts {id, ...} → {ok:true} */
    public function update(Request $request): JsonResponse
    {
        $data = $this->validated($request, requireId: true);

        $shift = Shift::find($data['id']);
        if (! $shift) {
            return response()->json(['error' => 'Turno no encontrado'], 404);
        }

        $shift->update($this->normalize($data));

        return response()->json(['ok' => true]);
    }

    /** DELETE /api/shifts?id=X → {ok:true} */
    public function destroy(Request $request): JsonResponse
    {
        $id = (int) $request->query('id', 0);
        if (! $id) {
            return response()->json(['error' => 'Falta id'], 400);
        }

        Shift::where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, bool $requireId = false): array
    {
        $rules = [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_min' => ['nullable', 'integer', 'min:0'],
            'break_mode' => ['nullable', 'in:auto,manual'],
            'lunch_start' => ['nullable', 'date_format:H:i'],
            'cobro' => ['nullable', 'in:anticipado,posterior'],
        ];

        if ($requireId) {
            $rules['id'] = ['required', 'integer'];
        }

        $data = $request->validate($rules);

        // El almuerzo (si se indicó) debe caber completo dentro del turno.
        $team = Employee::find($data['employee_id'])?->team;
        if ($team && $team->rule === 'lunch' && ! empty($data['lunch_start'])) {
            $total = $this->grossMinutes($data['start_time'], $data['end_time']);
            $offset = $this->grossMinutes($data['start_time'], $data['lunch_start']);
            if ($offset <= 0 || $offset + $team->lunch_min > $total) {
                abort(response()->json([
                    'error' => 'El almuerzo no cabe dentro del turno (revisa la hora de inicio del almuerzo).',
                ], 422));
            }
        }

        return $data;
    }

    /**
     * Aplica las reglas del EQUIPO del empleado antes de guardar:
     *  - regla 'interval' (CSR): break_min = 15 min por cada 3 h completas
     *    (nunca al final); lunch_start = null.
     *  - regla 'lunch' (Contabilidad): break_min = lunch_min del equipo si se
     *    indicó lunch_start, si no 0; break_mode = 'manual'.
     */
    private function normalize(array $data): array
    {
        $team = Employee::find($data['employee_id'])?->team;
        $rule = $team->rule ?? 'interval';

        $cobro = ($data['cobro'] ?? 'anticipado') === 'posterior' ? 'posterior' : 'anticipado';

        if ($rule === 'lunch') {
            $lunchStart = $data['lunch_start'] ?? null;
            $breakMin = $lunchStart ? (int) ($team->lunch_min ?? 60) : 0;
            $breakMode = 'manual';
        } else {
            $lunchStart = null;
            $breakMode = ($data['break_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
            $breakMin = $breakMode === 'auto'
                ? $this->autoBreakMinutes(
                    $data['start_time'],
                    $data['end_time'],
                    (int) ($team->break_interval_min ?? 180),
                    (int) ($team->break_len_min ?? 15),
                )
                : (int) ($data['break_min'] ?? 0);
        }

        return [
            'employee_id' => (int) $data['employee_id'],
            'work_date' => $data['work_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'break_min' => $breakMin,
            'break_mode' => $breakMode,
            'lunch_start' => $lunchStart,
            'cobro' => $cobro,
        ];
    }

    private function grossMinutes(string $start, string $end): int
    {
        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));
        $mins = ($eh * 60 + $em) - ($sh * 60 + $sm);
        if ($mins <= 0) {
            $mins += 24 * 60; // el turno cruza medianoche
        }

        return $mins;
    }

    private function breakCount(int $total, int $everyMin, int $lenMin): int
    {
        $count = 0;
        $k = 1;
        while ($k * $everyMin + $lenMin <= $total) {
            $count++;
            $k++;
        }

        return $count;
    }

    private function autoBreakMinutes(string $start, string $end, int $everyMin, int $lenMin): int
    {
        return $this->breakCount($this->grossMinutes($start, $end), $everyMin, $lenMin) * $lenMin;
    }
}
