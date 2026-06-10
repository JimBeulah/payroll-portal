<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPayrollJson extends Command
{
    protected $signature   = 'payroll:import-json {file : Path to the exported JSON file}';
    protected $description = 'Import payroll data from a JSON export file into the current database';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (!file_exists($path)) {
            $this->error("File not found: $path");
            return 1;
        }

        $data = json_decode(file_get_contents($path), true);

        if (!$data) {
            $this->error('Invalid JSON file.');
            return 1;
        }

        if (!$this->confirm('This will WIPE and replace all existing data. Continue?')) {
            return 0;
        }

        DB::transaction(function () use ($data) {
            // Disable foreign key checks during wipe
            DB::statement('SET session_replication_role = replica');

            $tables = [
                'payroll_entries',
                'payroll_manual_attendances',
                'attendance_uploads',
                'payroll_runs',
                'app_settings',
                'holidays',
                'employees',
                'users',
            ];

            foreach ($tables as $table) {
                DB::table($table)->truncate();
                $this->line("  Cleared $table");
            }

            DB::statement('SET session_replication_role = DEFAULT');

            // Insert in dependency order
            $this->insertTable('users',                     $data['users']                      ?? []);
            $this->insertTable('employees',                 $data['employees']                  ?? []);
            $this->insertTable('holidays',                  $data['holidays']                   ?? []);
            $this->insertTable('payroll_runs',              $data['payroll_runs']               ?? []);
            $this->insertTable('payroll_entries',           $data['payroll_entries']            ?? []);
            $this->insertTable('payroll_manual_attendances',$data['payroll_manual_attendances'] ?? []);
            $this->insertTable('attendance_uploads',        $data['attendance_uploads']         ?? []);

            // app_settings uses key-value, no id column
            foreach (($data['app_settings'] ?? []) as $row) {
                DB::table('app_settings')->insert((array) $row);
            }
            $this->info('  Inserted app_settings: ' . count($data['app_settings'] ?? []));

            // Reset sequences so new inserts don't collide with existing ids
            foreach (['users', 'employees', 'holidays', 'payroll_runs', 'payroll_entries', 'payroll_manual_attendances', 'attendance_uploads'] as $table) {
                $max = DB::table($table)->max('id');
                if ($max) {
                    DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), {$max})");
                }
            }
        });

        $this->info('Import complete.');
        return 0;
    }

    private function insertTable(string $table, array $rows): void
    {
        if (empty($rows)) {
            $this->line("  Skipped $table (empty)");
            return;
        }

        // Cast each row from object/array to plain array
        $rows = array_map(fn($r) => (array) $r, $rows);

        // Insert in chunks to avoid query size limits
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        $this->info("  Inserted $table: " . count($rows));
    }
}
