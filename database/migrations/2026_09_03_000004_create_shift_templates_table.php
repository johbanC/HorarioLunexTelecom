<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // 'weekday' = Lun–Vie · 'weekend' = Sáb–Dom
            $table->enum('kind', ['weekday', 'weekend']);

            $table->time('start_time');
            $table->time('end_time');
            $table->time('lunch_start')->nullable();
            $table->enum('cobro', ['anticipado', 'posterior'])->default('anticipado');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['employee_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
