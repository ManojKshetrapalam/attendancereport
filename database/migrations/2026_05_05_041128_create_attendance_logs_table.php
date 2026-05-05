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
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('emp_code');
            $table->dateTime('punch_time');
            $table->string('punch_state')->nullable();
            $table->string('terminal_alias')->nullable();
            $table->string('work_code')->nullable();
            $table->timestamps();
            
            // Prevent duplicate entries for the same punch
            $table->unique(['emp_code', 'punch_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
