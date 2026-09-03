<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Team;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $csr = Team::where('slug', 'csr')->first();
        if (! $csr) {
            return;
        }

        $names = ['Karelys', 'Juana', 'Valentina', 'Juan Manuel', 'Juanita Restrepo'];

        foreach ($names as $order => $name) {
            Employee::firstOrCreate(
                ['name' => $name, 'team_id' => $csr->id],
                ['sort_order' => $order],
            );
        }
    }
}
