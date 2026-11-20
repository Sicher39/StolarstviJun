<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The administration edits the selection of every item with real selects, so the four catalog
        // references become columns of their own instead of staying locked inside the JSON snapshot.
        // The snapshot keeps the selection the customer sent, these columns carry what the workshop
        // quotes now - a later catalog change can not rewrite either of them by itself.
        //
        // The columns are nullable on purpose: an item stored before them existed keeps its selection
        // in the snapshot only, and an inquiry of the older one-piece shape has no items at all.
        Schema::table('inquiry_items', function (Blueprint $table): void {
            $table->foreignId('door_model_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('door_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('glass_type_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inquiry_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('door_model_id');
            $table->dropConstrainedForeignId('door_variant_id');
            $table->dropConstrainedForeignId('decor_id');
            $table->dropConstrainedForeignId('glass_type_id');
        });
    }
};
