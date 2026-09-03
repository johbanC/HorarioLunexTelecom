<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Asegura que existan los dos equipos base (para bases de datos que
        //    ya tienen empleados cargados antes de esta versión).
        $csrId = $this->ensureTeam([
            'name' => 'CSR',
            'slug' => 'csr',
            'rule' => 'interval',
            'break_len_min' => 15,
            'break_interval_min' => 180,
            'break_paid' => true,
            'sort_order' => 0,
        ]);

        $this->ensureTeam([
            'name' => 'Contabilidad',
            'slug' => 'contabilidad',
            'rule' => 'lunch',
            'lunch_min' => 60,
            'break_paid' => false,
            'sort_order' => 1,
        ]);

        // 2. Agrega la columna y asigna todos los empleados actuales a CSR.
        Schema::table('employees', function (Blueprint $table) use ($csrId) {
            $table->foreignId('team_id')
                ->default($csrId)
                ->after('id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->index('team_id');
        });

        DB::table('employees')->whereNull('team_id')->update(['team_id' => $csrId]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
    }

    private function ensureTeam(array $attrs): int
    {
        $existing = DB::table('teams')->where('slug', $attrs['slug'])->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('teams')->insertGetId(array_merge([
            'share_token' => Str::random(24),
            'lunch_min' => 60,
            'break_len_min' => 15,
            'break_interval_min' => 180,
            'break_paid' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }
};
