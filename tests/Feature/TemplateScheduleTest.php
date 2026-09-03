<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TemplateScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function csr(): Team
    {
        return Team::where('slug', 'csr')->firstOrFail();
    }

    private function contabilidad(): Team
    {
        return Team::where('slug', 'contabilidad')->firstOrFail();
    }

    private function emp(Team $team, string $name = 'Ana'): Employee
    {
        return Employee::create(['name' => $name, 'team_id' => $team->id, 'sort_order' => 0]);
    }

    public function test_template_upsert_is_one_row_per_employee_and_kind(): void
    {
        $e = $this->emp($this->csr());

        $this->postJson('/api/templates', [
            'employee_id' => $e->id, 'kind' => 'weekday',
            'start_time' => '08:00', 'end_time' => '17:00', 'cobro' => 'anticipado',
        ])->assertOk();

        $this->postJson('/api/templates', [
            'employee_id' => $e->id, 'kind' => 'weekday',
            'start_time' => '09:00', 'end_time' => '18:00',
        ])->assertOk()->assertJsonPath('start_time', '09:00');

        $this->assertDatabaseCount('shift_templates', 1);

        $this->getJson('/api/templates?team='.$this->csr()->id)
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.end_time', '18:00');
    }

    public function test_generate_month_fills_weekdays_and_weekends_from_templates(): void
    {
        $e = $this->emp($this->csr());
        // Lun–Vie 08:00–14:00 (6 h → 1 descanso de 15), fin de semana 10:00–13:00 (3 h → 0)
        $this->postJson('/api/templates', ['employee_id' => $e->id, 'kind' => 'weekday', 'start_time' => '08:00', 'end_time' => '14:00'])->assertOk();
        $this->postJson('/api/templates', ['employee_id' => $e->id, 'kind' => 'weekend', 'start_time' => '10:00', 'end_time' => '13:00', 'active' => true])->assertOk();

        // Septiembre 2026: 22 días de semana, 8 de fin de semana (30 días).
        $this->postJson('/api/schedule/generate', ['team_id' => $this->csr()->id, 'month' => '2026-09'])
            ->assertOk()->assertJson(['created' => 30, 'skipped' => 0]);

        $this->assertSame(30, Shift::count());
        $this->assertSame(22, Shift::where('break_min', 15)->count());
        $this->assertSame(8, Shift::where('break_min', 0)->count());
    }

    public function test_inactive_weekend_template_is_not_generated(): void
    {
        $e = $this->emp($this->csr());
        $this->postJson('/api/templates', ['employee_id' => $e->id, 'kind' => 'weekday', 'start_time' => '08:00', 'end_time' => '16:00'])->assertOk();
        $this->postJson('/api/templates', ['employee_id' => $e->id, 'kind' => 'weekend', 'start_time' => '10:00', 'end_time' => '14:00', 'active' => false])->assertOk();

        $this->postJson('/api/schedule/generate', ['team_id' => $this->csr()->id, 'month' => '2026-09'])
            ->assertOk()->assertJson(['created' => 22]);

        $this->assertSame(0, Shift::where('start_time', '10:00:00')->count());
    }

    public function test_generate_only_weekends_kind(): void
    {
        $e = $this->emp($this->csr());
        $this->postJson('/api/templates', ['employee_id' => $e->id, 'kind' => 'weekday', 'start_time' => '08:00', 'end_time' => '16:00'])->assertOk();
        $this->postJson('/api/templates', ['employee_id' => $e->id, 'kind' => 'weekend', 'start_time' => '10:00', 'end_time' => '14:00'])->assertOk();

        $this->postJson('/api/schedule/generate', ['team_id' => $this->csr()->id, 'month' => '2026-09', 'kinds' => ['weekend']])
            ->assertOk()->assertJson(['created' => 8]);
    }

    public function test_regenerating_skips_identical_shifts_but_stays_additive(): void
    {
        $e = $this->emp($this->csr());
        $this->postJson('/api/templates', ['employee_id' => $e->id, 'kind' => 'weekday', 'start_time' => '08:00', 'end_time' => '16:00'])->assertOk();

        $this->postJson('/api/schedule/generate', ['team_id' => $this->csr()->id, 'month' => '2026-09', 'kinds' => ['weekday']])
            ->assertOk()->assertJson(['created' => 22]);

        // Segunda pasada: nada nuevo, todo omitido (no se duplica).
        $this->postJson('/api/schedule/generate', ['team_id' => $this->csr()->id, 'month' => '2026-09', 'kinds' => ['weekday']])
            ->assertOk()->assertJson(['created' => 0, 'skipped' => 22]);

        $this->assertSame(22, Shift::count());

        // Cambiar la plantilla y regenerar SÍ agrega turnos adicionales.
        $this->postJson('/api/templates', ['employee_id' => $e->id, 'kind' => 'weekday', 'start_time' => '17:00', 'end_time' => '21:00'])->assertOk();
        $this->postJson('/api/schedule/generate', ['team_id' => $this->csr()->id, 'month' => '2026-09', 'kinds' => ['weekday']])
            ->assertOk()->assertJson(['created' => 22]);

        $this->assertSame(44, Shift::count());
    }

    public function test_repeat_shift_over_selected_weekdays(): void
    {
        $e = $this->emp($this->csr());

        // Lunes (1) y miércoles (3) de septiembre 2026.
        $res = $this->postJson('/api/shifts/repeat', [
            'employee_id' => $e->id, 'month' => '2026-09',
            'weekdays' => [1, 3], 'start_time' => '08:00', 'end_time' => '12:00', 'cobro' => 'posterior',
        ])->assertOk();

        $mondays = 0;
        $wednesdays = 0;
        foreach (Shift::all() as $sh) {
            $dow = Carbon::parse($sh->work_date)->dayOfWeek;
            if ($dow === Carbon::MONDAY) {
                $mondays++;
            }
            if ($dow === Carbon::WEDNESDAY) {
                $wednesdays++;
            }
            $this->assertContains($dow, [Carbon::MONDAY, Carbon::WEDNESDAY]);
        }
        $this->assertSame($res->json('created'), $mondays + $wednesdays);
        $this->assertSame('posterior', Shift::first()->cobro);
    }

    public function test_contabilidad_template_generates_lunch_that_is_deducted(): void
    {
        $e = $this->emp($this->contabilidad());
        $this->postJson('/api/templates', [
            'employee_id' => $e->id, 'kind' => 'weekday',
            'start_time' => '09:00', 'end_time' => '18:00', 'lunch_start' => '13:00',
        ])->assertOk();

        $this->postJson('/api/schedule/generate', ['team_id' => $this->contabilidad()->id, 'month' => '2026-09', 'kinds' => ['weekday']])
            ->assertOk()->assertJson(['created' => 22]);

        $shift = Shift::first();
        $this->assertSame(60, $shift->break_min);
        $this->assertSame('13:00', substr($shift->getRawOriginal('lunch_start'), 0, 5));
    }

    public function test_template_with_lunch_that_does_not_fit_is_rejected(): void
    {
        $e = $this->emp($this->contabilidad());

        $this->postJson('/api/templates', [
            'employee_id' => $e->id, 'kind' => 'weekday',
            'start_time' => '09:00', 'end_time' => '18:00', 'lunch_start' => '17:45',
        ])->assertStatus(422);
    }
}
