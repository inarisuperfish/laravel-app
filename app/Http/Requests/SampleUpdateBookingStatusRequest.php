<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SampleUpdateBookingStatusRequest extends FormRequest
{
    /**
     * 認可ロジック（必要ならここで設定）
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // ここを `false` にするとリクエストが拒否される
    }

    /**
     * バリデーションルール
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['cancel', 'force_cancel'])],
        ];
    }

    /**
     * カスタムメッセージ（オプション）
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'status.required' => 'ステータスは必須です。',
            'status.in' => 'ステータスは「cancel」または「force_cancel」のみ許可されています。',
        ];
    }
}
