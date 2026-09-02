<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ShiftController extends Controller
{
    private const BREAK_EVERY_MIN = 180; // 3 horas
    private const BREAK_LEN_MIN = 15;

    /** GET /api/shifts?month=YYYY-MM → [{id, employee_id, work_date, start_time, end_time, break_min, break_mode, cobro}, ...] */
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
            'cobro' => ['nullable', 'in:anticipado,posterior'],
        ];

        if ($requireId) {
            $rules['id'] = ['required', 'integer'];
        }

        return $request->validate($rules);
    }

    /**
     * Aplica las reglas de negocio antes de guardar:
     *  - break_mode por defecto 'auto', cobro por defecto 'anticipado'
     *  - si es 'auto', break_min se recalcula desde start/end (15 min cada 3h
     *    completas, y solo si queda turno después — nunca al final).
     */
    private function normalize(array $data): array
    {
        $breakMode = ($data['break_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
        $cobro = ($data['cobro'] ?? 'anticipado') === 'posterior' ? 'posterior' : 'anticipado';

        $breakMin = $breakMode === 'auto'
            ? $this->autoBreakMinutes($data['start_time'], $data['end_time'])
            : (int) ($data['break_min'] ?? 0);

        return [
            'employee_id' => (int) $data['employee_id'],
            'work_date' => $data['work_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'break_min' => $breakMin,
            'break_mode' => $breakMode,
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

    private function breakCount(int $total): int
    {
        $count = 0;
        $k = 1;
        while ($k * self::BREAK_EVERY_MIN + self::BREAK_LEN_MIN <= $total) {
            $count++;
            $k++;
        }

        return $count;
    }

    private function autoBreakMinutes(string $start, string $end): int
    {
        return $this->breakCount($this->grossMinutes($start, $end)) * self::BREAK_LEN_MIN;
    }
}
