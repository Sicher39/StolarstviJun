<?php

use App\Models\LegalDocument;

/*
|--------------------------------------------------------------------------
| Právní dokumenty
|--------------------------------------------------------------------------
|
| Every legal page of the site is defined here once. The values are mirrored into the
| `legal_documents` table by Database\Seeders\LegalDocumentSeeder and edited in the
| administration afterwards. This configuration is also the fallback used when no row exists,
| so the public page never renders an empty document.
|
| The site is Czech only, therefore the single locale is `cs`.
|
| The text uses two conventions:
|
| - `{{ variable }}` is replaced by a value read from the contact record, the privacy settings
|   or the application configuration (see App\Services\Legal\LegalContentRenderer). Variables are
|   the only values that are kept in sync with the administration automatically.
|
| - `{{ block }}` (`{{ processing_purposes }}`, `{{ cookie_services }}`) marks where a structured
|   overview belongs: the processing purposes of the privacy policy and the cookies and services of
|   the cookie policy. The block is built from the administration records by
|   App\Services\Compliance\ComplianceContentBlocks and inserted exactly where the token stands, so
|   the document never repeats in prose what the panel already describes.
|
| - `[DOPLNIT: ...]` marks a legally material fact that could not be verified from this repository
|   (processor identities, the supervisory authority address, the effective date, DPO contact,
|   transfers outside the EU). Nothing is invented here: the owner fills the gaps in the panel and
|   the marker disappears from the published page.
|
| The text describes only what this application really does: a door configurator that stores an
| inquiry (name, e-mail, optional phone and message, chosen configuration and indicative price),
| an administration panel, a contact page and a cookie dialog that asks before any optional tool
| runs. There is no customer account, no e-shop, no payment gateway and no newsletter; no analytics
| or advertising script is enabled by default (`database/seeders/TrackingScriptSeeder`).
|
*/

