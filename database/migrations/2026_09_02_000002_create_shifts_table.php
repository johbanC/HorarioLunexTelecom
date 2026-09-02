<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('break_min')->default(0);
            $table->enum('break_mode', ['auto', 'manual'])->default('auto');
            $table->enum('cobro', ['anticipado', 'posterior'])->default('anticipado');
            $table->timestamps();

            $table->index('work_date');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
