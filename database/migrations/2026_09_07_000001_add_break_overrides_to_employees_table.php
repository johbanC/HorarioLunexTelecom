<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Overrides opcionales del descanso para un asesor puntual.
            // NULL = usa el valor del equipo.
            $table->integer('break_len_min')->nullable()->after('sort_order'); // regla 'interval'
            $table->integer('lunch_min')->nullable()->after('break_len_min');   // regla 'lunch'
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['break_len_min', 'lunch_min']);
        });
    }
};
