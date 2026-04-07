<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'version_number',
        'user_id',
        'user_name',
        'otp_code',
        'otp_verified_at',
        'ip_address',
        'user_agent',
        'file_path',
        'current_hash',
    ];

    protected function casts(): array
    {
        return [
            'otp_verified_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
