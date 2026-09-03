<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 60)->unique();
            $table->string('share_token', 32)->unique();

            // 'interval' = un descanso corto cada X horas (regla CSR)
            // 'lunch'    = una hora de almuerzo en un horario elegido a mano
            $table->enum('rule', ['interval', 'lunch'])->default('interval');

            $table->integer('break_len_min')->default(15);      // duración de cada descanso (regla interval)
            $table->integer('break_interval_min')->default(180); // cada cuántos minutos toca descanso (regla interval)
            $table->integer('lunch_min')->default(60);           // duración del almuerzo (regla lunch)
            $table->boolean('break_paid')->default(true);        // false => el descanso/almuerzo se resta de las horas pagadas

            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
