<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Signature;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SignatureWorkflowService
{
    public function __construct(
        private readonly FilesystemManager $filesystem,
        private readonly IntegrityService $integrityService,
        private readonly OtpService $otpService,
        private readonly PdfProcessorService $pdfProcessorService,
    ) {
    }

    public function initiate(UploadedFile $pdf, string $externalId, string $appSource, array $signers): Document
    {
        $documentId = (string) Str::uuid();
        $disk = $this->disk();
        $originalPath = $disk->putFileAs($documentId, $pdf, 'v0-original.pdf');

        if ($originalPath === false) {
            throw new \RuntimeException('Unable to store the original PDF version.');
        }

        $originalHash = $this->integrityService->calculateStoredFileHash($originalPath);

        try {
            return DB::transaction(function () use ($appSource, $documentId, $externalId, $originalHash, $originalPath, $signers): Document {
                $document = Document::query()->create([
                    'id' => $documentId,
                    'external_id' => $externalId,
                    'app_source' => $appSource,
                    'status' => DocumentStatus::Pending,
                    'final_hash' => $originalHash,
                ]);

                Signature::query()->create([
                    'document_id' => $document->id,
                    'version_number' => 0,
                    'file_path' => $originalPath,
                    'current_hash' => $originalHash,
                ]);

                foreach (array_values($signers) as $index => $signer) {
                    $signature = Signature::query()->create([
                        'document_id' => $document->id,
                        'version_number' => $index + 1,
                        'user_id' => $signer['user_id'],
                        'user_name' => $signer['user_name'],
                    ]);

                    $this->otpService->issue($signature);
                }

                return $document->fresh('signatures');
            });
        } catch (Throwable $exception) {
            $disk->deleteDirectory($documentId);

            throw $exception;
        }
    }

    public function verify(string $documentId, string $userId, string $otpCode, string $ipAddress, ?string $userAgent): array
    {
        $targetPath = null;

        try {
            return DB::transaction(function () use ($documentId, $userId, $otpCode, $ipAddress, $userAgent, &$targetPath): array {
                $document = Document::query()->lockForUpdate()->find($documentId);

                if ($document === null) {
                    throw (new ModelNotFoundException())->setModel(Document::class, [$documentId]);
                }

                $latestMaterializedVersion = Signature::query()
                    ->where('document_id', $document->id)
                    ->whereNotNull('file_path')
                    ->orderByDesc('version_number')
                    ->lockForUpdate()
                    ->firstOrFail();

                $signature = Signature::query()
                    ->where('document_id', $document->id)
                    ->where('user_id', $userId)
                    ->where('version_number', '>', 0)
                    ->whereNull('otp_verified_at')
                    ->orderBy('version_number')
                    ->lockForUpdate()
                    ->first();

                if ($signature === null) {
                    throw ValidationException::withMessages([
                        'user_id' => ['No pending signature was found for this user in the requested document.'],
                    ]);
                }

                if ($signature->version_number !== $latestMaterializedVersion->version_number + 1) {
                    throw ValidationException::withMessages([
                        'document_id' => ['The signature cannot be applied yet because previous versions are still pending.'],
                    ]);
                }

                if (! $this->otpService->isValid($signature, $otpCode)) {
                    throw ValidationException::withMessages([
                        'otp_code' => ['The provided OTP code is invalid or has already been consumed.'],
                    ]);
                }

                $this->integrityService->assertSignatureIntegrity($latestMaterializedVersion);

                $targetPath = sprintf('%s/v%d-signed.pdf', $document->id, $signature->version_number);
                $validationUrl = route('signatures.validate', ['document' => $document->id]);
                $this->disk()->makeDirectory($document->id);

                $this->pdfProcessorService->stamp(
                    $this->disk()->path($latestMaterializedVersion->file_path),
                    $this->disk()->path($targetPath),
                    [
                        'user_name' => $signature->user_name,
                        'user_id' => $signature->user_id,
                        'signed_at' => now()->format('Y-m-d H:i:s'),
                        'ip_address' => $ipAddress,
                        'hash' => $latestMaterializedVersion->current_hash,
                    ],
                    $validationUrl,
                );

                $newHash = $this->integrityService->calculateStoredFileHash($targetPath);

                $signature->forceFill([
                    'otp_code' => null,
                    'otp_verified_at' => now(),
                    'ip_address' => $ipAddress,
                    'user_agent' => Str::limit((string) $userAgent, 1000, ''),
                    'file_path' => $targetPath,
                    'current_hash' => $newHash,
                ])->save();

                $remainingSignatures = Signature::query()
                    ->where('document_id', $document->id)
                    ->where('version_number', '>', 0)
                    ->whereNull('otp_verified_at')
                    ->count();

                $document->forceFill([
                    'status' => $remainingSignatures === 0
                        ? DocumentStatus::Completed
                        : DocumentStatus::Partial,
                    'final_hash' => $newHash,
                ])->save();

                return [
                    'document' => $document->fresh('signatures'),
                    'signature' => $signature->fresh(),
                ];
            });
        } catch (Throwable $exception) {
            if ($targetPath !== null) {
                $this->disk()->delete($targetPath);
            }

            throw $exception;
        }
    }

    private function disk()
    {
        return $this->filesystem->disk(config('signature.storage_disk'));
    }
}
