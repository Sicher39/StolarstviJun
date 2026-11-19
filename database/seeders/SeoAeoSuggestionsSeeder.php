<?php

namespace Database\Seeders;

use App\Models\SeoPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Enriches the SEO records with AEO suggestions (long tail keywords and the questions customers
 * really ask, which search engines and AI answers can quote).
 *
 * Every question and answer is derived from the copy that is already published on the page, so the
 * suggestion never introduces a claim the website does not make. The seeder fills only what is
 * still empty (keywords are merged, not replaced), which keeps it idempotent and safe to run after
 * the copy was edited in the administration.
 *
 * It also works on a database that was never seeded: a record missing for a suggested page is
 * created from config/seo.php, so the suggestions do not depend on SeoPagesSeeder having run.
 *
 * The brief fields of the record (`aeo_summary`, `aeo_search_intent`, `aeo_entities`) are not part of
 * a suggestion: a record created here takes them from config/seo.php and a second run never
 * overwrites a value an editor wrote.
 *
 * The seeder is deliberately not registered in DatabaseSeeder: it is a standalone enrichment that is
 * run on demand (`php artisan db:seed --class=SeoAeoSuggestionsSeeder`), so a fresh database is never
 * filled with suggested questions automatically.
 */
class SeoAeoSuggestionsSeeder extends Seeder
{
    /**
     * @var array<string, array{keywords?: string, faqs?: list<array{question: string, answer: string}>}>
     */
    private const SUGGESTIONS = [
        'front.index' => [
            'keywords' => 'stolařství Brno, dveře na míru, zakázková výroba ze dřeva, schody a zábradlí na míru, kuchyně na míru Brno',
            'faqs' => [
                [
                    'question' => 'Vyrábíte dveře sériově, nebo na zakázku?',
                    'answer' => 'Nevyrábíme sériově. Každé dveře vznikají na zakázku podle prostoru, stylu i přání zákazníka.',
                ],
                [
                    'question' => 'Jaké materiály používáte?',
                    'answer' => 'Pracujeme s kvalitním masivním dřevem a ověřenými materiály, jejichž vlastnosti známe z praxe. Volbu dřeva i dalších komponent přizpůsobujeme konkrétnímu použití a zatížení výrobku.',
                ],
                [
                    'question' => 'Co znamená osobní přístup od návrhu po montáž?',
                    'answer' => 'Se zákazníkem komunikujeme přímo po celou dobu realizace. Od prvního návrhu až po montáž řešíme zakázku osobně.',
                ],
                [
                    'question' => 'Jak zjistím orientační cenu dveří?',
                    'answer' => 'V konfigurátoru si zvolíte materiál, design i kování a okamžitě uvidíte výslednou podobu dveří i orientační cenu s DPH.',
                ],
            ],
        ],
        'front.doors' => [
            'keywords' => 'vchodové dveře na míru, interiérové dveře z masivu, bezfalcové dveře, posuvné dveře, dveře Zastávka u Brna',
            'faqs' => [
                [
                    'question' => 'Jaký je rozdíl mezi falcovými a bezfalcovými dveřmi?',
                    'answer' => 'Falcové dveře mají po obvodu křídla zámkový profil, který překrývá rám. Bezfalcové dveře jsou s rovinou stěny, působí čistším dojmem a hodí se do moderních interiérů.',
                ],
                [
                    'question' => 'Vyrábíte dveře v atypických rozměrech?',
                    'answer' => 'Ano. Zakázková výroba je naše specialita, takže atypické rozměry, specifický design i nestandardní řešení jsou běžnou součástí zakázky.',
                ],
                [
                    'question' => 'Co řešíte u vchodových dveří?',
                    'answer' => 'U vchodových dveří klademe důraz na pevnou konstrukci, tepelnou a zvukovou izolaci, kvalitní kování a zámky i odolné povrchové úpravy.',
                ],
                [
                    'question' => 'Z čeho si u interiérových dveří vybírám?',
                    'answer' => 'Z masivního dřeva nebo dýhy, různých povrchových úprav, bezfalcového i falcového provedení a posuvného i klasického otevírání.',
                ],
            ],
        ],
        'front.configurator' => [
            'keywords' => 'konfigurátor dveří online, orientační cena dveří s DPH, návrh dveří, dveře na míru online',
            'faqs' => [
                [
                    'question' => 'Je cena z konfigurátoru závazná?',
                    'answer' => 'Ne. Konfigurátor zobrazuje orientační cenu s DPH, závaznou nabídku připravujeme podle konkrétní zakázky.',
                ],
                [
                    'question' => 'Co si v konfigurátoru nastavím?',
                    'answer' => 'Materiál, dekor, sklo i kování. Výslednou podobu dveří a orientační cenu vidíte okamžitě.',
                ],
            ],
        ],
        'front.customProduction' => [
            'keywords' => 'kuchyně na míru Brno, vestavěné skříně na míru, schody a zábradlí na míru, dřevěné obložení stěn, atypický nábytek',
            'faqs' => [
                [
                    'question' => 'Co všechno vyrobíte ze dřeva?',
                    'answer' => 'Kuchyně na míru, vestavěné i samostatně stojící skříně, jídelní a pracovní stoly, knihovny, komody, koupelnový a ložnicový nábytek, schodiště a zábradlí.',
                ],
                [
                    'question' => 'Řešíte kromě vzhledu i technické detaily?',
                    'answer' => 'Ano. Důraz klademe na detaily a technické návaznosti – spoje, kotvení, povrchové úpravy, kování i montážní řešení.',
                ],
                [
                    'question' => 'Jaká schodiště vyrábíte?',
                    'answer' => 'Přímá i točitá schodiště, s dřevěným nebo kombinovaným zábradlím, pro novostavby i rekonstrukce.',
                ],
            ],
        ],
        'front.references' => [
            'keywords' => 'realizace dveří, kuchyně na míru fotogalerie, schody realizace, reference stolařství',
            'faqs' => [
                [
                    'question' => 'Jaké realizace najdu v galerii?',
                    'answer' => 'Realizace jsou rozdělené na dveře, nábytek, kuchyně a ostatní zakázky.',
                ],
                [
                    'question' => 'Jsou fotografie z vašich zakázek?',
                    'answer' => 'Ano, v galerii jsou dokončené zakázky, které vydrží roky, ne sezóny.',
                ],
            ],
        ],
        'front.contact' => [
            'keywords' => 'stolařství Zastávka u Brna, stolařská dílna u Brna, kontakt na stolaře',
            'faqs' => [
                [
                    'question' => 'Jak vás nejrychleji kontaktuji?',
                    'answer' => 'Zavolejte na +420 777 123 123 nebo napište na info@drevovyroba-jun.cz. Kontaktní osobou je Miroslav Jun.',
                ],
                [
                    'question' => 'Kde vás najdu?',
                    'answer' => 'Stará Osada 540, 664 84 Zastávka u Brna. Odkazy do Google Maps, Waze i Seznam Map najdete přímo na stránce Kontakt.',
                ],
                [
                    'question' => 'Jaké jsou vaše firemní údaje?',
                    'answer' => 'Stolařství Jun a synové s.r.o., IČ 28288505, DIČ CZ28288505, datová schránka 2bc4vfu. Zapsaná pod značkou C 58837/KSBR Krajským soudem v Brně.',
                ],
            ],
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('seo_pages')) {
            return;
        }

        $pages = config('seo.pages', []);
        $pages = is_array($pages) ? $pages : [];

        foreach (self::SUGGESTIONS as $pageKey => $suggestion) {
            $key = (string) $pageKey;

            // A suggestion never describes a page that the application cannot render.
            if (! Route::has($key)) {
                continue;
            }

            $seoPage = SeoPage::withTrashed()->where('key', $key)->first();

            if (! $seoPage instanceof SeoPage) {
                $definition = $pages[$key] ?? [];

                $seoPage = SeoPage::query()->create([
                    ...SeoPage::attributesFromDefinition(is_array($definition) ? $definition : []),
                    'key' => $key,
                ]);
            }

            $attributes = $this->missingValues($seoPage, $suggestion);

            if ($attributes === []) {
                continue;
            }

            $seoPage->fill($attributes);

            if ($seoPage->isDirty()) {
                $seoPage->save();
            }
        }
    }

