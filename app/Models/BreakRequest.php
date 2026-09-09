<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BreakRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_request_id',
        'break_in',
        'break_out',
    ];

    protected $casts = [
        'break_in' => 'datetime',
        'break_out' => 'datetime',
    ];

    /**
     * この休憩申請に属する勤怠申請を取得
     */
    public function attendanceRequests(): HasMany
    {
        return $this->hasMany(AttendanceRequest::class);
    }
}
