<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('door_models', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable()->index();
            $table->text('description')->nullable();
            $table->decimal('base_price_without_vat', 12, 2)->default(0);
            $table->decimal('base_price_with_vat', 12, 2)->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('door_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('door_model_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('opening_direction')->nullable()->index();
            $table->string('opening_type')->nullable()->index();
            $table->boolean('has_glass')->default(false)->index();
            $table->boolean('sliding_possible')->default(false)->index();
            $table->decimal('price_modifier', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['door_model_id', 'code']);
        });

        Schema::create('decors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('price_modifier', 12, 2)->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('glass_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedTinyInteger('opacity')->default(100);
            $table->decimal('price_modifier', 12, 2)->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('surcharges', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('price_without_vat', 12, 2)->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('door_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('door_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->json('config')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('decor_door_model', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('decor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('door_model_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['decor_id', 'door_model_id']);
        });

        Schema::create('door_model_glass_type', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('door_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('glass_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['door_model_id', 'glass_type_id']);
        });

        Schema::create('door_model_surcharge', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('door_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('surcharge_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['door_model_id', 'surcharge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('door_model_surcharge');
        Schema::dropIfExists('door_model_glass_type');
        Schema::dropIfExists('decor_door_model');
        Schema::dropIfExists('door_templates');
        Schema::dropIfExists('surcharges');
        Schema::dropIfExists('glass_types');
        Schema::dropIfExists('decors');
        Schema::dropIfExists('door_variants');
        Schema::dropIfExists('door_models');
    }
};
