<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Signature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SignatureDocumentSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $disk = Storage::disk(config('signature.storage_disk', 'signatures'));
        $appSources = ['ivoolve-flow', 'movex', 'bululu', 'rrhh-portal'];

        foreach (range(1, 10) as $index) {
            $documentId = sprintf('10000000-0000-4000-8000-%012d', $index);
            $externalId = sprintf('DOC-%s-%04d', date('Y'), $index);
            $appSource = $appSources[($index - 1) % count($appSources)];
            $signerCount = ($index % 3) + 1;
            $completedCount = $this->completedCountForIndex($index, $signerCount);
            $status = $this->statusForCounts($completedCount, $signerCount);
            $createdAt = Carbon::now()->subDays(11 - $index)->startOfDay()->addHours(8);

            $versionZeroPath = "{$documentId}/v0-original.pdf";
            $versionZeroBinary = $this->makePdfBinary(
                "Documento {$externalId}",
                [
                    "Aplicacion origen: {$appSource}",
                    'Version: v0',
                    'Estado inicial: pendiente de firmas',
                ],
            );

            $disk->put($versionZeroPath, $versionZeroBinary);
            $currentFinalHash = hash('sha256', $versionZeroBinary);

            $document = Document::query()->updateOrCreate(
                ['id' => $documentId],
                [
                    'external_id' => $externalId,
                    'app_source' => $appSource,
                    'status' => $status,
                    'final_hash' => $currentFinalHash,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addMinutes(max($completedCount, 1) * 10),
                ],
            );

            Signature::query()->updateOrCreate(
                [
                    'document_id' => $document->id,
                    'version_number' => 0,
                ],
                [
                    'user_id' => null,
                    'user_name' => null,
                    'phone_number' => null,
                    'otp_code' => null,
                    'otp_verified_at' => null,
                    'ip_address' => null,
                    'user_agent' => null,
                    'file_path' => $versionZeroPath,
                    'current_hash' => $currentFinalHash,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ],
            );

            foreach (range(1, $signerCount) as $versionNumber) {
                $signerCreatedAt = $createdAt->copy()->addMinutes($versionNumber * 10);
                $isCompleted = $versionNumber <= $completedCount;
                $userId = sprintf('CC%08d', ($index * 100) + $versionNumber);
                $userName = "Firmante {$index}-{$versionNumber}";
                $phoneNumber = sprintf('57300123%04d', ($index * 10) + $versionNumber);
                $filePath = null;
                $currentHash = null;
                $otpVerifiedAt = null;
                $otpCode = str_pad((string) (($index * 137 + $versionNumber * 23) % 1000000), 6, '0', STR_PAD_LEFT);
                $ipAddress = null;
                $userAgent = null;

                if ($isCompleted) {
                    $filePath = "{$documentId}/v{$versionNumber}-signed.pdf";
                    $signedBinary = $this->makePdfBinary(
                        "Documento {$externalId}",
                        [
                            "Aplicacion origen: {$appSource}",
                            "Version: v{$versionNumber}",
                            "Firmado por: {$userName}",
                            "Identificacion: {$userId}",
                        ],
                    );

                    $disk->put($filePath, $signedBinary);
                    $currentHash = hash('sha256', $signedBinary);
                    $currentFinalHash = $currentHash;
                    $otpVerifiedAt = $signerCreatedAt->copy()->addMinutes(2);
                    $otpCode = null;
                    $ipAddress = "192.168.1.{$index}";
                    $userAgent = 'SignatureDocumentSeeder/1.0';
                }

                Signature::query()->updateOrCreate(
                    [
                        'document_id' => $document->id,
                        'version_number' => $versionNumber,
                    ],
                    [
                        'user_id' => $userId,
                        'user_name' => $userName,
                        'phone_number' => $phoneNumber,
                        'otp_code' => $otpCode,
                        'otp_verified_at' => $otpVerifiedAt,
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                        'file_path' => $filePath,
                        'current_hash' => $currentHash,
                        'created_at' => $signerCreatedAt,
                        'updated_at' => $otpVerifiedAt ?? $signerCreatedAt,
                    ],
                );
            }

            $document->forceFill([
                'final_hash' => $currentFinalHash,
                'status' => $status,
            ])->save();
        }
    }

    private function completedCountForIndex(int $index, int $signerCount): int
    {
        if ($index <= 3) {
            return 0;
        }

        if ($index <= 7) {
            return min(1, $signerCount);
        }

        return $signerCount;
    }

    private function statusForCounts(int $completedCount, int $signerCount): DocumentStatus
    {
        if ($completedCount === 0) {
            return DocumentStatus::Pending;
        }

        if ($completedCount < $signerCount) {
            return DocumentStatus::Partial;
        }

        return DocumentStatus::Completed;
    }

    private function makePdfBinary(string $title, array $lines): string
    {
        $pdf = new \TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name'));
        $pdf->SetAuthor('ivoolveSignature Seeder');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(16, 18, 16);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $title, 0, 1);
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', '', 11);

        foreach ($lines as $line) {
            $pdf->MultiCell(0, 7, $line, 0, 'L', false, 1);
        }

        return $pdf->Output('seed.pdf', 'S');
    }
}
