<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hardware of the „Ostatní“ section: a handle, a fitting or any other accessory priced per
        // door. The name and the picture are what the visitor sees, the price is net only and the
        // gross price is derived from the central VAT rate.
        //
        // The table is called `hardware` because the word has no plural in the sense used here; every
        // model states the name explicitly, so no pluralisation rule can change it.
        Schema::create('hardware', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('price_without_vat', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('door_model_hardware', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('door_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hardware_id')->constrained('hardware')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['door_model_id', 'hardware_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('door_model_hardware');
        Schema::dropIfExists('hardware');
    }
};
