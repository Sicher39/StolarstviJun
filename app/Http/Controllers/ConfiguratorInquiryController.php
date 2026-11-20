<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Surcharge;
use App\Services\Compliance\ConsentRecorder;
use App\Services\Inquiry\DoorPreviewImageInspector;
use App\Services\Inquiry\InquiryItemBuilder;
use App\Services\Inquiry\InquiryStatus;
use App\Services\VatCalculator;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Stores one configurator inquiry.
 *
 * A visitor may configure several doors in one request, so the payload carries an `items` list: every
 * entry is one separately configured door with its own name, quantity and selection of ids. The older
 * flat payload (`door_model_id`, `door_variant_id`, ...) is still accepted and becomes a single item,
 * which keeps the documented one-piece flow and every stored inquiry readable.
 *
 * Nothing about the price comes from the browser. Each item is resolved and priced by InquiryItemBuilder
 * from the database, the item prices are summed into the recap of the inquiry, and the PNG that the
 * browser composed is stored as an illustration only.
 */
class ConfiguratorInquiryController extends Controller
{
    /**
     * Messages of the composed PNG, keyed by the validation rule. They are registered twice below,
     * because the upload is either a field of the legacy payload or a field of one item.
     */
    private const PREVIEW_MESSAGES = [
        'mimes' => 'Náhled konfigurace musí být obrázek ve formátu PNG.',
        'mimetypes' => 'Náhled konfigurace musí být obrázek ve formátu PNG.',
        'file' => 'Náhled konfigurace se nepodařilo nahrát, odešlete ho prosím znovu.',
        'uploaded' => 'Náhled konfigurace se nepodařilo nahrát, maximum je 5 MB.',
        'max' => 'Náhled konfigurace je příliš velký, maximum je 5 MB.',
    ];

