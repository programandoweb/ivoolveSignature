<?php

namespace App\Services;

use App\Exceptions\IntegrityViolationException;
use App\Models\Signature;
use Illuminate\Filesystem\FilesystemManager;

class IntegrityService
{
    public function __construct(
        private readonly FilesystemManager $filesystem,
    ) {
    }

    public function calculateFileHash(string $absolutePath): string
    {
        $hash = hash_file('sha256', $absolutePath);

        if ($hash === false) {
            throw new IntegrityViolationException('Unable to calculate the SHA-256 hash for the provided file.');
        }

        return $hash;
    }

    public function calculateStoredFileHash(string $relativePath): string
    {
        return $this->calculateFileHash($this->disk()->path($relativePath));
    }

    public function assertSignatureIntegrity(Signature $signature): void
    {
        if ($signature->file_path === null || $signature->current_hash === null) {
            throw new IntegrityViolationException('The requested version is missing its physical file or stored hash.');
        }

        $disk = $this->disk();

        if (! $disk->exists($signature->file_path)) {
            throw new IntegrityViolationException('The signed PDF could not be found in private storage.');
        }

        $currentHash = $this->calculateStoredFileHash($signature->file_path);

        if (! hash_equals($signature->current_hash, $currentHash)) {
            throw new IntegrityViolationException('The document integrity check failed. The physical file hash does not match the stored version hash.');
        }
    }

    private function disk()
    {
        return $this->filesystem->disk(config('signature.storage_disk'));
    }
}
