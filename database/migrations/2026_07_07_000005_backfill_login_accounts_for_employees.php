<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Create a login account for every existing employee that does not have one.
     *
     * Username = lowercase of the name with all non-alphanumeric characters removed
     * (spaces stripped), de-duplicated with a numeric suffix. Password = "123700".
     * Data-only migration: guarded so it is safe/idempotent to run on a deployed DB.
     */
    public function up(): void
    {
        $employees = DB::table('employees')
            ->whereNull('user_id')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'name']);

        foreach ($employees as $employee) {
            $base = preg_replace('/[^a-z0-9]/', '', strtolower($employee->name)) ?: 'employee';

            $username = $base;
            $i = 1;
            while (DB::table('users')->where('username', $username)->exists()) {
                $username = $base.$i;
                $i++;
            }

            $userId = DB::table('users')->insertGetId([
                'name' => $employee->name,
                'username' => $username,
                'role' => 'employee',
                'password' => Hash::make('123700'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('employees')->where('id', $employee->id)->update(['user_id' => $userId]);
        }
    }

    /**
     * Data backfill — not reversed automatically to avoid deleting accounts that may
     * since have been used. Unlink/remove manually if a rollback is truly needed.
     */
    public function down(): void
    {
        //
    }
};
