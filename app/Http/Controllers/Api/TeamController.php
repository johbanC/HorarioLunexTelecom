<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /** GET /api/teams → [{id, name, slug, share_token, rule, ...}, ...] */
    public function index(): JsonResponse
    {
        $teams = Team::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($teams->map->toApiArray()->values());
    }

    /** POST /api/teams {name, rule, ...} → {id, ...} */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedTeam($request);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Falta el nombre del equipo'], 400);
        }

        $nextOrder = (int) (Team::max('sort_order') ?? -1) + 1;

        $team = Team::create([
            'name' => $name,
            'rule' => $data['rule'] ?? 'interval',
            'break_len_min' => $data['break_len_min'] ?? 15,
            'break_interval_min' => $data['break_interval_min'] ?? 180,
            'lunch_min' => $data['lunch_min'] ?? 60,
            'break_paid' => $data['break_paid'] ?? ($data['rule'] ?? 'interval') === 'interval',
            'sort_order' => $nextOrder,
        ]);

        return response()->json($team->toApiArray());
    }

    /** PUT /api/teams {id, ...} → {ok:true} */
    public function update(Request $request): JsonResponse
    {
        $data = $this->validatedTeam($request, requireId: true);

        $team = Team::find($data['id']);
        if (! $team) {
            return response()->json(['error' => 'Equipo no encontrado'], 404);
        }

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Falta el nombre del equipo'], 400);
        }

        $team->fill([
            'name' => $name,
            'rule' => $data['rule'] ?? $team->rule,
            'break_len_min' => $data['break_len_min'] ?? $team->break_len_min,
            'break_interval_min' => $data['break_interval_min'] ?? $team->break_interval_min,
            'lunch_min' => $data['lunch_min'] ?? $team->lunch_min,
            'break_paid' => array_key_exists('break_paid', $data) ? (bool) $data['break_paid'] : $team->break_paid,
        ])->save();

        return response()->json(['ok' => true]);
    }

    /** DELETE /api/teams?id=X → {ok:true} (bloqueado si tiene empleados) */
    public function destroy(Request $request): JsonResponse
    {
        $id = (int) $request->query('id', 0);
        if (! $id) {
            return response()->json(['error' => 'Falta id'], 400);
        }

        $team = Team::withCount('employees')->find($id);
        if (! $team) {
            return response()->json(['ok' => true]);
        }

        if ($team->employees_count > 0) {
            return response()->json([
                'error' => 'No se puede eliminar un equipo con empleados. Mueve o elimina primero a sus empleados.',
            ], 409);
        }

        if (Team::count() <= 1) {
            return response()->json(['error' => 'Debe existir al menos un equipo.'], 409);
        }

        $team->delete();

        return response()->json(['ok' => true]);
    }

    /** POST /api/teams/regenerate-token {id} → {share_token} */
    public function regenerateToken(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $team = Team::find($data['id']);
        if (! $team) {
            return response()->json(['error' => 'Equipo no encontrado'], 404);
        }

        $team->update(['share_token' => Str::random(24)]);

        return response()->json(['share_token' => $team->share_token]);
    }

    private function validatedTeam(Request $request, bool $requireId = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'rule' => ['nullable', 'in:interval,lunch'],
            'break_len_min' => ['nullable', 'integer', 'min:0', 'max:240'],
            'break_interval_min' => ['nullable', 'integer', 'min:30', 'max:600'],
            'lunch_min' => ['nullable', 'integer', 'min:0', 'max:240'],
            'break_paid' => ['nullable', 'boolean'],
        ];

        if ($requireId) {
            $rules['id'] = ['required', 'integer'];
        }

        return $request->validate($rules);
    }
}
