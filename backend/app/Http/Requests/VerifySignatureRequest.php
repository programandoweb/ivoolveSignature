<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifySignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_id' => ['required', 'uuid', 'exists:documents,id'],
            'user_id' => ['required', 'string', 'max:255'],
            'otp_code' => ['required', 'digits:6'],
        ];
    }
}
