<?php

namespace App\Services;

use App\Models\Signature;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function issue(Signature $signature): void
    {
        $otpCode = $this->generateCode();

        $signature->forceFill([
            'otp_code' => $otpCode,
        ])->save();

        Log::info('Signature OTP dispatched.', [
            'document_id' => $signature->document_id,
            'signature_id' => $signature->id,
            'version_number' => $signature->version_number,
            'user_id' => $signature->user_id,
            'otp_code' => $otpCode,
        ]);
    }

    public function isValid(Signature $signature, string $otpCode): bool
    {
        return $signature->otp_code !== null
            && $signature->otp_verified_at === null
            && hash_equals($signature->otp_code, $otpCode);
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
