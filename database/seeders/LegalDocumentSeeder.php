<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Creates one published record for every document defined in `config/legal.php`.
 *
 * The seeder only fills an empty slot: an existing record is never overwritten, so text edited in
 * the administration survives re-seeding.
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys((array) config('legal.documents', [])) as $type) {
            $type = (string) $type;
            $locale = LegalDocument::DEFAULT_LOCALE;

            $exists = LegalDocument::query()
                ->where('type', $type)
                ->where('locale', $locale)
                ->exists();

            if ($exists) {
                continue;
            }

            LegalDocument::query()->create(LegalDocument::attributesFromDefinition($type, $locale));
        }
    }
}