return [

    'default_locale' => LegalDocument::DEFAULT_LOCALE,

    /*
    | Locales offered in the administration. The frontend is Czech only, so no other locale is
    | published today; the column exists so a translation can be added without a migration.
    */

    'locales' => [
        LegalDocument::DEFAULT_LOCALE => 'Čeština',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dokumenty
    |--------------------------------------------------------------------------
    |
    | The array key is the document type stored in the `type` column.
    |
    */

    'documents' => [

        LegalDocument::TYPE_PRIVACY_POLICY => [
            'label' => 'Zásady ochrany osobních údajů',
            'title' => 'Zásady ochrany osobních údajů',
            'version' => '1.0',
            'perex' => 'Tyto zásady popisují, jak Stolařství Jun a synové s.r.o. zpracovává osobní údaje lidí, kteří si na webu prohlížejí nabídku dveří a zakázkové výroby, odešlou poptávku nebo nás kontaktují e-mailem či telefonicky.',
            'content' => <<<'HTML'
<p>Tyto zásady ochrany osobních údajů popisují, jak <strong>{{ company_name }}</strong> zpracovává osobní údaje lidí, kteří si na webu prohlížejí nabídku dveří a zakázkové výroby, odešlou poptávku nebo nás kontaktují e-mailem či telefonicky.</p>

<h2>1. Správce osobních údajů</h2>
<p>Správcem osobních údajů je <strong>{{ company_name }}</strong>, se sídlem {{ full_address }}, IČ {{ registration_number }}, DIČ {{ vat_number }}, datová schránka {{ data_box }}. {{ registry_note }}</p>
<p>Kontaktovat nás můžete e-mailem na <strong>{{ email }}</strong> nebo telefonicky na <strong>{{ phone }}</strong>. Pro záležitosti ochrany osobních údajů používáme stejnou e-mailovou adresu.</p>
<p>[DOPLNIT: pokud firma jmenovala pověřence pro ochranu osobních údajů, doplňte jeho jméno a kontakt. Pokud jmenován nebyl, tuto větu vymažte.]</p>

<h2>2. Jaké údaje zpracováváme</h2>
<p>Rozsah údajů odpovídá tomu, co web skutečně umí:</p>
<ul>
<li><strong>Poptávka z konfigurátoru dveří:</strong> jméno, e-mail, telefon (nepovinný), text zprávy (nepovinný), zvolená konfigurace dveří a orientační cena vypočtená konfigurátorem.</li>
<li><strong>E-mailová a telefonická komunikace:</strong> jméno, kontaktní údaje a obsah zpráv, které nám sami pošlete.</li>
<li><strong>Technické údaje o provozu webu:</strong> technicky nezbytné cookies (relace, ochrana odesílaných formulářů, uložení vaší volby cookies) a běžné záznamy webového serveru.</li>
</ul>
<p>Na webu není zákaznický účet, e-shop ani platební brána, proto nezpracováváme hesla, platební údaje ani historii objednávek.</p>

<h2>3. Účely a právní základy zpracování</h2>
<p>Osobní údaje zpracováváme pouze pro účely uvedené níže. U každého účelu najdete právní základ, rozsah zpracovávaných údajů, dobu uchovávání a příjemce.</p>
{{ processing_purposes }}
<p>Údaje z poptávek nepoužíváme k zasílání obchodních sdělení ani k jinému marketingu. Analytické a marketingové nástroje spouštíme pouze tehdy, pokud je povolíte v nastavení cookies; bez vašeho souhlasu se žádný z nich nenačte.</p>

<h2>4. Doba uchovávání</h2>
<ul>
<li>Poptávky a komunikaci k nim uchováváme <strong>{{ inquiry_retention_period }}</strong>, poté je anonymizujeme nebo vymažeme.</li>
<li>Účetní a daňové doklady uchováváme po dobu, kterou ukládají právní předpisy o účetnictví a o daních.</li>
<li>Údaje potřebné k ochraně právních nároků uchováváme po dobu, po kterou je lze u soudu uplatnit.</li>
<li>Technicky nezbytné cookies zanikají na konci relace prohlížeče.</li>
</ul>

<h2>5. Příjemci a předávání údajů</h2>
<p>Osobní údaje zpracovávají pověřené osoby správce a dodavatelé, kteří pro nás zajišťují technický provoz:</p>
<ul>
<li>poskytovatel hostingu a správy webu: [DOPLNIT: obchodní firma a sídlo poskytovatele hostingu],</li>
<li>poskytovatel e-mailové schránky: [DOPLNIT: obchodní firma a sídlo poskytovatele e-mailu],</li>
<li>externí účetní a daňový poradce: [DOPLNIT: obchodní firma a sídlo],</li>
<li>poskytovatel IT podpory a zálohování: [DOPLNIT: obchodní firma a sídlo].</li>
</ul>
<p>[DOPLNIT: zda a kam se osobní údaje předávají mimo Evropskou unii a Evropský hospodářský prostor, včetně uvedení vhodných záruk.]</p>

<h2>6. Cookies a měření návštěvnosti</h2>
<p>Web ve výchozím stavu používá pouze technicky nezbytné cookies: relaci pro zobrazení stránek, token pro ochranu odesílaných formulářů a uložení vaší volby cookies. Bez nich by web nefungoval, proto je není potřeba povolovat.</p>
<p>Analytické, marketingové ani preferenční cookies nespouštíme bez vašeho souhlasu. Souhlas udělujete v cookie liště a můžete ho kdykoli změnit nebo odvolat. Přehled všech cookies a služeb, které web používá, najdete v <a href="/zasady-cookies">zásadách používání cookies</a>.</p>

<h2>7. Automatizované rozhodování a profilování</h2>
<p>Neprovádíme automatizované rozhodování ani profilování. Konfigurátor pouze vypočítá orientační cenu podle zvolené konfigurace; cenu ani nabídku neschválí bez kontroly člověkem.</p>

<h2>8. Vaše práva</h2>
<p>Podle článků 15 až 22 GDPR máte právo:</p>
<ul>
<li>na přístup k osobním údajům,</li>
<li>na opravu nepřesných údajů,</li>
<li>na výmaz („právo být zapomenut“),</li>
<li>na omezení zpracování,</li>
<li>na přenositelnost údajů,</li>
<li>vznést námitku proti zpracování založenému na oprávněném zájmu.</li>
</ul>
<p>Žádost pošlete na <strong>{{ email }}</strong>. Vyřídíme ji bez zbytečného odkladu, nejpozději do jednoho měsíce.</p>
<p>Domníváte-li se, že zpracování porušuje GDPR, můžete podat stížnost u Úřadu pro ochranu osobních údajů, [DOPLNIT: adresa a webové stránky úřadu].</p>

<h2>9. Zabezpečení osobních údajů</h2>
<p>Přístup do administrace webu je chráněn přihlášením a oprávněními podle rolí, osobní údaje se ukládají v databázi webu a zálohují se. K údajům mají přístup pouze pověřené osoby.</p>

<h2>10. Změny těchto zásad</h2>
<p>Zásady průběžně aktualizujeme, když se změní obsah webu nebo způsob zpracování. Aktuální verze je vždy zveřejněna na této stránce; datum poslední úpravy najdete níže.</p>
<p>Tyto zásady nabývají účinnosti dnem [DOPLNIT: datum účinnosti].</p>
HTML,
        ],

        LegalDocument::TYPE_COOKIE_POLICY => [
            'label' => 'Zásady používání cookies',
            'title' => 'Zásady používání cookies',
            'version' => '1.0',
            // The seeded row stays a draft on purpose: the text below is an operator-reviewable
            // template with placeholders, not a set of terms the workshop already published. The
            // public page renders it exactly as it renders every draft, and the editor confirms the
            // wording before filling `published_at`.
            'publish_on_seed' => false,
            'perex' => 'Tyto zásady popisují, jaké cookies web Stolařství Jun a synové s.r.o. používá, k čemu slouží a jak můžete volitelné cookies povolit nebo odmítnout.',
            'content' => <<<'HTML'
<p>Tyto zásady používání cookies vysvětlují, jak <strong>{{ company_name }}</strong>, se sídlem {{ full_address }}, používá cookies a podobné technologie na tomto webu. Navazují na <a href="/ochrana-osobnich-udaju">zásady ochrany osobních údajů</a>, které popisují zpracování osobních údajů obecně.</p>

<h2>1. Co jsou cookies</h2>
<p>Cookies jsou malé textové soubory, které web ukládá do vašeho prohlížeče. Umožňují zapamatovat si stav relace, ochranu odesílaného formuláře nebo vaši volbu cookies. Některé cookies web potřebuje k technickému provozu, jiné jsou dobrovolné.</p>

<h2>2. Jaké kategorie používáme</h2>
<ul>
<li><strong>Nezbytné cookies</strong> – zajišťují chod webu, bezpečnost odesílaných formulářů a uložení vaší volby cookies. Nelze je vypnout a nepotřebují souhlas.</li>
<li><strong>Analytické cookies</strong> – volitelné. Spouštějí se jen po vašem souhlasu a slouží k měření návštěvnosti.</li>
<li><strong>Marketingové cookies</strong> – volitelné. Spouštějí se jen po vašem souhlasu a slouží k měření reklamních kampaní.</li>
<li><strong>Preferenční cookies</strong> – volitelné. Spouštějí se jen po vašem souhlasu a pamatují si volitelné nastavení webu.</li>
</ul>
<p>Volitelnou kategorii nabízíme v cookie liště jen tehdy, pokud je na webu skutečně co spouštět. Pokud web žádný nástroj dané kategorie nepoužívá, kategorii nenabídneme a nic si neuložíme.</p>

<h2>3. Přehled cookies a služeb</h2>
<p>Následující přehled odpovídá nastavení webu v době zobrazení této stránky.</p>
{{ cookie_services }}

<h2>4. Jak souhlas udělit, změnit a odvolat</h2>
<p>Při první návštěvě se zobrazí cookie lišta s volbou povolit vše, odmítnout volitelné nebo nastavit kategorie jednotlivě. Stejné nastavení můžete kdykoli otevřít odkazem <em>Nastavení cookies</em> v patičce webu.</p>
<p>Souhlas můžete kdykoli odvolat; volitelné cookies se pak přestanou ukládat. Nezbytné cookies se ukládají vždy, protože bez nich web nefunguje.</p>

<h2>5. Cookies třetích stran</h2>
<p>Nástroje třetích stran se na webu spouštějí pouze tehdy, pokud je v cookie liště povolíte. [DOPLNIT: pokud budou doplněny konkrétní nástroje třetích stran, doplňte jejich poskytovatele, dobu uchování a odkaz na jejich zásady.]</p>

<h2>6. Jak cookies odmítnout v prohlížeči</h2>
<p>Cookies můžete mazat nebo blokovat také v nastavení svého prohlížeče. Pokud zablokujete nezbytné cookies, některé části webu nemusí fungovat – zejména odeslání poptávkového formuláře.</p>

<h2>7. Změny těchto zásad</h2>
<p>Zásady aktualizujeme, když se změní používané cookies nebo nástroje. Aktuální verze je vždy zveřejněna na této stránce.</p>
<p>Tyto zásady nabývají účinnosti dnem [DOPLNIT: datum účinnosti].</p>
HTML,
        ],

    ],

];
