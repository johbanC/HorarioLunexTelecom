<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftTemplate extends Model
{
    protected $fillable = [
        'employee_id',
        'kind',
        'start_time',
        'end_time',
        'lunch_start',
        'cobro',
        'active',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'employee_id' => (int) $this->employee_id,
            'kind' => $this->kind,
            'start_time' => substr((string) $this->getRawOriginal('start_time'), 0, 5),
            'end_time' => substr((string) $this->getRawOriginal('end_time'), 0, 5),
            'lunch_start' => $this->getRawOriginal('lunch_start') ? substr((string) $this->getRawOriginal('lunch_start'), 0, 5) : null,
            'cobro' => $this->cobro,
            'active' => (bool) $this->active,
        ];
    }
}
