<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_variants', function (Blueprint $table): void {
            $table->unsignedInteger('canvas_width')->default(426);
            $table->unsignedInteger('canvas_height')->default(900);
        });

        Schema::dropIfExists('door_templates');
    }

    public function down(): void
    {
        Schema::create('door_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('door_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->json('config')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('door_variants', function (Blueprint $table): void {
            $table->dropColumn(['canvas_width', 'canvas_height']);
        });
    }
};
