<?php

namespace Tests\Feature;

use App\Models\Signature;
use App\Services\PdfProcessorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class SignatureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $signatureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signatureRoot = storage_path('framework/testing/disks/signatures');

        config()->set('signature.api_key', 'testing-api-key');
        config()->set('filesystems.disks.signatures', [
            'driver' => 'local',
            'root' => $this->signatureRoot,
            'throw' => false,
            'report' => false,
        ]);

        File::deleteDirectory($this->signatureRoot);
        File::ensureDirectoryExists($this->signatureRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->signatureRoot);

        parent::tearDown();
    }

    public function test_initiate_requires_an_api_key(): void
    {
        $response = $this->postJson('/api/v1/signatures/initiate', []);

        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Invalid API key.',
            ]);
    }

    public function test_it_processes_the_full_signature_workflow(): void
    {
        $pdf = UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4 test original document');

        $initiateResponse = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
            'Accept' => 'application/json',
        ])->post('/api/v1/signatures/initiate', [
            'external_id' => 'VAC-2026-0001',
            'app_source' => 'ivoolve-flow',
            'pdf' => $pdf,
            'signers' => [
                [
                    'user_id' => '10101010',
                    'user_name' => 'Ana Lopez',
                ],
                [
                    'user_id' => '20202020',
                    'user_name' => 'Luis Perez',
                ],
            ],
        ]);

        $initiateResponse->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $documentId = $initiateResponse->json('data.document_id');
        $signatureRows = Signature::query()
            ->where('document_id', $documentId)
            ->where('version_number', '>', 0)
            ->orderBy('version_number')
            ->get();

        $this->mock(PdfProcessorService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('stamp')
                ->twice()
                ->andReturnUsing(function (string $sourcePath, string $destinationPath, array $stampData): void {
                    File::ensureDirectoryExists(dirname($destinationPath));
                    File::put($destinationPath, '%PDF-1.4 signed '.$stampData['user_id']);
                });
        });

        $firstVerifyResponse = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
            'User-Agent' => 'PHPUnit',
        ])->postJson('/api/v1/signatures/verify', [
            'document_id' => $documentId,
            'user_id' => '10101010',
            'otp_code' => $signatureRows[0]->otp_code,
        ]);

        $firstVerifyResponse->assertOk()
            ->assertJsonPath('data.status', 'partial');

        $secondVerifyResponse = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
            'User-Agent' => 'PHPUnit',
        ])->postJson('/api/v1/signatures/verify', [
            'document_id' => $documentId,
            'user_id' => '20202020',
            'otp_code' => $signatureRows[1]->otp_code,
        ]);

        $secondVerifyResponse->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.validation_url', url("/api/v1/validate/{$documentId}"));

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'status' => 'completed',
        ]);
    }

    public function test_it_sends_the_otp_over_whatsapp_when_phone_and_endpoint_are_present(): void
    {
        Http::fake([
            'https://app2.delicetiendavirtual.com/whatsapp/delice_bot/send' => Http::response([
                'success' => true,
            ], 200),
        ]);

        config()->set('signature.whatsapp_endpoint', 'https://app2.delicetiendavirtual.com/whatsapp/delice_bot/send');

        $pdf = UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4 test original document');

        $response = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
            'Accept' => 'application/json',
        ])->post('/api/v1/signatures/initiate', [
            'external_id' => 'OTP-2026-0001',
            'app_source' => 'ivoolve-flow',
            'pdf' => $pdf,
            'signers' => [
                [
                    'user_id' => '30303030',
                    'user_name' => 'Maria Ruiz',
                    'phone_number' => '573001234567',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.signatures.0.phone_number', '573001234567');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://app2.delicetiendavirtual.com/whatsapp/delice_bot/send'
                && $request['to'] === '573001234567'
                && str_contains((string) $request['message'], 'Maria Ruiz')
                && str_contains((string) $request['message'], 'OTP-2026-0001');
        });
    }
}
