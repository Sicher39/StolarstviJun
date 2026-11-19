<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Záznamy souhlasů
|--------------------------------------------------------------------------
|
| Evidence of what a visitor agreed to: the cookie categories of the cookie dialog and the GDPR
| acknowledgement of an inquiry. The row keeps the version of the document that was agreed with, a
| snapshot of the acknowledged content and the subject details of the form, because the record has
| to prove the state of the site at the moment of the consent.
|
| IP address and user agent are stored only as salted hashes: they identify a repeated submission
| without keeping the raw value.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('consent_uuid')->unique();
            $table->string('type');
            $table->string('version')->nullable();
            $table->json('preferences')->nullable();
            $table->string('source')->nullable();
            $table->string('purpose')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('subject_email')->nullable();
            $table->string('subject_phone')->nullable();
            $table->string('channel')->nullable();
            $table->string('ip_hash')->nullable();
            $table->string('user_agent_hash')->nullable();
            $table->foreignId('legal_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('data_processing_purpose_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
