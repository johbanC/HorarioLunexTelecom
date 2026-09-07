<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Team;

/**
 * Reglas de descanso / almuerzo compartidas por el controlador de turnos y el
 * generador de horario a partir de plantillas.
 */
class ShiftRules
{
    /**
     * Config efectiva de descanso: parte de la del equipo y aplica los overrides
     * del asesor (employees.break_len_min / lunch_min) si están definidos.
     *
     * @return array{rule:string, break_len_min:int, break_interval_min:int, lunch_min:int, break_paid:bool}
     */
    public static function configFor(?Team $team, ?Employee $employee = null): array
    {
        return [
            'rule' => $team->rule ?? 'interval',
            'break_len_min' => (int) ($employee?->break_len_min ?? $team?->break_len_min ?? 15),
            'break_interval_min' => (int) ($team?->break_interval_min ?? 180),
            'lunch_min' => (int) ($employee?->lunch_min ?? $team?->lunch_min ?? 60),
            'break_paid' => (bool) ($team->break_paid ?? true),
        ];
    }

    /** Minutos trabajados entre start y end ("HH:mm"), sumando 24 h si cruza medianoche. */
    public static function grossMinutes(string $start, string $end): int
    {
        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));
        $mins = ($eh * 60 + $em) - ($sh * 60 + $sm);
        if ($mins <= 0) {
            $mins += 24 * 60;
        }

        return $mins;
    }

    /** Cantidad de descansos de la regla 'interval' (nunca justo al final del turno). */
    public static function breakCount(int $total, int $everyMin, int $lenMin): int
    {
        $count = 0;
        $k = 1;
        while ($k * $everyMin + $lenMin <= $total) {
            $count++;
            $k++;
        }

        return $count;
    }

    public static function autoBreakMinutes(string $start, string $end, int $everyMin, int $lenMin): int
    {
        return self::breakCount(self::grossMinutes($start, $end), $everyMin, $lenMin) * $lenMin;
    }

    /** ¿El almuerzo (lunchStart + lunch_min efectivo) cabe completo dentro del turno? */
    public static function lunchFits(array $cfg, string $start, string $end, string $lunchStart): bool
    {
        $total = self::grossMinutes($start, $end);
        $offset = self::grossMinutes($start, $lunchStart);

        return $offset > 0 && $offset + (int) $cfg['lunch_min'] <= $total;
    }

    /**
     * Devuelve [break_min, break_mode, lunch_start] ya resueltos según $cfg
     * (config efectiva del equipo + overrides del asesor).
     * $in acepta: start_time, end_time, break_mode, break_min, lunch_start.
     */
    public static function resolve(array $cfg, array $in): array
    {
        if (($cfg['rule'] ?? 'interval') === 'lunch') {
            $lunchStart = $in['lunch_start'] ?? null;

            return [
                'break_min' => $lunchStart ? (int) $cfg['lunch_min'] : 0,
                'break_mode' => 'manual',
                'lunch_start' => $lunchStart,
            ];
        }

        $breakMode = ($in['break_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
        $breakMin = $breakMode === 'auto'
            ? self::autoBreakMinutes(
                $in['start_time'],
                $in['end_time'],
                (int) $cfg['break_interval_min'],
                (int) $cfg['break_len_min'],
            )
            : (int) ($in['break_min'] ?? 0);

        return [
            'break_min' => $breakMin,
            'break_mode' => $breakMode,
            'lunch_start' => null,
        ];
    }
}
