<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The picture of a frame is an optional illustration of its selector card in the configurator.
        // The catalog can therefore keep the file uploaded and still hide it from the public site
        // without deleting anything, which is what this flag decides.
        Schema::table('door_frames', function (Blueprint $table): void {
            $table->boolean('show_image')->default(true)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('door_frames', function (Blueprint $table): void {
            $table->dropColumn('show_image');
        });
    }
};
