<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable on purpose. The column is added to a catalog that already contains models and a
        // default would relabel doors nobody has classified yet: a veneered model would silently be
        // quoted as foiled. The administration sets the type on the next edit, and a model that still
        // has none is sorted after the typed ones instead of being hidden.
        Schema::table('door_models', function (Blueprint $table): void {
            $table->string('type')->nullable()->after('category')->index();
        });
    }

    public function down(): void
    {
        Schema::table('door_models', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
