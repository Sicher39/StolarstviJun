<?php

namespace Tests\Feature;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Inquiry;
use App\Models\Surcharge;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ConfiguratorInquiryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_configurator_inquiry_can_be_stored_with_compatible_selection(): void
    {
        [$doorModel, $variant, $decor, $glassType, $surcharge] = $this->createCompatibleCatalogSelection();

        $response = $this->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'decor_id' => $decor->id,
            'glass_type_id' => $glassType->id,
            'surcharge_ids' => [$surcharge->id],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'customer_phone' => '+420 123 456 789',
            'customer_message' => 'Prosím o ověření konfigurace.',
        ]);

        $response
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas(Inquiry::class, [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'decor_id' => $decor->id,
            'glass_type_id' => $glassType->id,
            'customer_email' => 'jan@example.com',
            'price_without_vat' => 1550,
            'price_with_vat' => 1875.5,
            'status' => 'new',
        ]);

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();

        $this->assertDatabaseHas('inquiry_surcharge', [
            'inquiry_id' => $inquiry->id,
            'surcharge_id' => $surcharge->id,
            'price_without_vat' => 50,
        ]);
    }

    public function test_configurator_inquiry_rejects_variant_from_different_model(): void
    {
        [$doorModel] = $this->createCompatibleCatalogSelection();

        $otherModel = DoorModel::query()->create([
            'name' => 'Other test model',
            'slug' => 'other-test-model-'.uniqid(),
            'base_price_without_vat' => 2000,
            'base_price_with_vat' => 2420,
            'active' => true,
        ]);

        $otherVariant = DoorVariant::query()->create([
            'door_model_id' => $otherModel->id,
            'code' => 'OTHER',
            'width' => 800,
            'height' => 2100,
            'has_glass' => false,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);

        $response = $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $otherVariant->id,
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'surcharge_ids' => [],
        ]);

        $response
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasErrors('door_variant_id');
    }

    public function test_configurator_inquiry_rejects_glass_for_non_glass_variant(): void
    {
        [$doorModel, , , $glassType] = $this->createCompatibleCatalogSelection();

        $variantWithoutGlass = DoorVariant::query()->create([
            'door_model_id' => $doorModel->id,
            'code' => 'NO-GLASS-'.uniqid(),
            'width' => 900,
            'height' => 2100,
            'has_glass' => false,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);

        $response = $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variantWithoutGlass->id,
            'glass_type_id' => $glassType->id,
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'surcharge_ids' => [],
        ]);

        $response
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasErrors('glass_type_id');
    }

    /**
     * @return array{0: DoorModel, 1: DoorVariant, 2: Decor, 3: GlassType, 4: Surcharge}
     */
    private function createCompatibleCatalogSelection(): array
    {
        $doorModel = DoorModel::query()->create([
            'name' => 'Test model '.uniqid(),
            'slug' => 'test-model-'.uniqid(),
            'category' => 'interierove',
            'description' => 'Test model description',
            'base_price_without_vat' => 1000,
            'base_price_with_vat' => 1210,
            'active' => true,
        ]);

        $variant = DoorVariant::query()->create([
            'door_model_id' => $doorModel->id,
            'code' => 'VAR-'.uniqid(),
            'width' => 800,
            'height' => 2100,
            'opening_direction' => 'leve',
            'opening_type' => 'otocne',
            'has_glass' => true,
            'sliding_possible' => true,
            'price_modifier' => 200,
        ]);

        $decor = Decor::query()->create([
            'name' => 'Test decor '.uniqid(),
            'code' => 'DEC-'.uniqid(),
            'price_modifier' => 100,
            'active' => true,
        ]);

        $glassType = GlassType::query()->create([
            'name' => 'Test glass '.uniqid(),
            'code' => 'GLS-'.uniqid(),
            'opacity' => 70,
            'price_modifier' => 200,
            'active' => true,
        ]);

        $surcharge = Surcharge::query()->create([
            'name' => 'Test surcharge '.uniqid(),
            'code' => 'SUR-'.uniqid(),
            'price_without_vat' => 50,
            'active' => true,
        ]);

        $doorModel->decors()->attach($decor);
        $doorModel->glasses()->attach($glassType);
        $doorModel->surcharges()->attach($surcharge);

        return [$doorModel, $variant, $decor, $glassType, $surcharge];
    }
}
