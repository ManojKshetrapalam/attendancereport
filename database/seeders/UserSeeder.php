<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@vvt.com'],
            [
                'name'     => 'Admin User',
                'password' => \Illuminate\Support\Facades\Hash::make('admin123'),
                'role'     => 'admin',
            ]
        );

        // Employees
        $employees = \App\Models\Employee::all();
        foreach ($employees as $emp) {
            $email = strtolower($emp->first_name) . '@vvt.com';
            \App\Models\User::updateOrCreate(
                ['email' => $email],
                [
                    'name'     => trim($emp->first_name . ' ' . $emp->last_name),
                    'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                    'role'     => 'employee',
                    'emp_code' => $emp->emp_code,
                ]
            );
        }
    }
}
