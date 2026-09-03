<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    /** Las migraciones ya crean los equipos CSR y Contabilidad. */
    private function csr(): Team
    {
        return Team::where('slug', 'csr')->firstOrFail();
    }

    private function contabilidad(): Team
    {
        return Team::where('slug', 'contabilidad')->firstOrFail();
    }

    private function employee(Team $team, string $name = 'Ana'): Employee
    {
        return Employee::create(['name' => $name, 'team_id' => $team->id, 'sort_order' => 0]);
    }

    public function test_migrations_seed_the_two_base_teams(): void
    {
        $this->getJson('/api/teams')
            ->assertOk()
            ->assertJsonPath('0.slug', 'csr')
            ->assertJsonPath('0.rule', 'interval')
            ->assertJsonPath('0.break_paid', true)
            ->assertJsonPath('1.slug', 'contabilidad')
            ->assertJsonPath('1.rule', 'lunch')
            ->assertJsonPath('1.break_paid', false);
    }

    public function test_employees_are_scoped_by_team(): void
    {
        $this->employee($this->csr(), 'CSR Uno');
        $this->employee($this->contabilidad(), 'Conta Uno');

        $this->getJson('/api/employees?team='.$this->csr()->id)
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'CSR Uno');

        $this->getJson('/api/employees?team='.$this->contabilidad()->id)
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Conta Uno');
    }

    public function test_creating_employee_requires_team_and_lands_at_end_of_that_team(): void
    {
        $this->postJson('/api/employees', ['name' => 'Sin equipo'])->assertStatus(422);

        $this->employee($this->csr(), 'Primero');
        $this->postJson('/api/employees', ['name' => 'Segundo', 'team_id' => $this->csr()->id])
            ->assertOk()
            ->assertJson(['name' => 'Segundo', 'sort_order' => 1, 'team_id' => $this->csr()->id]);
    }

    public function test_deleting_employee_cascades_to_shifts(): void
    {
        $emp = $this->employee($this->csr());
        Shift::create([
            'employee_id' => $emp->id, 'work_date' => '2026-09-10',
            'start_time' => '08:00', 'end_time' => '17:00',
            'break_min' => 30, 'break_mode' => 'auto', 'cobro' => 'anticipado',
        ]);

        $this->deleteJson('/api/employees?id='.$emp->id)->assertOk();
        $this->assertDatabaseCount('shifts', 0);
    }

    /** CSR: 15 min por cada 3 h completas, nunca al final del turno. */
    #[DataProvider('csrBreakCases')]
    public function test_csr_auto_break_minutes(string $start, string $end, int $expected): void
    {
        $emp = $this->employee($this->csr());

        $this->postJson('/api/shifts', [
            'employee_id' => $emp->id, 'work_date' => '2026-09-10',
            'start_time' => $start, 'end_time' => $end, 'break_mode' => 'auto',
        ])->assertOk();

        $this->assertSame($expected, Shift::first()->break_min);
        $this->assertNull(Shift::first()->getRawOriginal('lunch_start'));
    }

    public static function csrBreakCases(): array
    {
        return [
            '2h  -> 0' => ['08:00', '10:00', 0],
            '3h  -> 0' => ['08:00', '11:00', 0],
            '6h  -> 1' => ['08:00', '14:00', 15],
            '9h  -> 2' => ['08:00', '17:00', 30],
            'medianoche 22:00-02:00 (4h) -> 1' => ['22:00', '02:00', 15],
        ];
    }

    public function test_contabilidad_lunch_is_stored_and_fixed_at_team_length(): void
    {
        $emp = $this->employee($this->contabilidad());

        $this->postJson('/api/shifts', [
            'employee_id' => $emp->id, 'work_date' => '2026-09-10',
            'start_time' => '09:00', 'end_time' => '18:00',
            'lunch_start' => '13:00', 'break_mode' => 'auto',
        ])->assertOk();

        $shift = Shift::first();
        $this->assertSame(60, $shift->break_min);
        $this->assertSame('13:00', substr($shift->getRawOriginal('lunch_start'), 0, 5));
        $this->assertSame('manual', $shift->break_mode);
    }

    public function test_contabilidad_shift_without_lunch_has_zero_break(): void
    {
        $emp = $this->employee($this->contabilidad());

        $this->postJson('/api/shifts', [
            'employee_id' => $emp->id, 'work_date' => '2026-09-10',
            'start_time' => '09:00', 'end_time' => '13:00',
        ])->assertOk();

        $this->assertSame(0, Shift::first()->break_min);
    }

    public function test_lunch_that_does_not_fit_is_rejected(): void
    {
        $emp = $this->employee($this->contabilidad());

        $this->postJson('/api/shifts', [
            'employee_id' => $emp->id, 'work_date' => '2026-09-10',
            'start_time' => '09:00', 'end_time' => '18:00',
            'lunch_start' => '17:30', // 17:30 + 60min = 18:30 > fin de turno
        ])->assertStatus(422);
    }

    public function test_shifts_can_be_filtered_by_team(): void
    {
        $csrEmp = $this->employee($this->csr(), 'CSR');
        $conEmp = $this->employee($this->contabilidad(), 'CON');
        foreach ([$csrEmp, $conEmp] as $e) {
            Shift::create([
                'employee_id' => $e->id, 'work_date' => '2026-09-05',
                'start_time' => '08:00', 'end_time' => '12:00',
                'break_min' => 0, 'break_mode' => 'auto', 'cobro' => 'anticipado',
            ]);
        }

        $this->getJson('/api/shifts?month=2026-09&team='.$this->csr()->id)
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.employee_id', $csrEmp->id);
    }

    public function test_public_readonly_link_returns_only_its_team(): void
    {
        $csrEmp = $this->employee($this->csr(), 'CSR Visible');
        $this->employee($this->contabilidad(), 'Conta Oculto');
        Shift::create([
            'employee_id' => $csrEmp->id, 'work_date' => '2026-09-07',
            'start_time' => '08:00', 'end_time' => '17:00',
            'break_min' => 30, 'break_mode' => 'auto', 'cobro' => 'anticipado',
        ]);

        $token = $this->csr()->share_token;

        $this->get('/ver/'.$token)->assertOk()->assertSee('CSR');

        $this->getJson('/api/ver/'.$token.'/data?month=2026-09')
            ->assertOk()
            ->assertJsonPath('team.slug', 'csr')
            ->assertJsonCount(1, 'employees')
            ->assertJsonCount(1, 'shifts')
            ->assertJsonPath('shifts.0.start_time', '08:00');

        $this->get('/ver/token-que-no-existe')->assertNotFound();
    }

    public function test_team_crud_and_delete_guard(): void
    {
        $this->postJson('/api/teams', ['name' => 'Ventas', 'rule' => 'interval'])
            ->assertOk()->assertJsonPath('slug', 'ventas');

        $ventas = Team::where('slug', 'ventas')->firstOrFail();
        $this->employee($ventas, 'V1');

        // no se puede borrar un equipo con empleados
        $this->deleteJson('/api/teams?id='.$ventas->id)->assertStatus(409);

        $ventas->employees()->delete();
        $this->deleteJson('/api/teams?id='.$ventas->id)->assertOk();
        $this->assertDatabaseMissing('teams', ['slug' => 'ventas']);
    }

    public function test_regenerate_share_token_changes_it(): void
    {
        $old = $this->csr()->share_token;

        $res = $this->postJson('/api/teams/regenerate-token', ['id' => $this->csr()->id])->assertOk();

        $this->assertNotSame($old, $res->json('share_token'));
        $this->assertSame($res->json('share_token'), $this->csr()->fresh()->share_token);
    }

    public function test_bad_month_is_rejected(): void
    {
        $this->getJson('/api/shifts?month=2026')->assertStatus(400);
        $this->getJson('/api/shifts?month=2026-13')->assertStatus(400);
    }
}
