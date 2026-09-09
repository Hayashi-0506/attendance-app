<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EditAttendanceRequest extends FormRequest
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
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['nullable', 'date_format:H:i', 'after_or_equal:new_clock_in'],
            'breaks' => ['nullable', 'array'],
            'breaks.*.new_break_in' => ['nullable', 'required_with:breaks.*.new_break_out', 'date_format:H:i', 'after_or_equal:new_clock_in', 'before_or_equal:new_clock_out'],
            'breaks.*.new_break_out' => ['nullable', 'date_format:H:i', 'after_or_equal:breaks.*.new_break_in', 'before_or_equal:new_clock_out'],
            'comment' => ['required', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください',
            'new_clock_in.date_format' => '出勤時間は「時:分」形式（例：09:00）で入力してください',
            'new_clock_out.date_format' => '退勤時間は「時:分」形式（例：18:00）で入力してください',
            'new_clock_out.after_or_equal' => '出勤時間もしくは退勤時間が不適切な値です',

            'breaks.*.new_break_in.date_format' => '休憩開始時間は「時:分」形式（例：12:00）で入力してください',
            'breaks.*.new_break_in.after_or_equal' => '休憩時間が不適切な値です',
            'breaks.*.new_break_in.before_or_equal' => '休憩時間が不適切な値です',

            'breaks.*.new_break_out.date_format' => '休憩終了時間は「時:分」形式（例：13:00）で入力してください',
            'breaks.*.new_break_out.after_or_equal' => '休憩時間が不適切な値です',
            'breaks.*.new_break_out.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',

            'comment.required' => 'コメントを入力してください',
        ];
    }

    protected function prepareForValidation(): void
    {
        $breakIns = $this->input('new_break_in', []);
        $breakOuts = $this->input('new_break_out', []);

        $breaks = [];
        $count = max(count($breakIns), count($breakOuts));

        for ($i = 0; $i < $count; $i++) {
            $breaks[] = [
                'new_break_in' => $this->normalizeTime($breakIns[$i] ?? null),
                'new_break_out' => $this->normalizeTime($breakOuts[$i] ?? null),
            ];
        }

        $this->merge([
            'new_clock_in' => $this->normalizeTime($this->input('new_clock_in')),
            'new_clock_out' => $this->normalizeTime($this->input('new_clock_out')),
            'breaks' => $breaks,
        ]);
    }

    private function normalizeTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // 全角を半角に変換
        $value = mb_convert_kana($value, 'as');

        // "9:00" のように時が1桁の場合、先頭に0を付けて "09:00" にする
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
            return sprintf('%02d:%s', (int) $matches[1], $matches[2]);
        }

        return $value;
    }
}
