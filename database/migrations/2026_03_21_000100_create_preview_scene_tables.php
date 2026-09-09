<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_scenes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedInteger('canvas_width')->default(426);
            $table->unsignedInteger('canvas_height')->default(900);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('preview_scene_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('preview_scene_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('name');
            $table->string('code');
            $table->string('color')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['preview_scene_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_scene_materials');
        Schema::dropIfExists('preview_scenes');
    }
};
