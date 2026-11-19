<?php

namespace App\Http\Controllers;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Inquiry;
use App\Models\Surcharge;
use App\Services\Compliance\ConsentRecorder;
use App\Services\Inquiry\DoorPreviewImageInspector;
use App\Services\Inquiry\InquiryStatus;
use App\Services\VatCalculator;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfiguratorInquiryController extends Controller
{
    public function __invoke(Request $request, ConsentRecorder $consents, DoorPreviewImageInspector $previewInspector): RedirectResponse
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
            // The acknowledgement of the privacy policy is a real consent, not a marketing opt-in: the
            // inquiry carries personal data, so it may only be sent after the visitor confirmed they
            // read how the workshop processes it.
            'gdpr_consent' => ['accepted'],
            // The illustration of the chosen door that the browser composed right before submitting. It is
            // optional (a legacy browser may not be able to compose it) and it is never part of the stored
            // configuration: the price and the selection are always validated from the database below.
            'door_preview' => [
                'nullable',
                'file',
                'mimes:png',
                'mimetypes:image/png',
                'max:5120',
                function (string $attribute, mixed $value, Closure $fail) use ($previewInspector): void {
                    if (! $value instanceof UploadedFile) {
                        $fail('Náhled konfigurace se nepodařilo přijmout, odešlete ho prosím znovu.');

                        return;
                    }

                    $violation = $previewInspector->violation($value);

                    if ($violation !== null) {
                        $fail($violation);
                    }
                },
            ],
        ], [
            'gdpr_consent.accepted' => 'Bez potvrzení seznámení se zásadami ochrany osobních údajů poptávku odeslat nelze.',
            'door_preview.mimes' => 'Náhled konfigurace musí být obrázek ve formátu PNG.',
            'door_preview.mimetypes' => 'Náhled konfigurace musí být obrázek ve formátu PNG.',
            'door_preview.file' => 'Náhled konfigurace se nepodařilo nahrát, odešlete ho prosím znovu.',
            'door_preview.uploaded' => 'Náhled konfigurace se nepodařilo nahrát, maximum je 5 MB.',
            'door_preview.max' => 'Náhled konfigurace je příliš velký, maximum je 5 MB.',
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

        $priceWithVat = VatCalculator::applyRate($priceWithoutVat);

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
                'vat_rate' => VatCalculator::rate(),
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

        $doorPreview = $validated['door_preview'] ?? null;

        DB::transaction(function () use ($request, $consents, $validated, $doorModel, $doorVariant, $decor, $glassType, $priceWithoutVat, $priceWithVat, $configuration, $crmPayload, $surcharges, $doorPreview): void {
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
                'status' => InquiryStatus::New->value,
            ]);

            $inquiry->surcharges()->attach(
                $surcharges->mapWithKeys(fn (Surcharge $surcharge): array => [
                    $surcharge->id => [
                        'price_without_vat' => round((float) $surcharge->price_without_vat, 2),
                    ],
                ])->all(),
            );

            // The preview is stored next to the inquiry in the same transaction, so a stored inquiry never
            // loses the illustration it was sent with. It is stored as media instead of a column, because
            // the media table is already polymorphic and the file is not part of the quoted configuration.
            if ($doorPreview instanceof UploadedFile) {
                $inquiry->addMedia($doorPreview)
                    ->usingFileName('nahlad-poptavky-'.$inquiry->getKey().'.png')
                    ->toMediaCollection(Inquiry::PREVIEW_COLLECTION);
            }

            // The proof of the acknowledgement belongs to the same transaction as the inquiry: either
            // both exist or neither does, so a stored inquiry always carries its evidence.
            $consents->recordGdprAcknowledgement(
                ConsentRecorder::SOURCE_CONFIGURATOR_INQUIRY,
                [
                    'form' => 'konfigurátor dveří',
                    'inquiry_id' => $inquiry->id,
                    'door_model' => $doorModel->name,
                    'door_variant' => $doorVariant->code,
                    'decor' => $decor?->name,
                    'glass_type' => $glassType?->name,
                    'surcharges' => $surcharges->pluck('name')->all(),
                    'price_with_vat' => $priceWithVat,
                ],
                $request,
                $validated['customer_name'],
                $validated['customer_email'],
                $validated['customer_phone'] ?? null,
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