    public function __invoke(
        Request $request,
        ConsentRecorder $consents,
        DoorPreviewImageInspector $previewInspector,
        InquiryItemBuilder $itemBuilder,
    ): RedirectResponse {
        $usesItemPayload = is_array($request->input('items'));

        $validated = $request->validate(
            $this->rules($previewInspector, $usesItemPayload),
            $this->messages(),
        );

        $selections = $usesItemPayload
            ? $validated['items']
            : [$this->legacySelection($validated)];

        $positions = [];
        $sequence = 0;

        foreach ($selections as $index => $selection) {
            // The original index of the item is kept in the attribute prefix, so a validation error of one
            // item points at the field the browser sent.
            $positions[] = $itemBuilder->build(
                $selection,
                $usesItemPayload ? 'items.'.$index.'.' : '',
            ) + [
                'position' => $sequence++,
                'preview' => $usesItemPayload ? ($selection['door_preview'] ?? null) : null,
            ];
        }

        $summary = $this->summary($positions);
        $itemSnapshots = array_map(
            fn (array $position): array => $position['attributes']['configuration'],
            $positions,
        );

        $configuration = [
            'items' => $itemSnapshots,
            'summary' => $summary,
            // The single door shape stays on the top level, so an older reader of the snapshot (and the
            // editable selects of the administration) keeps seeing what it always saw. For a one piece
            // request both views describe the same door.
            'door_model' => $itemSnapshots[0]['door_model'],
            'door_variant' => $itemSnapshots[0]['door_variant'],
            'decor' => $itemSnapshots[0]['decor'],
            'glass_type' => $itemSnapshots[0]['glass_type'],
            'surcharges' => $itemSnapshots[0]['surcharges'],
            'totals' => [
                'price_without_vat' => $summary['price_without_vat'],
                'price_with_vat' => $summary['price_with_vat'],
                'vat_rate' => $summary['vat_rate'],
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

        $legacyPreview = $usesItemPayload ? null : ($validated['door_preview'] ?? null);

        DB::transaction(function () use ($request, $consents, $validated, $positions, $summary, $configuration, $crmPayload, $legacyPreview): void {
            $firstPosition = $positions[0];

            $inquiry = Inquiry::query()->create([
                'door_model_id' => $firstPosition['door_model']->id,
                'door_variant_id' => $firstPosition['door_variant']->id,
                'decor_id' => $firstPosition['decor']?->id,
                'glass_type_id' => $firstPosition['glass_type']?->id,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_message' => $validated['customer_message'] ?? null,
                'price_without_vat' => $summary['price_without_vat'],
                'price_with_vat' => $summary['price_with_vat'],
                'configuration' => $configuration,
                'crm_payload' => $crmPayload,
                'status' => InquiryStatus::New->value,
            ]);

            foreach ($positions as $position) {
                $item = $inquiry->items()->create([
                    'position' => $position['position'],
                    ...$position['attributes'],
                ]);

                // The illustration is stored next to its own item in the same transaction, so a stored
                // item never loses the picture it was sent with. It is never served without a signature.
                if ($position['preview'] instanceof UploadedFile) {
                    $item->addMedia($position['preview'])
                        ->usingFileName('nahlad-polozky-'.$item->getKey().'.png')
                        ->toMediaCollection(InquiryItem::PREVIEW_COLLECTION);
                }
            }

            $this->attachSurcharges($inquiry, $positions);

            // An inquiry of the older one-piece payload keeps its illustration on the inquiry itself,
            // exactly where it has always been.
            if ($legacyPreview instanceof UploadedFile) {
                $inquiry->addMedia($legacyPreview)
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
                    'door_model' => $firstPosition['door_model']->name,
                    'door_variant' => $firstPosition['door_variant']->code,
                    'decor' => $firstPosition['decor']?->name,
                    'glass_type' => $firstPosition['glass_type']?->name,
                    'surcharges' => $this->surchargeNames($positions),
                    'items' => array_map(
                        fn (array $position): array => [
                            'name' => $position['attributes']['name'],
                            'quantity' => $position['attributes']['quantity'],
                            'price_without_vat' => $position['attributes']['price_without_vat'],
                        ],
                        $positions,
                    ),
                    'total_doors' => $summary['total_doors'],
                    'price_with_vat' => $summary['price_with_vat'],
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

    /**
     * @return array<string, mixed>
     */
    private function rules(DoorPreviewImageInspector $previewInspector, bool $usesItemPayload): array
    {
        $customerRules = [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'customer_message' => ['nullable', 'string', 'max:5000'],
            // The acknowledgement of the privacy policy is a real consent, not a marketing opt-in: the
            // inquiry carries personal data, so it may only be sent after the visitor confirmed they
            // read how the workshop processes it.
            'gdpr_consent' => ['accepted'],
        ];

        if ($usesItemPayload) {
            return [
                ...$customerRules,
                'items' => ['required', 'array', 'min:1', 'max:'.InquiryItem::MAX_ITEMS_PER_INQUIRY],
                'items.*.name' => ['nullable', 'string', 'max:160'],
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.InquiryItem::MAX_QUANTITY_PER_ITEM],
                'items.*.door_model_id' => ['required', 'exists:door_models,id'],
                'items.*.door_variant_id' => ['required', 'exists:door_variants,id'],
                'items.*.decor_id' => ['nullable', 'exists:decors,id'],
                'items.*.glass_type_id' => ['nullable', 'exists:glass_types,id'],
                'items.*.surcharge_ids' => ['array'],
                'items.*.surcharge_ids.*' => ['integer', 'distinct', 'exists:surcharges,id'],
                'items.*.door_preview' => $this->previewRules($previewInspector),
            ];
        }

        return [
            ...$customerRules,
            'door_model_id' => ['required', 'exists:door_models,id'],
            'door_variant_id' => ['required', 'exists:door_variants,id'],
            'decor_id' => ['nullable', 'exists:decors,id'],
            'glass_type_id' => ['nullable', 'exists:glass_types,id'],
            'surcharge_ids' => ['array'],
            'surcharge_ids.*' => ['integer', 'distinct', 'exists:surcharges,id'],
            // The illustration of the chosen door that the browser composed right before submitting. It
            // is optional (a legacy browser may not be able to compose it) and it is never part of the
            // stored configuration: the price and the selection are always validated from the database.
            'door_preview' => $this->previewRules($previewInspector),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function previewRules(DoorPreviewImageInspector $previewInspector): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        $messages = [
            'gdpr_consent.accepted' => 'Bez potvrzení seznámení se zásadami ochrany osobních údajů poptávku odeslat nelze.',
            'items.required' => 'Poptávka musí obsahovat alespoň jednu konfiguraci.',
            'items.min' => 'Poptávka musí obsahovat alespoň jednu konfiguraci.',
            'items.max' => 'Najednou lze poptat nejvýše '.InquiryItem::MAX_ITEMS_PER_INQUIRY.' konfigurací.',
            'items.*.quantity.required' => 'U každé položky vyplňte počet kusů.',
            'items.*.quantity.integer' => 'Počet kusů musí být celé číslo.',
            'items.*.quantity.min' => 'Počet kusů musí být alespoň 1.',
            'items.*.quantity.max' => 'U jedné položky lze poptat nejvýše '.InquiryItem::MAX_QUANTITY_PER_ITEM.' kusů.',
        ];

        foreach (self::PREVIEW_MESSAGES as $rule => $message) {
            $messages['door_preview.'.$rule] = $message;
            $messages['items.*.door_preview.'.$rule] = $message;
        }

        return $messages;
    }

    /**
     * The flat payload of the one piece flow, expressed as the single item it describes.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function legacySelection(array $validated): array
    {
        return [
            'name' => null,
            'quantity' => 1,
            'door_model_id' => $validated['door_model_id'],
            'door_variant_id' => $validated['door_variant_id'],
            'decor_id' => $validated['decor_id'] ?? null,
            'glass_type_id' => $validated['glass_type_id'] ?? null,
            'surcharge_ids' => $validated['surcharge_ids'] ?? [],
        ];
    }

    /**
     * The recap of the whole request: the number of doors and the three price levels the customer saw.
     *
     * @param  list<array{attributes: array<string, mixed>}>  $positions
     * @return array{total_doors: int, price_without_vat: float, vat_amount: float, price_with_vat: float, vat_rate: float}
     */
    private function summary(array $positions): array
    {
        $priceWithoutVat = 0.0;
        $priceWithVat = 0.0;
        $totalDoors = 0;

        foreach ($positions as $position) {
            $totalDoors += (int) $position['attributes']['quantity'];
            $priceWithoutVat += (float) $position['attributes']['price_without_vat'];
            $priceWithVat += (float) $position['attributes']['price_with_vat'];
        }

        $priceWithoutVat = round($priceWithoutVat, 2);
        $priceWithVat = round($priceWithVat, 2);

        return [
            'total_doors' => $totalDoors,
            'price_without_vat' => $priceWithoutVat,
            'vat_amount' => round($priceWithVat - $priceWithoutVat, 2),
            'price_with_vat' => $priceWithVat,
            'vat_rate' => VatCalculator::rate(),
        ];
    }

    /**
     * The pivot has one row per surcharge of the whole inquiry, so the surcharges of all items are
     * deduplicated. The price per piece stays in the snapshot of every item.
     *
     * @param  list<array{surcharges: \Illuminate\Support\Collection<int, Surcharge>}>  $positions
     */
    private function attachSurcharges(Inquiry $inquiry, array $positions): void
    {
        $prices = [];

        foreach ($positions as $position) {
            foreach ($position['surcharges'] as $surcharge) {
                $prices[$surcharge->id] ??= round((float) $surcharge->price_without_vat, 2);
            }
        }

        if ($prices === []) {
            return;
        }

        $inquiry->surcharges()->attach($prices);
    }

    /**
     * @param  list<array{surcharges: \Illuminate\Support\Collection<int, Surcharge>}>  $positions
     * @return list<string>
     */
    private function surchargeNames(array $positions): array
    {
        $names = [];

        foreach ($positions as $position) {
            foreach ($position['surcharges'] as $surcharge) {
                $names[$surcharge->id] = $surcharge->name;
            }
        }

        return array_values($names);
    }
}
