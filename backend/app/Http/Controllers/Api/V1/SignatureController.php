<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\IntegrityViolationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateSignatureRequest;
use App\Http\Requests\VerifySignatureRequest;
use App\Models\Document;
use App\Models\Signature;
use App\Services\IntegrityService;
use App\Services\SignatureWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SignatureController extends Controller
{
    public function __construct(
        private readonly SignatureWorkflowService $workflowService,
        private readonly IntegrityService $integrityService,
    ) {
    }

    public function initiate(InitiateSignatureRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $document = $this->workflowService->initiate(
            $request->file('pdf'),
            $payload['external_id'],
            $payload['app_source'],
            $payload['signers'],
        );

        return response()->json([
            'message' => 'Document registration completed successfully.',
            'data' => $this->serializeDocument($document),
        ], JsonResponse::HTTP_CREATED);
    }

    public function verify(VerifySignatureRequest $request): JsonResponse
    {
        $payload = $request->validated();
        try {
            $result = $this->workflowService->verify(
                $payload['document_id'],
                $payload['user_id'],
                $payload['otp_code'],
                (string) $request->ip(),
                $request->userAgent(),
            );
        } catch (IntegrityViolationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        /** @var \App\Models\Document $document */
        $document = $result['document'];
        /** @var \App\Models\Signature $signature */
        $signature = $result['signature'];

        return response()->json([
            'message' => 'The electronic signature was applied successfully.',
            'data' => [
                'document_id' => $document->id,
                'status' => $document->status->value,
                'final_hash' => $document->final_hash,
                'validation_url' => route('signatures.validate', ['document' => $document->id]),
                'signature' => [
                    'id' => $signature->id,
                    'version_number' => $signature->version_number,
                    'user_id' => $signature->user_id,
                    'user_name' => $signature->user_name,
                    'signed_at' => $signature->otp_verified_at?->toIso8601String(),
                    'file_path' => $signature->file_path,
                    'current_hash' => $signature->current_hash,
                ],
            ],
        ]);
    }

    public function validateDocument(Document $document): View
    {
        $document->load([
            'signatures' => fn ($query) => $query->orderBy('version_number'),
        ]);

        $latestVersion = $document->signatures->whereNotNull('file_path')->sortByDesc('version_number')->first();
        $isValid = false;
        $integrityMessage = 'No signed or original physical version was found.';

        if ($latestVersion !== null) {
            try {
                $this->integrityService->assertSignatureIntegrity($latestVersion);
                $isValid = $document->final_hash !== null
                    && $latestVersion->current_hash !== null
                    && hash_equals($document->final_hash, $latestVersion->current_hash);
                $integrityMessage = $isValid
                    ? 'El hash fisico coincide con el hash final registrado por el microservicio.'
                    : 'El hash final del documento no coincide con la ultima version materializada.';
            } catch (IntegrityViolationException $exception) {
                $integrityMessage = $exception->getMessage();
            }
        }

        return view('validation', [
            'brandColor' => config('signature.branding_color', '#FE4FA2'),
            'document' => $document,
            'isValid' => $isValid,
            'integrityMessage' => $integrityMessage,
            'completedSignatures' => $document->signatures->where('version_number', '>', 0)->whereNotNull('otp_verified_at')->count(),
            'totalSignatures' => $document->signatures->where('version_number', '>', 0)->count(),
            'latestVersion' => $latestVersion,
        ]);
    }

    private function serializeDocument(Document $document): array
    {
        return [
            'document_id' => $document->id,
            'external_id' => $document->external_id,
            'app_source' => $document->app_source,
            'status' => $document->status->value,
            'final_hash' => $document->final_hash,
            'validation_url' => route('signatures.validate', ['document' => $document->id]),
            'signatures' => $document->signatures
                ->where('version_number', '>', 0)
                ->values()
                ->map(fn (Signature $signature): array => [
                    'id' => $signature->id,
                    'version_number' => $signature->version_number,
                    'user_id' => $signature->user_id,
                    'user_name' => $signature->user_name,
                    'status' => $signature->otp_verified_at === null ? 'pending' : 'signed',
                ])
                ->all(),
        ];
    }
}
