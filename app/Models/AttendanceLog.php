<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceLog extends Model
{
    use HasFactory;
    
    protected $fillable = ['emp_code', 'punch_time', 'punch_state', 'terminal_alias', 'work_code'];
}
