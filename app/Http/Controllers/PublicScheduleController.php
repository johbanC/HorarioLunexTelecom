<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PublicScheduleController extends Controller
{
    /** GET /ver/{token} → página de solo lectura del horario de un equipo. */
    public function page(string $token): View
    {
        $team = Team::where('share_token', $token)->firstOrFail();

        return view('public', [
            'team' => $team->toApiArray(),
        ]);
    }

    /** GET /ver/{token}/data?month=YYYY-MM → { team, employees, shifts } solo lectura. */
    public function data(Request $request, string $token): JsonResponse
    {
        $team = Team::where('share_token', $token)->first();
        if (! $team) {
            return response()->json(['error' => 'Enlace no válido'], 404);
        }

        $month = (string) $request->query('month', '');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return response()->json(['error' => 'Parámetro month inválido (usa YYYY-MM)'], 400);
        }

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $employees = Employee::where('team_id', $team->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'team_id', 'name', 'sort_order']);

        $shifts = Shift::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->orderBy('employee_id')
            ->orderBy('id')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json([
            'team' => $team->toApiArray(),
            'employees' => $employees,
            'shifts' => $shifts,
        ]);
    }
}
