<?php

namespace App\Filament\Resources\InquiryResource\Pages;

use App\Filament\Resources\InquiryResource;
use App\Models\Inquiry;
use App\Services\Inquiry\InquiryTotalsCalculator;
use Filament\Resources\Pages\EditRecord;

/**
 * The detail of one inquiry. The heading carries the inquiry number and the customer, because the
 * two column layout below already starts with the contact data itself.
 *
 * The prices are never a value the browser sends: after the form (and with it the items repeater) is
 * saved, the totals calculator recomputes every line price from the quantity and the price per piece
 * of the item and mirrors their sum into the inquiry. An inquiry stored before the items existed has
 * no items, so its manually edited prices are left exactly as the administrator typed them.
 */
class EditInquiry extends EditRecord
{
    protected static string $resource = InquiryResource::class;

    protected function afterSave(): void
    {
        /** @var Inquiry $inquiry */
        $inquiry = $this->getRecord();

        app(InquiryTotalsCalculator::class)->recalculate($inquiry);
    }

    public function getTitle(): string
    {
        /** @var Inquiry $record */
        $record = $this->getRecord();

        return 'Poptávka č. '.$record->getKey();
    }

    public function getSubheading(): ?string
    {
        /** @var Inquiry $record */
        $record = $this->getRecord();

        $receivedAt = $record->created_at?->format('d.m.Y H:i');

        $parts = array_filter([
            $record->customer_name,
            $record->customer_email,
            $receivedAt === null ? null : 'přijata '.$receivedAt,
            $this->doorsLine($record),
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * How many doors the request describes, so the heading already shows that an inquiry may carry
     * more than one configuration.
     */
    private function doorsLine(Inquiry $record): ?string
    {
        $record->loadMissing('items');

        if ($record->items->isEmpty()) {
            return null;
        }

        return $record->items->count().' položek · '.$record->totalDoors().' ks dveří';
    }
}
