<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['Karelys', 'Juana', 'Valentina', 'Juan Manuel', 'Juanita Restrepo'];

        foreach ($names as $order => $name) {
            Employee::firstOrCreate(
                ['name' => $name],
                ['sort_order' => $order],
            );
        }
    }
}
