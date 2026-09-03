<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Solo se usa en equipos con regla 'lunch': hora de inicio del almuerzo
            // elegida a mano al crear el turno. NULL en equipos con regla 'interval'.
            $table->time('lunch_start')->nullable()->after('break_mode');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('lunch_start');
        });
    }
};
