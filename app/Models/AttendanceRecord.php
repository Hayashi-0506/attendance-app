<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    /**
     * この勤怠を所有するユーザーを取得
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この勤怠に属する休憩を取得
     */
    public function breakRecords(): HasMany
    {
        return $this->hasMany(BreakRecord::class);
    }

    /**
     * この勤怠に属する勤怠申請を取得
     */
    public function attendanceRequests(): HasMany
    {
        return $this->hasMany(AttendanceRequest::class);
    }

    public function latestBreakRecord(): HasOne
    {
        return $this->hasOne(BreakRecord::class)->latestOfMany('break_in');
    }

    public function pendingAttendanceRequest(): HasOne
    {
        return $this->hasOne(AttendanceRequest::class)->where('is_approved', false);
    }

    protected function formattedDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->date ? Carbon::parse($this->date)->isoFormat('MM月DD日(ddd)') : '',
        );
    }

    protected function formattedClockIn(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->clock_in?->format('H:i') ?? '',
        );
    }

    protected function formattedClockOut(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->clock_out?->format('H:i') ?? '',
        );
    }

    public function attendanceStatus(): Attribute
    {
        if (! $this->clock_in) {
            return Attribute::make(get: fn () => '勤務外');
        }

        if ($this->clock_out) {
            return Attribute::make(get: fn () => '退勤済');
        }

        $breaks = $this->latestBreakRecord;
        if ($breaks && ! $breaks->break_out) {
            return Attribute::make(get: fn () => '休憩中');
        }

        return Attribute::make(get: fn () => '出勤中');
    }

    public function totalTime(): Attribute
    {
        $totalSeconds = 0;

        if ($this->clock_in && $this->clock_out) {
            // diffInSeconds() で「終了時刻 - 開始時刻」の秒数を取得
            $in = Carbon::parse($this->clock_in)->setSecond(0);
            $out = Carbon::parse($this->clock_out)->setSecond(0);
            $totalSeconds += $out->diffInSeconds($in);

            $totalBreakTime = $this->total_break_time;
            if ($totalBreakTime) {
                $totalSeconds -= $totalBreakTime;
            }
        }

        return Attribute::make(get: fn () => $totalSeconds);
    }

    public function totalBreakTime(): Attribute
    {
        if (! $this->clock_in || $this->breakRecords->isEmpty()) {
            return Attribute::make(get: fn () => 0);
        }

        $totalSeconds = 0;
        // 紐づく休憩レコードをループして、それぞれの休憩時間を合計する
        foreach ($this->breakRecords as $break) {
            if ($break->break_in && $break->break_out) {
                // diffInSeconds() で「終了時刻 - 開始時刻」の秒数を取得
                $in = Carbon::parse($break->break_in)->setSecond(0);
                $out = Carbon::parse($break->break_out)->setSecond(0);
                $totalSeconds += $out->diffInSeconds($in);
            }
        }

        return Attribute::make(get: fn () => $totalSeconds);
    }

    public function formatSecondsToHM(?int $seconds): string
    {
        if (! $seconds) {
            return '';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%d:%02d', $hours, $minutes);
    }
}
