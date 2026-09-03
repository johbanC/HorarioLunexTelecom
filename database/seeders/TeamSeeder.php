<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        Team::firstOrCreate(
            ['slug' => 'csr'],
            [
                'name' => 'CSR',
                'rule' => 'interval',       // 15 min de descanso cada 3 h
                'break_len_min' => 15,
                'break_interval_min' => 180,
                'break_paid' => true,       // el descanso NO se descuenta del pago
                'sort_order' => 0,
            ],
        );

        Team::firstOrCreate(
            ['slug' => 'contabilidad'],
            [
                'name' => 'Contabilidad',
                'rule' => 'lunch',          // 1 hora de almuerzo, en horario manual
                'lunch_min' => 60,
                'break_paid' => false,      // el almuerzo SÍ se descuenta del pago
                'sort_order' => 1,
            ],
        );
    }
}
