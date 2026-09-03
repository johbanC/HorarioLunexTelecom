<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /** GET /api/employees[?team=ID] → [{id, team_id, name, sort_order}, ...] */
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->when($request->filled('team'), fn ($q) => $q->where('team_id', (int) $request->query('team')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'team_id', 'name', 'sort_order']);

        return response()->json($employees);
    }

    /** POST /api/employees {name, team_id} → {id, team_id, name, sort_order} */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Falta el nombre del empleado'], 400);
        }

        // Nuevos empleados van al final de la lista de SU equipo.
        $nextOrder = (int) (Employee::where('team_id', $data['team_id'])->max('sort_order') ?? -1) + 1;

        $employee = Employee::create([
            'name' => $name,
            'team_id' => $data['team_id'],
            'sort_order' => $nextOrder,
        ]);

        return response()->json([
            'id' => $employee->id,
            'team_id' => $employee->team_id,
            'name' => $employee->name,
            'sort_order' => $employee->sort_order,
        ]);
    }

    /** PUT /api/employees {id, name[, team_id]} → {ok:true} */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Falta id o nombre'], 400);
        }

        $employee = Employee::find($data['id']);
        if (! $employee) {
            return response()->json(['error' => 'Empleado no encontrado'], 404);
        }

        $employee->name = $name;
        if (! empty($data['team_id']) && $data['team_id'] !== $employee->team_id) {
            $employee->team_id = $data['team_id'];
            $employee->sort_order = (int) (Employee::where('team_id', $data['team_id'])->max('sort_order') ?? -1) + 1;
        }
        $employee->save();

        return response()->json(['ok' => true]);
    }

    /** DELETE /api/employees?id=X → {ok:true} (cascada a sus turnos) */
    public function destroy(Request $request): JsonResponse
    {
        $id = (int) $request->query('id', 0);
        if (! $id) {
            return response()->json(['error' => 'Falta id'], 400);
        }

        Employee::where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }
}
