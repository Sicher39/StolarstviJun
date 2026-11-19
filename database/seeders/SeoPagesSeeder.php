<?php

namespace Database\Seeders;

use App\Models\SeoPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Route;

/**
 * Creates the SEO record of the global settings and of every public page defined in config/seo.php.
 *
 * Only missing rows are created, so re-seeding never overwrites values edited in the administration
 * (the same safe behaviour as ContactSeeder and the catalog seeders). A key is seeded only when it
 * is the global record or when its route really exists, so the table never describes a page that
 * the application cannot render.
 */
class SeoPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = config('seo.pages', []);

        if (! is_array($pages)) {
            return;
        }

        foreach ($pages as $pageKey => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $key = (string) $pageKey;

            if (! $this->isSeedable($key)) {
                continue;
            }

            if ($this->recordExists($key)) {
                continue;
            }

            SeoPage::query()->create([
                ...SeoPage::attributesFromDefinition($definition),
                'key' => $key,
                'is_global' => $key === SeoPage::GLOBAL_KEY,
            ]);
        }
    }

    /**
     * The global record has no route by design, every other key must belong to a real page.
     */
    private function isSeedable(string $pageKey): bool
    {
        return $pageKey === SeoPage::GLOBAL_KEY || Route::has($pageKey);
    }

    /**
     * Soft deleted rows keep their key reserved, so they are never rebuilt automatically.
     */
    private function recordExists(string $pageKey): bool
    {
        return SeoPage::withTrashed()->where('key', $pageKey)->exists();
    }
}
