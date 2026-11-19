<?php

namespace App\Console\Commands;

use App\Models\PrivacySetting;
use App\Services\Privacy\InquiryAnonymizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Manual retention step for the inquiry domain. The command is deliberately not scheduled: erasing
 * personal data is a decision of the owner, not a side effect of deploying the application.
 *
 * Two explicit modes are required, so the command can never anonymize by accident:
 *
 *   php artisan gdpr:anonymize-inquiries --dry-run
 *   php artisan gdpr:anonymize-inquiries --force
 */
class AnonymizeInquiries extends Command
{
    protected $signature = 'gdpr:anonymize-inquiries
        {--dry-run : Jen spočítá poptávky, kterých by se anonymizace týkala}
        {--force : Anonymizaci opravdu provede}
        {--months= : Doba uchovávání v měsících, přepíše hodnotu z administrace}';

    protected $description = 'Anonymizuje poptávky starší než nastavená doba uchovávání osobních údajů.';

    public function handle(InquiryAnonymizer $anonymizer): int
    {
        $months = $this->resolveRetentionMonths();

        if ($months === null) {
            $this->error('Doba uchovávání poptávek není nastavena. Doplňte ji v administraci (Ochrana osobních údajů) nebo použijte --months=.');

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $isForced = (bool) $this->option('force');

        if ($isDryRun === $isForced) {
            $this->error('Zadejte právě jednu možnost: --dry-run pro kontrolu, nebo --force pro provedení anonymizace.');

            return self::FAILURE;
        }

        $cutoff = $anonymizer->cutoff($months);

        if ($isDryRun) {
            $this->info(sprintf(
                'Připraveno k anonymizaci: %d (poptávky vytvořené do %s, doba uchovávání %d měsíců). Nic se nezměnilo.',
                $anonymizer->count($cutoff),
                $cutoff->toDateString(),
                $months,
            ));

            return self::SUCCESS;
        }

        $anonymized = $anonymizer->anonymize($cutoff);

        Log::info('Inquiry anonymization finished.', [
            'months' => $months,
            'cutoff' => $cutoff->toDateString(),
            'anonymized' => $anonymized,
        ]);

        $this->info(sprintf(
            'Anonymizováno: %d (poptávky vytvořené do %s, doba uchovávání %d měsíců).',
            $anonymized,
            $cutoff->toDateString(),
            $months,
        ));

        return self::SUCCESS;
    }

    /**
     * The option wins over the stored setting, so the owner can verify a different period once
     * without changing the administration.
     */
    private function resolveRetentionMonths(): ?int
    {
        $option = $this->option('months');

        if ($option !== null && $option !== '') {
            $months = (int) $option;

            return $months >= 1 ? $months : null;
        }

        return PrivacySetting::configuredRetentionMonths();
    }
}
