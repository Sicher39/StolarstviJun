<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns `seo_pages` with the field names the SEO brief asks for: `key`, `seo_title`,
 * `seo_description`, `seo_keywords`, a single `og_image` column, `twitter_image`, `schema_json` and
 * `aeo_faq`.
 *
 * The values are renamed, not duplicated, so every value has exactly one column. The first
 * migration is left in its original shape on purpose: it may already have been applied, so a
 * forward fix is the only safe way to change the table.
 *
 * `twitter_image` is added here because the column belongs to the record, but it is filled by
 * App\Models\SeoPage from `og_image` and is never editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropIndex('seo_pages_page_key_unique');

        Schema::table('seo_pages', function (Blueprint $table): void {
            $table->renameColumn('page_key', 'key');
            $table->renameColumn('title', 'seo_title');
            $table->renameColumn('description', 'seo_description');
            $table->renameColumn('keywords', 'seo_keywords');
            $table->renameColumn('structured_data', 'schema_json');
            $table->renameColumn('faqs', 'aeo_faq');
        });

        Schema::table('seo_pages', function (Blueprint $table): void {
            // One column for the sharing picture: an absolute URL or a path on the public disk.
            $table->string('og_image', 2048)->nullable();
            // Mirrors og_image, so both networks always show the same picture.
            $table->string('twitter_image', 2048)->nullable();
        });

        // The external URL used to be preferred over an uploaded file, so it is kept first.
        DB::statement("UPDATE seo_pages SET og_image = COALESCE(NULLIF(og_image_url, ''), NULLIF(og_image_path, ''))");
        DB::statement('UPDATE seo_pages SET twitter_image = og_image');

        Schema::table('seo_pages', function (Blueprint $table): void {
            $table->dropColumn(['og_image_path', 'og_image_url']);
        });

        $this->createIndexes();
    }

    public function down(): void
    {
        $this->dropIndex('seo_pages_is_global_unique');
        $this->dropIndex('seo_pages_key_unique');

        Schema::table('seo_pages', function (Blueprint $table): void {
            $table->string('og_image_path', 2048)->nullable();
            $table->string('og_image_url', 2048)->nullable();
        });

        DB::statement("UPDATE seo_pages SET og_image_url = og_image WHERE og_image LIKE 'http%'");
        DB::statement("UPDATE seo_pages SET og_image_path = og_image WHERE og_image IS NOT NULL AND og_image NOT LIKE 'http%'");

        Schema::table('seo_pages', function (Blueprint $table): void {
            $table->dropColumn(['og_image', 'twitter_image']);
        });

        Schema::table('seo_pages', function (Blueprint $table): void {
            $table->renameColumn('key', 'page_key');
            $table->renameColumn('seo_title', 'title');
            $table->renameColumn('seo_description', 'description');
            $table->renameColumn('seo_keywords', 'keywords');
            $table->renameColumn('schema_json', 'structured_data');
            $table->renameColumn('aeo_faq', 'faqs');
        });

        // The shape of the first migration: one key per page, soft deleted rows ignored where the
        // driver supports a partial index.
        if ($this->partialIndexesSupported()) {
            DB::statement(
                'CREATE UNIQUE INDEX seo_pages_page_key_unique ON seo_pages (page_key) WHERE deleted_at IS NULL'
            );

            return;
        }

        Schema::table('seo_pages', function (Blueprint $table): void {
            $table->unique('page_key');
        });
    }

    private function createIndexes(): void
    {
        if ($this->partialIndexesSupported()) {
            // A soft deleted page does not keep its key reserved, and only one global record may
            // exist. Both rules need a partial index, which $table->unique() can not express.
            DB::statement(
                'CREATE UNIQUE INDEX seo_pages_key_unique ON seo_pages ('.$this->quotedKeyColumn().') WHERE deleted_at IS NULL'
            );
            DB::statement(
                'CREATE UNIQUE INDEX seo_pages_is_global_unique ON seo_pages (is_global) WHERE is_global = true'
            );

            return;
        }

        DB::statement('CREATE UNIQUE INDEX seo_pages_key_unique ON seo_pages ('.$this->quotedKeyColumn().')');

        if ($this->driver() === 'mysql' || $this->driver() === 'mariadb') {
            // MySQL has no partial indexes, so an expression index carries the same guarantee:
            // every non global row is NULL there, and NULL never collides.
            DB::statement(
                'CREATE UNIQUE INDEX seo_pages_is_global_unique ON seo_pages ((CASE WHEN is_global = 1 THEN 1 ELSE NULL END))'
            );
        }
    }

    private function dropIndex(string $index): void
    {
        if ($this->driver() === 'mysql' || $this->driver() === 'mariadb') {
            Schema::table('seo_pages', function (Blueprint $table) use ($index): void {
                $table->dropUnique($index);
            });

            return;
        }

        // PostgreSQL and SQLite keep indexes outside the table, so they can be dropped by name and
        // the statement is safe to run when the index was never created.
        DB::statement('DROP INDEX IF EXISTS '.$index);
    }

    private function quotedKeyColumn(): string
    {
        $driver = $this->driver();

        return $driver === 'mysql' || $driver === 'mariadb' ? '`key`' : '"key"';
    }

    private function partialIndexesSupported(): bool
    {
        return in_array($this->driver(), ['pgsql', 'sqlite'], true);
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }
};
