<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('page_key', 64);
            $table->string('label', 120);
            // Exactly one record describes the whole site (default image, social profiles, locality,
            // site wide structured data). Its key is the fixed `global` key, so the unique index below
            // already makes a second global record impossible.
            $table->boolean('is_global')->default(false);
            $table->string('title', 255);
            $table->string('description', 512)->nullable();
            $table->string('keywords', 512)->nullable();
            $table->string('robots', 64)->default('index,follow');
            // Canonical path of the page, for example `/dvere`. Empty means the path is derived from
            // the route name, so a page never loses its canonical when the field is left blank.
            $table->string('path', 2048)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 512)->nullable();
            $table->string('og_type', 32)->default('website');
            // File uploaded to the public disk, and an optional external image URL. Both are converted
            // to one absolute `og:image` URL by SeoPage::ogImageUrl().
            $table->string('og_image_path', 2048)->nullable();
            $table->string('og_image_url', 2048)->nullable();
            $table->json('social_profiles')->nullable();
            $table->string('locality', 120)->nullable();
            $table->string('area_served', 255)->nullable();
            $table->string('availability', 255)->nullable();
            $table->json('structured_data')->nullable();
            $table->json('faqs')->nullable();
            $table->boolean('in_sitemap')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // One page key may exist only once among the records that are still in use. Soft deleted
        // rows are ignored, so a page can be rebuilt after its record was retired. PostgreSQL is the
        // only driver here that can express this as a partial unique index; other drivers fall back
        // to a full unique index, where a soft deleted row keeps its key reserved.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX seo_pages_page_key_unique ON seo_pages (page_key) WHERE deleted_at IS NULL'
            );

            return;
        }

        Schema::table('seo_pages', function (Blueprint $table): void {
            $table->unique('page_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_pages');
    }
};
