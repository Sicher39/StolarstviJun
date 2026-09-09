<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('door_model_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('door_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('glass_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->text('customer_message')->nullable();
            $table->decimal('price_without_vat', 12, 2)->default(0);
            $table->decimal('price_with_vat', 12, 2)->default(0);
            $table->json('configuration')->nullable();
            $table->json('crm_payload')->nullable();
            $table->string('status')->default('new')->index();
            $table->timestamps();
        });

        Schema::create('inquiry_surcharge', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('surcharge_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_without_vat', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['inquiry_id', 'surcharge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_surcharge');
        Schema::dropIfExists('inquiries');
    }
};
