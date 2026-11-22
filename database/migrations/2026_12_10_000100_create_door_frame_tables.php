<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A frame type of the „Ostatní“ section of the configurator: bez zárubně, bezfalcová,
        // obložková, obrácená. Only the net price is stored, the gross price is always derived from
        // the central VAT rate, so a rate change can never leave a stale gross price in the catalog.
        Schema::create('door_frames', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('price_without_vat', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        // Eligibility is decided on the accessory and shared with the door model, because a frame
        // type is offered per model: the same frame can belong to any number of models.
        Schema::create('door_frame_door_model', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('door_frame_id')->constrained()->cascadeOnDelete();
            $table->foreignId('door_model_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['door_frame_id', 'door_model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('door_frame_door_model');
        Schema::dropIfExists('door_frames');
    }
};
