<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('glass_types', function (Blueprint $table): void {
            // The configurator drew the room behind every glass blurred, with the clear glass as the only
            // exception. The flag carries that decision into the catalog, so an administrator can set it per
            // glass instead of the browser guessing from the code.
            $table->boolean('blur_interior')->default(true)->after('opacity');
        });

        // The clear glass kept a sharp interior before the flag existed, so the published catalog keeps
        // looking exactly the way it did.
        DB::table('glass_types')
            ->where('code', 'cire-sklo')
            ->update(['blur_interior' => false]);
    }

    public function down(): void
    {
        Schema::table('glass_types', function (Blueprint $table): void {
            $table->dropColumn('blur_interior');
        });
    }
};
