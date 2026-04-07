<?php

namespace App\Services;

use App\Models\Signature;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function __construct(
        private readonly WhatsappService $whatsappService,
    ) {
    }

    public function issue(Signature $signature): void
    {
        $otpCode = $this->generateCode();

        $signature->forceFill([
            'otp_code' => $otpCode,
        ])->save();

        $signature->loadMissing('document');

        $context = [
            'document_id' => $signature->document_id,
            'signature_id' => $signature->id,
            'version_number' => $signature->version_number,
            'user_id' => $signature->user_id,
            'phone_number' => $signature->phone_number,
            'otp_code' => $otpCode,
        ];

        if ($signature->phone_number !== null && $signature->phone_number !== '') {
            $whatsappResult = $this->whatsappService->sendMessage(
                $signature->phone_number,
                $this->buildWhatsappMessage($signature, $otpCode),
            );

            $context['channel'] = 'whatsapp';
            $context['whatsapp'] = $whatsappResult;

            if (($whatsappResult['status'] ?? 500) >= 400) {
                Log::warning('Signature OTP WhatsApp dispatch failed.', $context);
            }
        } else {
            $context['channel'] = 'log';
        }

        Log::info('Signature OTP dispatched.', [
            ...$context,
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

    private function buildWhatsappMessage(Signature $signature, string $otpCode): string
    {
        $reference = $signature->document?->external_id ?? $signature->document_id;

        return "Hola {$signature->user_name}, tu codigo OTP para firmar el documento {$reference} es {$otpCode}. No lo compartas con nadie.";
    }
}
