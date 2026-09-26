<?php

namespace App\Models;

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
        'is_approved',
        'date',
        'clock_in',
        'clock_out',
        'comment',
        'request_date',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'request_date' => 'date',
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

    public function getRequestStatusAttribute()
    {
        // dump($this->is_approved);
        if ($this->is_approved) {
            return '承認済み';
        }

        return '承認待ち';
    }
}
