<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_manual_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('sw', 5)->nullable();   // "HH:MM" — null = no time-in recorded
            $table->string('ew', 5)->nullable();   // "HH:MM" — null = no time-out recorded
            $table->string('shift_start', 5);      // shift reference for this specific day
            $table->string('shift_end', 5);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_manual_attendances');
    }
};
