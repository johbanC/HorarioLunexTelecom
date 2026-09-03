<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ShiftTemplate;
use App\Support\ShiftRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftTemplateController extends Controller
{
    /** GET /api/templates?team=ID → plantillas de los empleados de ese equipo */
    public function index(Request $request): JsonResponse
    {
        $q = ShiftTemplate::query();

        if ($request->filled('team')) {
            $ids = Employee::where('team_id', (int) $request->query('team'))->pluck('id');
            $q->whereIn('employee_id', $ids);
        }

        return response()->json($q->get()->map->toApiArray()->values());
    }

    /** POST /api/templates {employee_id, kind, start_time, end_time, lunch_start?, cobro?, active?} */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'kind' => ['required', 'in:weekday,weekend'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'lunch_start' => ['nullable', 'date_format:H:i'],
            'cobro' => ['nullable', 'in:anticipado,posterior'],
            'active' => ['nullable', 'boolean'],
        ]);

        $team = Employee::find($data['employee_id'])?->team;
        if ($team && $team->rule === 'lunch' && ! empty($data['lunch_start'])
            && ! ShiftRules::lunchFits($team, $data['start_time'], $data['end_time'], $data['lunch_start'])) {
            return response()->json(['error' => 'El almuerzo no cabe dentro del turno de la plantilla.'], 422);
        }

        $template = ShiftTemplate::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'kind' => $data['kind']],
            [
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'lunch_start' => ($team && $team->rule === 'lunch') ? ($data['lunch_start'] ?? null) : null,
                'cobro' => $data['cobro'] ?? 'anticipado',
                'active' => $data['active'] ?? true,
            ],
        );

        return response()->json($template->toApiArray());
    }

    /** DELETE /api/templates?employee_id=X&kind=weekday|weekend */
    public function destroy(Request $request): JsonResponse
    {
        $employeeId = (int) $request->query('employee_id', 0);
        $kind = (string) $request->query('kind', '');
        if (! $employeeId || ! in_array($kind, ['weekday', 'weekend'], true)) {
            return response()->json(['error' => 'Falta employee_id o kind'], 400);
        }

        ShiftTemplate::where('employee_id', $employeeId)->where('kind', $kind)->delete();

        return response()->json(['ok' => true]);
    }
}
