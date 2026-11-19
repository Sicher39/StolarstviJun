<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->index();
            $table->string('locale')->default('cs');
            $table->string('title');
            $table->text('perex')->nullable();
            $table->longText('content');
            $table->string('version')->default('1.0');
            $table->date('effective_from')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // One document per type and locale: the record is edited in place, never duplicated.
            $table->unique(['type', 'locale']);
        });

        Schema::create('legal_document_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_document_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('title');
            $table->text('perex')->nullable();
            $table->longText('content');
            $table->date('effective_from')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_document_revisions');
        Schema::dropIfExists('legal_documents');
    }
};
