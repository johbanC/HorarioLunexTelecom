<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Shift;
use App\Support\ShiftRules;
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

        $team = Employee::find($data['employee_id'])?->team;
        if ($team && $team->rule === 'lunch' && ! empty($data['lunch_start'])
            && ! ShiftRules::lunchFits($team, $data['start_time'], $data['end_time'], $data['lunch_start'])) {
            abort(response()->json([
                'error' => 'El almuerzo no cabe dentro del turno (revisa la hora de inicio del almuerzo).',
            ], 422));
        }

        return $data;
    }

    /** Aplica las reglas del equipo del empleado antes de guardar. */
    private function normalize(array $data): array
    {
        $team = Employee::find($data['employee_id'])?->team;
        $resolved = ShiftRules::resolve($team, $data);

        return [
            'employee_id' => (int) $data['employee_id'],
            'work_date' => $data['work_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'break_min' => $resolved['break_min'],
            'break_mode' => $resolved['break_mode'],
            'lunch_start' => $resolved['lunch_start'],
            'cobro' => ($data['cobro'] ?? 'anticipado') === 'posterior' ? 'posterior' : 'anticipado',
        ];
    }
}