    /**
     * @param  array{keywords?: string, faqs?: list<array{question: string, answer: string}>}  $suggestion
     * @return array<string, mixed>
     */
    private function missingValues(SeoPage $seoPage, array $suggestion): array
    {
        $attributes = [];

        $keywords = $this->mergedKeywords($seoPage->seo_keywords, $suggestion['keywords'] ?? null);

        if ($keywords !== (string) $seoPage->seo_keywords) {
            $attributes['seo_keywords'] = $keywords;
        }

        $faqs = $suggestion['faqs'] ?? null;

        if (is_array($faqs) && $faqs !== [] && $seoPage->faqItems() === []) {
            $attributes['aeo_faq'] = $faqs;
        }

        return $attributes;
    }

    /**
     * Appends the suggested terms that are not present yet, so a second run is a no-op and the
     * keywords written in the administration always stay first.
     */
    private function mergedKeywords(?string $current, mixed $suggestion): string
    {
        $terms = [];

        foreach ([$current, is_string($suggestion) ? $suggestion : null] as $source) {
            foreach (explode(',', (string) $source) as $term) {
                $term = trim($term);

                if ($term === '' || $this->containsTerm($terms, $term)) {
                    continue;
                }

                $terms[] = $term;
            }
        }

        // The column is a varchar(512), so terms that would overflow it are dropped instead of
        // failing the write.
        $merged = '';

        foreach ($terms as $term) {
            $candidate = $merged === '' ? $term : $merged.', '.$term;

            if (mb_strlen($candidate) > SeoPage::MAX_KEYWORDS_LENGTH) {
                continue;
            }

            $merged = $candidate;
        }

        return $merged;
    }

    /**
     * @param  list<string>  $terms
     */
    private function containsTerm(array $terms, string $term): bool
    {
        foreach ($terms as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($term)) {
                return true;
            }
        }

        return false;
    }
}
