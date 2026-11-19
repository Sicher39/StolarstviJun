<?php

namespace App\Filament\Resources\InquiryResource\Pages;

use App\Filament\Resources\InquiryResource;
use App\Models\Inquiry;
use Filament\Resources\Pages\EditRecord;

/**
 * The detail of one inquiry. The heading carries the inquiry number and the customer, because the
 * two column layout below already starts with the contact data itself.
 */
class EditInquiry extends EditRecord
{
    protected static string $resource = InquiryResource::class;

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
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
