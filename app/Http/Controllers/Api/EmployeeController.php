<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /** GET /api/employees → [{id, name, sort_order}, ...] */
    public function index(): JsonResponse
    {
        $employees = Employee::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order']);

        return response()->json($employees);
    }

    /** POST /api/employees {name} → {id, name, sort_order} */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Falta el nombre del empleado'], 400);
        }

        // Nuevos empleados van al final de la lista.
        $nextOrder = (int) (Employee::max('sort_order') ?? -1) + 1;

        $employee = Employee::create([
            'name' => $name,
            'sort_order' => $nextOrder,
        ]);

        return response()->json([
            'id' => $employee->id,
            'name' => $employee->name,
            'sort_order' => $employee->sort_order,
        ]);
    }

    /** PUT /api/employees {id, name} → {ok:true} */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Falta id o nombre'], 400);
        }

        $employee = Employee::find($data['id']);
        if (! $employee) {
            return response()->json(['error' => 'Empleado no encontrado'], 404);
        }

        $employee->update(['name' => $name]);

        return response()->json(['ok' => true]);
    }

    /** DELETE /api/employees?id=X → {ok:true} (cascada a sus turnos) */
    public function destroy(Request $request): JsonResponse
    {
        $id = (int) $request->query('id', 0);
        if (! $id) {
            return response()->json(['error' => 'Falta id'], 400);
        }

        // ON DELETE CASCADE en la BD borra también los turnos de este empleado.
        Employee::where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }
}
