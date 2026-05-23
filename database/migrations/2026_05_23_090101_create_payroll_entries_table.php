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
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->integer('days_present')->default(0);
            $table->decimal('total_basic_pay', 10, 2)->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->decimal('overtime_pay', 10, 2)->default(0);
            $table->integer('late_minutes')->default(0);
            $table->decimal('late_deduction', 10, 2)->default(0);
            $table->integer('undertime_minutes')->default(0);
            $table->decimal('undertime_deduction', 10, 2)->default(0);
            $table->decimal('holiday_pay', 10, 2)->default(0);
            $table->decimal('gross_pay', 10, 2)->default(0);
            $table->decimal('cash_advance', 10, 2)->default(0);
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->decimal('first_release', 10, 2)->default(0);
            $table->decimal('second_release', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
