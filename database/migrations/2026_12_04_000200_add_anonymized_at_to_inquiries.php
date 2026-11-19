<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            // Filled by `php artisan gdpr:anonymize-inquiries --force`. The inquiry itself stays,
            // so the quote (configuration and prices) remains readable without personal data.
            $table->timestamp('anonymized_at')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropIndex(['anonymized_at']);
            $table->dropColumn('anonymized_at');
        });
    }
};
