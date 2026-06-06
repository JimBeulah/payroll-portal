<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
