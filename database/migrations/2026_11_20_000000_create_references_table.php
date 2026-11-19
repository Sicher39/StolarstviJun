<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('references', function (Blueprint $table): void {
            $table->id();
            $table->string('heading', 120);
            $table->string('description', 180)->nullable();
            $table->string('category', 32);
            $table->string('status', 32)->default('draft');
            $table->string('placement', 32)->nullable();
            $table->json('images')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index(['status', 'placement']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('references');
    }
};
