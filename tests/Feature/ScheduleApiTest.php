<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_seeded_employees_in_order(): void
    {
        $this->seed(\Database\Seeders\EmployeeSeeder::class);

        $this->getJson('/api/employees')
            ->assertOk()
            ->assertJsonCount(5)
            ->assertJsonPath('0.name', 'Karelys')
            ->assertJsonPath('4.name', 'Juanita Restrepo');
    }

    public function test_creates_employee_at_end_of_list(): void
    {
        Employee::create(['name' => 'Ana', 'sort_order' => 0]);

        $this->postJson('/api/employees', ['name' => '  Beto  '])
            ->assertOk()
            ->assertJson(['name' => 'Beto', 'sort_order' => 1]);
    }

    public function test_deleting_employee_cascades_to_shifts(): void
    {
        $emp = Employee::create(['name' => 'Ana', 'sort_order' => 0]);
        Shift::create([
            'employee_id' => $emp->id,
            'work_date' => '2026-09-10',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_min' => 30,
            'break_mode' => 'auto',
            'cobro' => 'anticipado',
        ]);

        $this->deleteJson('/api/employees?id='.$emp->id)->assertOk();

        $this->assertDatabaseCount('shifts', 0);
    }

    /** 15 min de descanso cada 3 horas completas, nunca al final del turno. */
    #[DataProvider('breakCases')]
    public function test_auto_break_minutes_follow_team_rule(string $start, string $end, int $expected): void
    {
        $emp = Employee::create(['name' => 'Ana', 'sort_order' => 0]);

        $this->postJson('/api/shifts', [
            'employee_id' => $emp->id,
            'work_date' => '2026-09-10',
            'start_time' => $start,
            'end_time' => $end,
            'break_mode' => 'auto',
        ])->assertOk();

        $this->assertSame($expected, Shift::first()->break_min);
    }

    public static function breakCases(): array
    {
        return [
            '2h  -> 0'  => ['08:00', '10:00', 0],
            '3h  -> 0 (descanso caería al salir)' => ['08:00', '11:00', 0],
            '6h  -> 1' => ['08:00', '14:00', 15],
            '9h  -> 2' => ['08:00', '17:00', 30],
            'cruza medianoche 22:00-02:00 (4h) -> 1' => ['22:00', '02:00', 15],
        ];
    }

    public function test_manual_break_is_respected(): void
    {
        $emp = Employee::create(['name' => 'Ana', 'sort_order' => 0]);

        $this->postJson('/api/shifts', [
            'employee_id' => $emp->id,
            'work_date' => '2026-09-10',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_mode' => 'manual',
            'break_min' => 45,
        ])->assertOk();

        $this->assertSame(45, Shift::first()->break_min);
    }

    public function test_month_filter_returns_only_that_month(): void
    {
        $emp = Employee::create(['name' => 'Ana', 'sort_order' => 0]);
        foreach (['2026-08-31', '2026-09-01', '2026-09-30', '2026-10-01'] as $d) {
            Shift::create([
                'employee_id' => $emp->id,
                'work_date' => $d,
                'start_time' => '08:00',
                'end_time' => '12:00',
                'break_min' => 0,
                'break_mode' => 'auto',
                'cobro' => 'anticipado',
            ]);
        }

        $this->getJson('/api/shifts?month=2026-09')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.work_date', '2026-09-01')
            ->assertJsonPath('0.start_time', '08:00');
    }

    public function test_bad_month_is_rejected(): void
    {
        $this->getJson('/api/shifts?month=2026')->assertStatus(400);
    }
}
