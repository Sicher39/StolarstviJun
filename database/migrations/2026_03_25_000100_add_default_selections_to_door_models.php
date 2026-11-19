<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_models', function (Blueprint $table): void {
            $table->foreignId('default_variant_id')
                ->nullable()
                ->after('base_price_with_vat')
                ->constrained('door_variants')
                ->nullOnDelete();

            $table->foreignId('default_decor_id')
                ->nullable()
                ->after('default_variant_id')
                ->constrained('decors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('door_models', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_variant_id');
            $table->dropConstrainedForeignId('default_decor_id');
        });
    }
};
