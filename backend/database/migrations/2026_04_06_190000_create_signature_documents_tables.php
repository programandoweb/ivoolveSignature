<?php

use App\Enums\DocumentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $statuses;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->statuses = array_map(
            static fn (DocumentStatus $status): string => $status->value,
            DocumentStatus::cases(),
        );

        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('external_id');
            $table->string('app_source');
            $table->enum('status', $this->statuses)
                ->default(DocumentStatus::Pending->value);
            $table->string('final_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['external_id', 'app_source']);
        });

        Schema::create('signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('otp_code', 6)->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('file_path')->nullable();
            $table->string('current_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['document_id', 'user_id', 'otp_verified_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signatures');
        Schema::dropIfExists('documents');
    }
};
