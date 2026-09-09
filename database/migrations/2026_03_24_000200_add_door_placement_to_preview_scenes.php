<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preview_scenes', function (Blueprint $table): void {
            $table->unsignedInteger('door_x')->default(487);
            $table->unsignedInteger('door_y')->default(100);
            $table->unsignedInteger('door_width')->default(426);
            $table->unsignedInteger('door_height')->default(900);
        });

        DB::table('preview_scenes')
            ->where('code', 'modern-light-room')
            ->where('canvas_width', 426)
            ->where('canvas_height', 900)
            ->update([
                'canvas_width' => 1400,
                'canvas_height' => 1100,
            ]);
    }

    public function down(): void
    {
        Schema::table('preview_scenes', function (Blueprint $table): void {
            $table->dropColumn(['door_x', 'door_y', 'door_width', 'door_height']);
        });
    }
};
