<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Cookie nastavení
|--------------------------------------------------------------------------
|
| A single editable record with the texts of the cookie dialog. The column defaults are only a
| fallback: the seeded record (Database\Seeders\CookieSettingSeeder) and the administration own the
| published wording.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(true);
            // Bumping the version invalidates every stored consent and asks the visitor again.
            $table->string('version')->default('1.0');
            $table->string('banner_title')->nullable();
            $table->text('banner_description')->nullable();
            $table->string('accept_all_label')->default('Povolit vše');
            $table->string('reject_all_label')->default('Odmítnout vše');
            $table->string('customize_label')->default('Nastavit cookies');
            $table->string('save_preferences_label')->default('Uložit nastavení');
            $table->string('necessary_title')->nullable();
            $table->text('necessary_description')->nullable();
            $table->string('analytics_title')->nullable();
            $table->text('analytics_description')->nullable();
            $table->string('marketing_title')->nullable();
            $table->text('marketing_description')->nullable();
            $table->string('preferences_title')->nullable();
            $table->text('preferences_description')->nullable();
            $table->string('footer_link_label')->default('Nastavení cookies');
            $table->string('privacy_policy_url')->nullable();
            $table->string('cookie_policy_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_settings');
    }
};
