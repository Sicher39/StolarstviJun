<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The accessory columns follow the four catalog references added earlier: the snapshot of the
        // item keeps what the customer sent, these columns carry what the workshop quotes now.
        //
        // Nullable on purpose, for the same reason as the columns before them: an item stored before
        // the frame and hardware existed has no value here, and a historical inquiry must stay
        // readable and editable. Nothing is calculated from them yet.
        Schema::table('inquiry_items', function (Blueprint $table): void {
            $table->foreignId('door_frame_id')
                ->nullable()
                ->after('glass_type_id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('hardware_id')
                ->nullable()
                ->after('door_frame_id')
                ->constrained('hardware')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inquiry_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('door_frame_id');
            $table->dropConstrainedForeignId('hardware_id');
        });
    }
};
