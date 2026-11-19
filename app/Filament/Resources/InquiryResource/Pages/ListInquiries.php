<?php

namespace App\Filament\Resources\InquiryResource\Pages;

use App\Filament\Resources\InquiryResource;
use App\Models\Inquiry;
use App\Services\Inquiry\InquiryStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * The list is split into tabs: "Vše" shows every inquiry, the other four show one status each.
 *
 * The tabs are built from InquiryStatus, so they can never drift from the status select in the edit
 * form. The counts come from one grouped query per page load and a zero count hides its badge, so
 * the row of tabs stays quiet in a list that is mostly empty.
 */
class ListInquiries extends ListRecords
{
    protected static string $resource = InquiryResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->inquiryCountsByStatus();
        $total = array_sum($counts);

        $tabs = [
            'all' => Tab::make('Vše')
                ->badge($total === 0 ? null : $total),
        ];

        foreach (InquiryStatus::cases() as $status) {
            $count = $counts[$status->value] ?? 0;

            $tabs[$status->value] = Tab::make($status->label())
                ->badge($count === 0 ? null : $count)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value));
        }

        return $tabs;
    }

    /**
     * Number of inquiries per stored status key. Keys that are no longer part of InquiryStatus stay
     * in the map, so the "Vše" tab counts the legacy rows too and always agrees with the table.
     *
     * @return array<string, int>
     */
    private function inquiryCountsByStatus(): array
    {
        return Inquiry::query()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->status => (int) $row->aggregate])
            ->all();
    }
}
