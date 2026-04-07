<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
            'app_source' => ['required', 'string', 'max:255'],
            'pdf' => [
                'required',
                'file',
                'mimetypes:application/pdf',
                'max:'.config('signature.max_upload_size_kb', 20480),
            ],
            'signers' => ['required', 'array', 'min:1'],
            'signers.*.user_id' => ['required', 'string', 'max:255', 'distinct'],
            'signers.*.user_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'pdf.mimetypes' => 'El archivo debe ser un PDF valido.',
            'signers.required' => 'Debes enviar al menos un firmante.',
            'signers.*.user_id.distinct' => 'Cada firmante debe tener una identificacion unica dentro del documento.',
        ];
    }
}
