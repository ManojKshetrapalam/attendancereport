<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EasyTimeService;
use App\Models\Employee;
use App\Models\AttendanceLog;

class SyncAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync employees and attendance logs from EasyTime Pro';

    /**
     * Execute the console command.
     */
    public function handle(EasyTimeService $easyTime)
    {
        $this->info('Starting EasyTime Pro Sync...');

        try {
            $this->info('Authenticating...');
            $easyTime->login();
            $this->info('Authenticated successfully.');

            // Sync Employees
            $this->info('Syncing Employees...');
            $page = 1;
            do {
                $employeeData = $easyTime->getEmployees($page);
                if (!$employeeData || empty($employeeData['data'])) {
                    break;
                }

                foreach ($employeeData['data'] as $emp) {
                    Employee::updateOrCreate(
                        ['emp_code' => $emp['emp_code']],
                        [
                            'first_name' => $emp['first_name'] ?? null,
                            'last_name' => $emp['last_name'] ?? null,
                            'department' => $emp['department']['dept_name'] ?? null,
                            'position' => $emp['position']['position_name'] ?? null,
                        ]
                    );
                }

                $page++;
            } while (!empty($employeeData['next']));

            $this->info('Employees synced.');

            // Sync Attendance Logs (Incremental)
            $this->info('Syncing Attendance Logs...');
            $page = 1;
            $newLogsCount = 0;
            $stopSync = false;

            // Get the latest punch time we already have in our DB
            $latestLocalPunch = AttendanceLog::max('punch_time');
            if ($latestLocalPunch) {
                $this->info("Incremental sync: fetching records newer than {$latestLocalPunch}");
                $startTime = $latestLocalPunch;
            } else {
                $this->info("No local records found. Performing full sync (this will take a while)...");
                $startTime = null;
            }
            
            do {
                $this->info("Fetching page {$page}...");
                // Use limit=1000 and server-side start_time filtering for speed
                $transactionData = $easyTime->getTransactions($page, 1000, $startTime);
                
                if (!$transactionData || empty($transactionData['data'])) {
                    break;
                }

                foreach ($transactionData['data'] as $tx) {
                    $log = AttendanceLog::firstOrCreate(
                        [
                            'emp_code'   => $tx['emp_code'],
                            'punch_time' => $tx['punch_time'],
                        ],
                        [
                            'punch_state'    => $tx['punch_state'] ?? null,
                            'terminal_alias' => $tx['terminal_alias'] ?? null,
                            'work_code'      => $tx['work_code'] ?? null,
                        ]
                    );

                    if ($log->wasRecentlyCreated) {
                        $newLogsCount++;
                    }
                }

                $page++;
                
            } while (!empty($transactionData['next']));

            $this->info("Attendance logs synced. {$newLogsCount} new logs imported.");

        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info('Sync completed successfully.');
        return Command::SUCCESS;
    }
}
