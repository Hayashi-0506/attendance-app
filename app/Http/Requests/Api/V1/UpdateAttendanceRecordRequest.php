<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class UpdateAttendanceRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'clock_in' => ['sometimes', 'required', 'date_format:H:i:s'],
            'clock_out' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['clock_in', 'clock_out'])) {
                    return;
                }

                $record = $this->route('attendanceRecord');

                $toTime = fn ($value) => $value ? Carbon::parse($value)->format('H:i:s') : null;

                $clockIn = $this->has('clock_in') ? $this->input('clock_in') : $toTime($record->clock_in);
                $clockOut = $this->has('clock_out') ? $this->input('clock_out') : $toTime($record->clock_out);

                if ($clockOut !== null && $clockOut <= $clockIn) {
                    $validator->errors()->add('clock_out', '退勤時刻は出勤時刻より後にしてください。');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
