<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Účely zpracování osobních údajů
|--------------------------------------------------------------------------
|
| One row per purpose the workshop really processes. The privacy policy publishes the active ones,
| so the administration is the single place where the scope of processing is described.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_processing_purposes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Stable key of a seeded purpose, so a re-run of the seeder updates the record in place.
            $table->string('context')->nullable();
            $table->text('description')->nullable();
            $table->text('personal_data_categories')->nullable();
            $table->string('legal_basis')->nullable();
            $table->string('retention_period')->nullable();
            $table->text('recipients')->nullable();
            $table->text('third_country_transfer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();

            $table->index(['context', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_processing_purposes');
    }
};
