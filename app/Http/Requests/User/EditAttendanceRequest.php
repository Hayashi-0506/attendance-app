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
        $rules = [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i', 'after_or_equal:new_clock_in'],
            'new_break_in' => ['nullable', 'array'],
            'new_break_out' => ['nullable', 'array'],
            'comment' => ['required', 'max:255'],
        ];

        $breakIns = $this->input('new_break_in', []);
        $breakOuts = $this->input('new_break_out', []);

        // 両方の配列のインデックスを合わせて走査（片方しかキーが無いケースも考慮）
        $indexes = array_unique(array_merge(array_keys($breakIns), array_keys($breakOuts)));
        sort($indexes);

        $lastIndex = end($indexes);

        foreach ($indexes as $index) {
            $inKey = "new_break_in.$index";
            $outKey = "new_break_out.$index";

            if ($index === $lastIndex) {
                // 最後の要素だけは両方nullでもOK（片方入力されたらもう片方も必須）
                $rules[$inKey] = ['bail', 'nullable', 'date_format:H:i', 'after_or_equal:new_clock_in', 'before_or_equal:new_clock_out', "required_with:$outKey"];
                $rules[$outKey] = ['bail', 'nullable', 'date_format:H:i', "after_or_equal:$inKey", 'before_or_equal:new_clock_out', "required_with:$inKey"];
            } else {
                // それ以外は両方必須
                $rules[$inKey] = ['bail', 'required', 'date_format:H:i', 'after_or_equal:new_clock_in', 'before_or_equal:new_clock_out'];
                $rules[$outKey] = ['bail', 'required', 'date_format:H:i', "after_or_equal:$inKey", 'before_or_equal:new_clock_out'];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください',
            'new_clock_in.date_format' => '出勤時間は「時:分」形式（例：09:00）で入力してください',
            'new_clock_out.required' => '退勤時間を入力してください',
            'new_clock_out.date_format' => '退勤時間は「時:分」形式（例：18:00）で入力してください',
            'new_clock_out.after_or_equal' => '出勤時間もしくは退勤時間が不適切な値です',

            'new_break_in.*.required' => '休憩開始時間を入力してください',
            'new_break_in.*.required_with' => '休憩開始時間を入力してください',
            'new_break_in.*.date_format' => '休憩開始時間は「時:分」形式（例：12:00）で入力してください',
            'new_break_in.*.after_or_equal' => '休憩時間が不適切な値です',
            'new_break_in.*.before_or_equal' => '休憩時間が不適切な値です',

            'new_break_out.*.required' => '休憩終了時間を入力してください',
            'new_break_out.*.required_with' => '休憩終了時間を入力してください',
            'new_break_out.*.date_format' => '休憩終了時間は「時:分」形式（例：13:00）で入力してください',
            'new_break_out.*.after_or_equal' => '休憩時間が不適切な値です',
            'new_break_out.*.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',

            'comment.required' => '備考を記入してください',
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
