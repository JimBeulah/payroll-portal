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
        Schema::table('payroll_manual_attendances', function (Blueprint $table) {
            // When true, drops the Excel entry for this date so the manual entry is the sole source.
            // When false (default), stacks on top of the Excel entry (e.g. a callback second shift).
            $table->boolean('is_override')->default(false)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_manual_attendances', function (Blueprint $table) {
            $table->dropColumn('is_override');
        });
    }
};
