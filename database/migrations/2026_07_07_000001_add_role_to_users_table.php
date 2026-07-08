<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add as nullable with a temporary 'admin' default so existing deployed
        // users keep their full-access behavior (they were all effectively admins).
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->default('admin')->after('username');
        });

        // Backfill any existing rows to 'admin'.
        DB::table('users')->whereNull('role')->update(['role' => 'admin']);

        // Tighten: not null, and flip the default to 'employee' for future accounts.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable(false)->default('employee')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
