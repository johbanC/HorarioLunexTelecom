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
        'lunch_start',
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
            'start_time' => $this->asHm($this->getRawOriginal('start_time')),
            'end_time' => $this->asHm($this->getRawOriginal('end_time')),
            'break_min' => (int) $this->break_min,
            'break_mode' => $this->break_mode,
            'lunch_start' => $this->asHm($this->getRawOriginal('lunch_start')),
            'cobro' => $this->cobro,
        ];
    }

    private function asHm(?string $raw): ?string
    {
        return $raw ? substr($raw, 0, 5) : null;
    }

    private function asDateString(?string $raw): ?string
    {
        return $raw ? substr($raw, 0, 10) : null;
    }
}
