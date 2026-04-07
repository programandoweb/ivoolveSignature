<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class WhatsappService
{
    public function sendMessage(string $to, string $text, ?string $imageUrl = null): array
    {
        $url = (string) config('signature.whatsapp_endpoint', '');

        if ($url === '') {
            return [
                'status' => 404,
                'error' => 'WhatsApp endpoint is not configured.',
            ];
        }

        $payload = [
            'to' => $to,
            'message' => $text,
        ];

        if ($imageUrl !== null && $imageUrl !== '') {
            $payload['imageUrl'] = $imageUrl;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('signature.whatsapp_timeout', 10))
                ->connectTimeout((int) config('signature.whatsapp_connect_timeout', 5))
                ->withOptions([
                    'verify' => (bool) config('signature.whatsapp_verify_ssl', true),
                ])
                ->post($url, $payload);

            return [
                'endpoint' => $url,
                'status' => $response->status(),
                'response' => json_decode($response->body(), true) ?? $response->body(),
            ];
        } catch (Throwable $exception) {
            return [
                'endpoint' => $url,
                'status' => 500,
                'error' => 'Error al enviar mensaje WhatsApp: '.$exception->getMessage(),
            ];
        }
    }
}
