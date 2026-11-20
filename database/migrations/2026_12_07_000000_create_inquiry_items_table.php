<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per configured door of an inquiry. The configurator used to describe a single door
        // in the columns of `inquiries`; those columns stay untouched, so every inquiry stored before
        // this table existed keeps working and every new inquiry still fills them with its first item.
        Schema::create('inquiry_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price_without_vat', 12, 2)->default(0);
            $table->decimal('price_without_vat', 12, 2)->default(0);
            $table->decimal('price_with_vat', 12, 2)->default(0);
            // The snapshot of the selection and the prices of this very item, frozen at submit time.
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->index(['inquiry_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_items');
    }
};
