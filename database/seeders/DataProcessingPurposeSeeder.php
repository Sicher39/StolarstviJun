<?php

namespace Database\Seeders;

use App\Enums\Compliance\LegalBasis;
use App\Models\DataProcessingPurpose;
use Illuminate\Database\Seeder;

/**
 * The purposes for which this workshop really processes personal data.
 *
 * The `context` column is the stable, seed-owned key: a re-run updates the seeded record in place
 * instead of creating a duplicate, and a purpose an administrator created under a different context is
 * never touched. Only purposes the application supports are described here - an inquiry from the door
 * configurator, a resulting order or custom manufacture, customer communication, accounting duties,
 * the evidence of consents and - only when the visitor opts in - analytics and marketing.
 *
 * Nothing of the e-commerce source project survives: there is no carrier, no payment gateway, no
 * invoicing service and no newsletter in this application, so none of them is named as a recipient.
 * A recipient the repository can not verify stays a generic category and the place for the concrete
 * company is marked `[DOPLNIT: ...]`, exactly like in the legal text.
 *
 * Descriptions are written as one paragraph per field, because the public page renders them inside a
 * single card; retention periods carry the statutory reference they come from.
 */
class DataProcessingPurposeSeeder extends Seeder
{
    /**
     * Canonical purposes of the workshop.
     *
     * @var list<array<string, mixed>>
     */
    private const PURPOSES = [
        [
            'name' => 'Vyřízení poptávky a příprava nabídky',
            'context' => DataProcessingPurpose::CONTEXT_INQUIRY,
            'description' => 'Zpracování údajů, které odešlete v poptávkovém formuláři konfigurátoru dveří nebo nám je sdělíte e-mailem či telefonicky. Údaje potřebujeme k tomu, abychom posoudili, zda je zvolenou zakázku možné vyrobit, a abychom připravili nezávaznou nabídku. Součástí poptávky je zvolená konfigurace dveří a orientační cena, kterou konfigurátor vypočítal.',
            'personal_data_categories' => 'Jméno a příjmení, e-mail, telefon (nepovinný), obsah zprávy (nepovinný), zvolená konfigurace dveří a orientační cena vypočtená konfigurátorem.',
            'legal_basis' => LegalBasis::Contract,
            'retention_period' => 'Po dobu vyřízení poptávky, nejdéle však doba nastavená v administraci (poptávky se poté anonymizují nebo mažou); vznikne-li ze poptávky zakázka, řídí se doba uchování níže uvedeným účelem plnění smlouvy.',
            'recipients' => 'Pověřené osoby správce (obchod a výroba), poskytovatel hostingu a správy webu [DOPLNIT: obchodní firma a sídlo], poskytovatel e-mailové schránky [DOPLNIT: obchodní firma a sídlo].',
            'third_country_transfer' => null,
            'is_active' => true,
            'priority' => 10,
        ],
        [
            'name' => 'Plnění smlouvy – zakázková výroba, dodání a montáž',
            'context' => 'contract_fulfillment',
            'description' => 'Zpracování údajů potřebných k uzavření a plnění smlouvy o zakázkové výrobě: výroba dveří, nábytku nebo jiných prvků na míru, domluva termínu, dodání a montáž. Účel vzniká pouze tehdy, když si u nás zakázku skutečně objednáte; z pouhé poptávky se nic nevyrábí.',
            'personal_data_categories' => 'Jméno a příjmení, fakturační a dodací adresa, e-mail, telefon, firemní údaje (název, IČO, DIČ) u objednávky na firmu, popis zakázky, termíny a předávací protokoly.',
            'legal_basis' => LegalBasis::Contract,
            'retention_period' => 'Po dobu plnění zakázky a dále 3 roky pro ochranu právních nároků (promlčecí lhůta); dokumentace předání a montáže se uchovává po dobu odpovědnosti za vady.',
            'recipients' => 'Pověřené osoby správce (výroba, montáž), případní subdodavatelé montáže [DOPLNIT: obchodní firma a sídlo, pokud jsou využíváni], poskytovatel hostingu a IT infrastruktury.',
            'third_country_transfer' => null,
            'is_active' => true,
            'priority' => 20,
        ],
        [
            'name' => 'Komunikace se zákazníky a vyřizování dotazů',
            'context' => 'customer_communication',
            'description' => 'Odpovídání na dotazy k poptávkám, zakázkám, materiálům, termínům a reklamacím, a to e-mailem, telefonicky nebo osobně. Komunikace navazuje na poptávku nebo zakázku a slouží k tomu, aby byla domluva dohledatelná.',
            'personal_data_categories' => 'Jméno a příjmení, e-mail, telefon a obsah komunikace (dotazy, požadavky, domluva termínů).',
            'legal_basis' => LegalBasis::LegitimateInterest,
            'retention_period' => 'Po dobu vyřízení dotazu a dále 3 roky pro ochranu právních nároků správce.',
            'recipients' => 'Pověřené osoby správce, poskytovatelé hostingu, e-mailové a IT infrastruktury.',
            'third_country_transfer' => null,
            'is_active' => true,
            'priority' => 30,
        ],
        [
            'name' => 'Plnění účetních a daňových povinností',
            'context' => 'legal_accounting',
            'description' => 'Vedení účetnictví a daňové evidence, vystavování a archivace dokladů k zakázkám v rozsahu stanoveném právními předpisy (zákon o účetnictví, zákon o DPH). Účel se týká jen těch údajů, které se na dokladu skutečně objeví.',
            'personal_data_categories' => 'Identifikační a fakturační údaje, údaje o zakázce a platbě, firemní údaje (název, IČO, DIČ) u objednávky na firmu.',
            'legal_basis' => LegalBasis::LegalObligation,
            'retention_period' => '5 let od konce účetního období, ve kterém byl doklad vystaven; daňové doklady k DPH po dobu stanovenou zákonem o DPH.',
            'recipients' => 'Správce, externí účetní a daňový poradce [DOPLNIT: obchodní firma a sídlo], orgány veřejné správy v rozsahu stanoveném zákonem.',
            'third_country_transfer' => null,
            'is_active' => true,
            'priority' => 40,
        ],
        [
            'name' => 'Evidence souhlasů a bezpečnostní záznamy',
            'context' => 'consent_security',
            'description' => 'Uchování záznamů o udělených souhlasech (volba cookies a potvrzení seznámení se zásadami u poptávky) a zpracování technických bezpečnostních záznamů, jako je relace prohlížení a ochrana odesílaných formulářů. Záznamy slouží jako důkaz pro případnou kontrolu dozorovým orgánem a pro obranu proti zneužití formulářů.',
            'personal_data_categories' => 'Hashovaná IP adresa, hashovaný prohlížeč, časové razítko a obsah souhlasu, jméno a kontakt z poptávky, technické údaje relace.',
            'legal_basis' => LegalBasis::LegitimateInterest,
            'retention_period' => 'Záznamy souhlasů po dobu platnosti souhlasu a 3 roky po jeho odvolání; bezpečnostní záznamy zpravidla nejdéle 1 rok.',
            'recipients' => 'Správce, poskytovatelé hostingu a bezpečnostní infrastruktury.',
            'third_country_transfer' => null,
            'is_active' => true,
            'priority' => 50,
        ],
        [
            'name' => 'Analytika návštěvnosti (jen se souhlasem)',
            'context' => 'analytics',
            'description' => 'Měření návštěvnosti webu a zlepšování nabídky dveří a zakázkové výroby. Analytické nástroje jsou ve výchozím stavu vypnuté: spustí se pouze tehdy, když je v cookie liště povolíte, a žádný skript se nenačte bez vašeho souhlasu.',
            'personal_data_categories' => 'Online identifikátory (cookie, IP adresa), údaje o chování na webu (navštívené stránky, prohlížeč, přibližná lokalita).',
            'legal_basis' => LegalBasis::Consent,
            'retention_period' => 'Po dobu platnosti souhlasu, nejdéle po dobu stanovenou poskytovatelem zapnutého nástroje.',
            'recipients' => 'Poskytovatelé analytických nástrojů, kteří jsou zapnuti v administraci (pokud žádný zapnutý není, žádné údaje se nepředávají), správce.',
            'third_country_transfer' => 'Případné předání do třetích zemí závisí na tom, které nástroje jsou zapnuté; konkrétní informace uvádějí zásady používání cookies.',
            'is_active' => true,
            'priority' => 60,
        ],
        [
            'name' => 'Marketing a měření reklamních kampaní (jen se souhlasem)',
            'context' => 'marketing',
            'description' => 'Měření účinnosti reklamních kampaní a remarketing. Marketingové nástroje jsou ve výchozím stavu vypnuté a spouštějí se pouze tehdy, když je v cookie liště povolíte. Údaje z poptávek k marketingu bez samostatného souhlasu nepoužíváme.',
            'personal_data_categories' => 'Online identifikátory (cookie, IP adresa), údaje o chování na webu a interakci s reklamním obsahem.',
            'legal_basis' => LegalBasis::Consent,
            'retention_period' => 'Po dobu platnosti souhlasu, nejdéle po dobu stanovenou poskytovatelem zapnutého nástroje.',
            'recipients' => 'Poskytovatelé marketingových nástrojů, kteří jsou zapnuti v administraci (pokud žádný zapnutý není, žádné údaje se nepředávají), správce.',
            'third_country_transfer' => 'Případné předání do třetích zemí závisí na tom, které nástroje jsou zapnuté; konkrétní informace uvádějí zásady používání cookies.',
            'is_active' => true,
            'priority' => 70,
        ],
    ];

    public function run(): void
    {
        foreach (self::PURPOSES as $purpose) {
            $existing = DataProcessingPurpose::query()
                ->where('context', $purpose['context'])
                ->first();

            if ($existing === null) {
                DataProcessingPurpose::query()->create($purpose);

                continue;
            }

            // The seeded record is refreshed in place: the operator's own purposes never share its
            // context, so they are not touched.
            $existing->fill($purpose)->save();
        }
    }
}
