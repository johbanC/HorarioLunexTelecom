<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Team extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'share_token',
        'rule',
        'break_len_min',
        'break_interval_min',
        'lunch_min',
        'break_paid',
        'sort_order',
    ];

    protected $casts = [
        'break_len_min' => 'integer',
        'break_interval_min' => 'integer',
        'lunch_min' => 'integer',
        'break_paid' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Team $team) {
            if (empty($team->share_token)) {
                $team->share_token = Str::random(24);
            }
            if (empty($team->slug)) {
                $team->slug = static::uniqueSlug($team->name);
            }
        });
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'equipo';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** Config de reglas que necesita el frontend para calcular descansos y horas. */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'share_token' => $this->share_token,
            'rule' => $this->rule,
            'break_len_min' => $this->break_len_min,
            'break_interval_min' => $this->break_interval_min,
            'lunch_min' => $this->lunch_min,
            'break_paid' => $this->break_paid,
            'sort_order' => $this->sort_order,
        ];
    }
}
