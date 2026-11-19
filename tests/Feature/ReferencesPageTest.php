<?php

namespace Tests\Feature;

use App\Models\Reference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReferencesPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests render the Inertia root view, so Vite must not require a production build manifest.
        $hotFile = storage_path('framework/testing/vite-hot');
        File::ensureDirectoryExists(dirname($hotFile));
        File::put($hotFile, 'http://127.0.0.1:5173');

        Vite::useHotFile($hotFile);

        // The fake disk needs its own public URL, otherwise the model cannot resolve image URLs.
        Storage::fake(Reference::IMAGES_DISK, ['url' => 'http://localhost/storage']);
    }

    public function test_references_page_serves_published_references_grouped_by_category(): void
    {
        $older = Reference::factory()
            ->published()
            ->category(Reference::CATEGORY_DOORS)
            ->images(['references/dvere-1.webp', 'references/dvere-2.webp'])
            ->create(['heading' => 'Dveře do obývacího pokoje', 'description' => 'Babice nad Svitavou', 'created_at' => now()->subDay()]);

        $newer = Reference::factory()
            ->published()
            ->category(Reference::CATEGORY_DOORS)
            ->create(['heading' => 'Vchodové dveře', 'description' => 'Zastávka u Brna', 'created_at' => now()]);

        Reference::factory()
            ->published()
            ->category(Reference::CATEGORY_KITCHEN)
            ->create(['heading' => 'Kuchyň na míru']);

        Reference::factory()
            ->draft()
            ->category(Reference::CATEGORY_DOORS)
            ->create(['heading' => 'Nepublikovaný koncept']);

        $response = $this->get(route('front.references'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page): Assert => $page
            // The frontend pages live outside Inertia's default page path, so the file check is disabled here.
            ->component('References', false)
            ->has('referencesByCategory', 4)
            ->where('referencesByCategory.dvere.0.id', $newer->id)
            ->where('referencesByCategory.dvere.0.header', 'Vchodové dveře')
            ->where('referencesByCategory.dvere.0.note', 'Zastávka u Brna')
            ->where('referencesByCategory.dvere.1.id', $older->id)
            ->where('referencesByCategory.dvere.1.images.0.alt', 'Dveře do obývacího pokoje')
            ->where('referencesByCategory.dvere.1.images.1.alt', 'Dveře do obývacího pokoje – fotografie 2')
            ->has('referencesByCategory.kuchyne', 1)
            ->has('referencesByCategory.nabytok', 0)
            ->has('referencesByCategory.ostatni', 0)
        );
    }

    public function test_references_page_image_urls_keep_the_order_saved_in_the_admin(): void
    {
        Reference::factory()
            ->published()
            ->category(Reference::CATEGORY_OTHER)
            ->images(['references/b.webp', 'references/a.webp', 'references/c.webp'])
            ->create(['heading' => 'Schodiště']);

        $this->get(route('front.references'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('References', false)
                ->where('referencesByCategory.ostatni.0.images.0.url', Storage::disk(Reference::IMAGES_DISK)->url('references/b.webp'))
                ->where('referencesByCategory.ostatni.0.images.1.url', Storage::disk(Reference::IMAGES_DISK)->url('references/a.webp'))
                ->where('referencesByCategory.ostatni.0.images.2.url', Storage::disk(Reference::IMAGES_DISK)->url('references/c.webp'))
            );
    }

    public function test_references_page_renders_all_categories_when_nothing_is_published(): void
    {
        Reference::factory()->draft()->create();

        $this->get(route('front.references'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('References', false)
                ->has('referencesByCategory', 4)
                ->has('referencesByCategory.dvere', 0)
                ->has('referencesByCategory.nabytok', 0)
                ->has('referencesByCategory.kuchyne', 0)
                ->has('referencesByCategory.ostatni', 0)
            );
    }

    public function test_doors_page_serves_only_published_references_placed_on_the_doors_page(): void
    {
        $expected = Reference::factory()
            ->published()
            ->placement(Reference::PLACEMENT_DOORS)
            ->create(['heading' => 'Dveře v rodinném domě']);

        Reference::factory()
            ->published()
            ->placement(Reference::PLACEMENT_CUSTOM_PRODUCTION)
            ->create(['heading' => 'Kuchyňská linka']);

        Reference::factory()
            ->published()
            ->placement(null)
            ->create(['heading' => 'Jen na stránce referencí']);

        Reference::factory()
            ->draft()
            ->placement(Reference::PLACEMENT_DOORS)
            ->create(['heading' => 'Rozpracované dveře']);

        $this->get(route('front.doors'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Doors', false)
                ->has('references', 1)
                ->where('references.0.id', $expected->id)
                ->where('references.0.header', 'Dveře v rodinném domě')
            );
    }

    public function test_custom_production_page_serves_only_published_references_placed_on_that_page(): void
    {
        $expected = Reference::factory()
            ->published()
            ->placement(Reference::PLACEMENT_CUSTOM_PRODUCTION)
            ->create(['heading' => 'Jídelní stůl na míru']);

        Reference::factory()
            ->published()
            ->placement(Reference::PLACEMENT_DOORS)
            ->create(['heading' => 'Vchodové dveře']);

        $this->get(route('front.customProduction'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('CustomProduction', false)
                ->has('references', 1)
                ->where('references.0.id', $expected->id)
                ->where('references.0.header', 'Jídelní stůl na míru')
            );
    }

    public function test_doors_and_custom_production_pages_render_without_any_references(): void
    {
        $this->get(route('front.doors'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Doors', false)
                ->has('references', 0)
            );

        $this->get(route('front.customProduction'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('CustomProduction', false)
                ->has('references', 0)
            );
    }

    public function test_new_references_default_to_draft(): void
    {
        $reference = Reference::factory()->create();

        $this->assertSame(Reference::STATUS_DRAFT, $reference->status);
        $this->assertNull($reference->placement);
    }

    public function test_removed_images_are_deleted_from_the_disk_only_after_a_successful_save(): void
    {
        $disk = Storage::disk(Reference::IMAGES_DISK);
        $disk->put('references/keep.webp', 'keep');
        $disk->put('references/remove.webp', 'remove');

        $reference = Reference::factory()
            ->images(['references/keep.webp', 'references/remove.webp'])
            ->create();

        $reference->update(['images' => ['references/keep.webp']]);

        $disk->assertExists('references/keep.webp');
        $disk->assertMissing('references/remove.webp');
    }

    public function test_paths_outside_the_reference_directory_are_never_deleted(): void
    {
        $disk = Storage::disk(Reference::IMAGES_DISK);
        $disk->put('other/foreign.webp', 'foreign');

        $reference = Reference::factory()
            ->images(['other/foreign.webp'])
            ->create();

        $reference->delete();

        $disk->assertExists('other/foreign.webp');
    }

    public function test_deleting_a_reference_deletes_its_images(): void
    {
        $disk = Storage::disk(Reference::IMAGES_DISK);
        $disk->put('references/gallery.webp', 'gallery');

        $reference = Reference::factory()
            ->images(['references/gallery.webp'])
            ->create();

        $reference->delete();

        $disk->assertMissing('references/gallery.webp');
    }
}
