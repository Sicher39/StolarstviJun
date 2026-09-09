<?php

namespace App\Http\Controllers;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Inquiry;
use App\Models\Surcharge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfiguratorInquiryController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'door_model_id' => ['required', 'exists:door_models,id'],
            'door_variant_id' => ['required', 'exists:door_variants,id'],
            'decor_id' => ['nullable', 'exists:decors,id'],
            'glass_type_id' => ['nullable', 'exists:glass_types,id'],
            'surcharge_ids' => ['array'],
            'surcharge_ids.*' => ['integer', 'distinct', 'exists:surcharges,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'customer_message' => ['nullable', 'string', 'max:5000'],
        ]);

        $doorModel = DoorModel::query()
            ->whereKey($validated['door_model_id'])
            ->where('active', true)
            ->first();

        if (! $doorModel) {
            throw ValidationException::withMessages([
                'door_model_id' => 'Vybraný model není dostupný.',
            ]);
        }

        $doorVariant = DoorVariant::query()
            ->whereKey($validated['door_variant_id'])
            ->where('door_model_id', $doorModel->id)
            ->first();

        if (! $doorVariant) {
            throw ValidationException::withMessages([
                'door_variant_id' => 'Vybraná varianta nepatří k zvolenému modelu.',
            ]);
        }

        $decor = $this->resolveDecor($doorModel, $validated['decor_id'] ?? null);
        $glassType = $this->resolveGlassType($doorModel, $doorVariant, $validated['glass_type_id'] ?? null);
        $surcharges = $this->resolveSurcharges($doorModel, $validated['surcharge_ids'] ?? []);

        $priceWithoutVat = (float) $doorModel->base_price_without_vat
            + (float) $doorVariant->price_modifier
            + (float) ($decor?->price_modifier ?? 0)
            + (float) ($glassType?->price_modifier ?? 0)
            + $surcharges->sum(fn (Surcharge $surcharge): float => (float) $surcharge->price_without_vat);

        $priceWithVat = round($priceWithoutVat * 1.21, 2);

        $configuration = [
            'door_model' => [
                'id' => $doorModel->id,
                'name' => $doorModel->name,
                'slug' => $doorModel->slug,
                'category' => $doorModel->category,
                'base_price_without_vat' => (float) $doorModel->base_price_without_vat,
            ],
            'door_variant' => [
                'id' => $doorVariant->id,
                'code' => $doorVariant->code,
                'width' => $doorVariant->width,
                'height' => $doorVariant->height,
                'opening_direction' => $doorVariant->opening_direction,
                'opening_type' => $doorVariant->opening_type,
                'has_glass' => $doorVariant->has_glass,
                'sliding_possible' => $doorVariant->sliding_possible,
                'price_modifier' => (float) $doorVariant->price_modifier,
            ],
            'decor' => $decor ? [
                'id' => $decor->id,
                'name' => $decor->name,
                'code' => $decor->code,
                'price_modifier' => (float) $decor->price_modifier,
            ] : null,
            'glass_type' => $glassType ? [
                'id' => $glassType->id,
                'name' => $glassType->name,
                'code' => $glassType->code,
                'opacity' => $glassType->opacity,
                'price_modifier' => (float) $glassType->price_modifier,
            ] : null,
            'surcharges' => $surcharges
                ->map(fn (Surcharge $surcharge): array => [
                    'id' => $surcharge->id,
                    'name' => $surcharge->name,
                    'code' => $surcharge->code,
                    'price_without_vat' => (float) $surcharge->price_without_vat,
                ])
                ->values()
                ->all(),
            'totals' => [
                'price_without_vat' => round($priceWithoutVat, 2),
                'price_with_vat' => $priceWithVat,
                'vat_rate' => 21,
            ],
        ];

        $crmPayload = [
            'customer' => [
                'name' => $validated['customer_name'],
                'email' => $validated['customer_email'],
                'phone' => $validated['customer_phone'] ?? null,
                'message' => $validated['customer_message'] ?? null,
            ],
            'configuration' => $configuration,
            'submitted_from' => 'front.configurator',
        ];

        DB::transaction(function () use ($validated, $doorModel, $doorVariant, $decor, $glassType, $priceWithoutVat, $priceWithVat, $configuration, $crmPayload, $surcharges): void {
            $inquiry = Inquiry::query()->create([
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $doorVariant->id,
                'decor_id' => $decor?->id,
                'glass_type_id' => $glassType?->id,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_message' => $validated['customer_message'] ?? null,
                'price_without_vat' => round($priceWithoutVat, 2),
                'price_with_vat' => $priceWithVat,
                'configuration' => $configuration,
                'crm_payload' => $crmPayload,
                'status' => 'new',
            ]);

            $inquiry->surcharges()->attach(
                $surcharges->mapWithKeys(fn (Surcharge $surcharge): array => [
                    $surcharge->id => [
                        'price_without_vat' => round((float) $surcharge->price_without_vat, 2),
                    ],
                ])->all(),
            );
        });

        return redirect()
            ->route('front.configurator')
            ->with('success', 'Poptávka byla úspěšně odeslána. Ozveme se vám co nejdříve.');
    }

    private function resolveDecor(DoorModel $doorModel, ?int $decorId): ?Decor
    {
        if (! $decorId) {
            return null;
        }

        $decor = Decor::query()
            ->whereKey($decorId)
            ->where('active', true)
            ->first();

        if (! $decor || ! $doorModel->decors()->whereKey($decor->id)->exists()) {
            throw ValidationException::withMessages([
                'decor_id' => 'Vybraný dekor není dostupný pro zvolený model.',
            ]);
        }

        return $decor;
    }

    private function resolveGlassType(DoorModel $doorModel, DoorVariant $doorVariant, ?int $glassTypeId): ?GlassType
    {
        if (! $doorVariant->has_glass) {
            if ($glassTypeId) {
                throw ValidationException::withMessages([
                    'glass_type_id' => 'Vybraná varianta neumožňuje sklo.',
                ]);
            }

            return null;
        }

        if (! $glassTypeId) {
            return null;
        }

        $glassType = GlassType::query()
            ->whereKey($glassTypeId)
            ->where('active', true)
            ->first();

        if (! $glassType || ! $doorModel->glasses()->whereKey($glassType->id)->exists()) {
            throw ValidationException::withMessages([
                'glass_type_id' => 'Vybrané sklo není dostupné pro zvolený model.',
            ]);
        }

        return $glassType;
    }

    /**
     * @param  array<int, int>  $surchargeIds
     * @return Collection<int, Surcharge>
     */
    private function resolveSurcharges(DoorModel $doorModel, array $surchargeIds): Collection
    {
        if ($surchargeIds === []) {
            return collect();
        }

        $orderedSurchargeIds = array_values($surchargeIds);

        $surcharges = Surcharge::query()
            ->whereIn('id', $orderedSurchargeIds)
            ->where('active', true)
            ->get();

        if ($surcharges->count() !== count($orderedSurchargeIds)) {
            throw ValidationException::withMessages([
                'surcharge_ids' => 'Některé příplatky nejsou dostupné.',
            ]);
        }

        $allowedSurchargeIds = $doorModel->surcharges()
            ->whereIn('surcharges.id', $orderedSurchargeIds)
            ->pluck('surcharges.id');

        if ($allowedSurchargeIds->count() !== count($orderedSurchargeIds)) {
            throw ValidationException::withMessages([
                'surcharge_ids' => 'Vybrané příplatky nejsou dostupné pro zvolený model.',
            ]);
        }

        return $surcharges
            ->sortBy(fn (Surcharge $surcharge): int => array_search($surcharge->id, $orderedSurchargeIds, true) ?: 0)
            ->values();
    }
}
