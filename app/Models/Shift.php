<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    protected $fillable = [
        'employee_id',
        'work_date',
        'start_time',
        'end_time',
        'break_min',
        'break_mode',
        'cobro',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'break_min' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Normaliza la fila para el frontend: fechas "YYYY-MM-DD" y horas "HH:mm",
     * igual que devolvía la versión PHP plano (api/shifts.php).
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'employee_id' => (int) $this->employee_id,
            'work_date' => $this->asDateString($this->getRawOriginal('work_date')),
            'start_time' => substr((string) $this->getRawOriginal('start_time'), 0, 5),
            'end_time' => substr((string) $this->getRawOriginal('end_time'), 0, 5),
            'break_min' => (int) $this->break_min,
            'break_mode' => $this->break_mode,
            'cobro' => $this->cobro,
        ];
    }

    private function asDateString(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        return substr($raw, 0, 10);
    }
}
