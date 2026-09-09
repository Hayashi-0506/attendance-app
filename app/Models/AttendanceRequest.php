<?php

namespace App\Models;

use App\Enum\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'user_id',
        'approval_status',
        'clock_in',
        'clock_out',
        'comment',
    ];

    protected $casts = [
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'approval_status' => ApprovalStatus::class,
    ];

    /**
     * この勤怠申請を所有するユーザーを取得
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この勤怠申請に属する勤怠を取得
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * この勤怠申請に属する休憩申請を取得
     */
    public function breakRequests(): HasMany
    {
        return $this->hasMany(BreakRequest::class);
    }
}
