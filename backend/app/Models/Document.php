<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'external_id',
        'app_source',
        'status',
        'final_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
        ];
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class)->orderBy('version_number');
    }

    public function completedSignatures(): HasMany
    {
        return $this->hasMany(Signature::class)
            ->where('version_number', '>', 0)
            ->whereNotNull('otp_verified_at')
            ->orderBy('version_number');
    }

    public function materializedVersions(): HasMany
    {
        return $this->hasMany(Signature::class)
            ->whereNotNull('file_path')
            ->orderBy('version_number');
    }
}
