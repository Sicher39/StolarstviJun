<script setup lang="ts">

interface Props {
  administrator?: string
  companyId?: string
  adress?: string
  email?: string
  tel?: string
  date?: string
}

const props = withDefaults(defineProps<Props>(), {
  administrator: '',
  companyId: '',
  adress: '',
  email: '',
  tel: '',
  date: '',
})

</script>

<template>
  <div class="flex w-full justify-center py-0 md:pb-20 px-2 pt-28">
    <div class="block w-full 2xl:w-10/12 3xl:w-9/12">
      <div class="flex justify-center w-full">
        <div class="font-main font-normal text-lg block w-full lg:w-10/12 xl:w-8/12">
          <p class="font-main font-bold text-xl lg:text-2xl text-center text-second">
            I.<br />
            Základní ustanovení
          </p>
          <ol>
            <li>
              Správcem osobních údajů podle čl.&nbsp;4 bodu&nbsp;7 nařízení Evropského parlamentu a Rady (EU) 2016/679
              o ochraně fyzických osob v souvislosti se zpracováním osobních údajů a o volném pohybu těchto údajů
              (<span class="font-bold">„GDPR“</span>) je
              <span class="font-bold">{{ props.administrator }}</span>, IČ
              <span class="font-bold">{{ props.companyId }}</span>, se sídlem
              <span class="font-bold">{{ props.adress }}</span> (dále jen <span class="font-bold">„správce“</span>).
            </li>
            <br />
            <li>
              <span class="font-bold">Kontaktní údaje správce:</span><br />
              adresa: <span class="font-bold">{{ props.adress }}</span><br />
              e-mail: <span class="font-bold">{{ props.email }}</span><br />
              telefon: <span class="font-bold">{{ props.tel }}</span>
            </li>
            <br />
            <li>
              Osobními údaji se rozumí veškeré informace o&nbsp;identifikované nebo identifikovatelné fyzické osobě.
            </li>
            <br />
            <li>Správce nejmenoval pověřence pro ochranu osobních údajů (DPO).</li>
          </ol>

          <br />
          <p class="font-main font-bold text-xl lg:text-2xl text-center text-second">
            II.<br />
            Zdroje a kategorie zpracovávaných osobních údajů
          </p>
          <ol>
            <li>
              Zpracováváme údaje, které nám poskytnete (např. v&nbsp;objednávce, formuláři, e-mailem či telefonicky),
              a dále údaje nezbytné pro vyřízení Vašeho požadavku/objednávky.
            </li>
            <br />
            <li>
              Typicky jde o&nbsp;identifikační a kontaktní údaje (jméno, adresa, e-mail, telefon),
              fakturační a dodací údaje, údaje o&nbsp;objednávkách a komunikaci; na webu také technické a
              analytické cookies podle Vašich souhlasů (viz část IV.).
            </li>
          </ol>

          <br />
          <p class="font-main font-bold text-xl lg:text-2xl text-center text-second">
            III.<br />
            Účely zpracování a právní základy
          </p>
          <ol>
            <li>
              <span class="font-bold">Plnění smlouvy</span> (čl.&nbsp;6 odst.&nbsp;1 písm.&nbsp;b) GDPR):
              vyřízení Vaší objednávky, dodání zboží/služeb, fakturace, reklamační řízení.
              Poskytnutí nezbytných údajů je podmínkou uzavření a plnění smlouvy.
            </li>
            <br />
            <li>
              <span class="font-bold">Oprávněný zájem</span> (čl.&nbsp;6 odst.&nbsp;1 písm.&nbsp;f) GDPR):
              základní bezpečnost a provoz webu, ochrana právních nároků,
              nezbytná interní analytika v&nbsp;agregované podobě,
              přímý marketing vůči vlastním zákazníkům (obdobné výrobky/služby) v souladu s&nbsp;§&nbsp;7 odst.&nbsp;3 zákona č.&nbsp;480/2004&nbsp;Sb.
              (možnost kdykoli se odhlásit).
            </li>
            <br />
            <li>
              <span class="font-bold">Souhlas</span> (čl.&nbsp;6 odst.&nbsp;1 písm.&nbsp;a) GDPR):
              zasílání newsletterů a obchodních sdělení, používání marketingových a měřicích cookies (včetně reklamních funkcí GA4),
              případně další volitelné zpracování, které není nezbytné.
              Souhlas můžete kdykoli odvolat prostřednictvím cookie lišty nebo odhlašovacím odkazem v&nbsp;e-mailu.
            </li>
            <br />
            <li>
              <span class="font-bold">Automatizované rozhodování a profilování:</span>
              neprovádíme automatizované rozhodování s právními či obdobně významnými účinky.
              K&nbsp;omezenému profilování pro marketing (segmentace podle chování na webu) dochází pouze,
              pokud k&nbsp;tomu udělíte souhlas s&nbsp;marketingovými cookies.
            </li>
          </ol>

          <br />
          <p class="font-main font-bold text-xl lg:text-2xl text-center text-second">
            IV.<br />
            Cookies, měření návštěvnosti (Google Analytics&nbsp;4) a Google Tag Manager
          </p>
          <ol>
            <li>
              Na našem webu používáme <span class="font-bold">Google Analytics&nbsp;4</span> (poskytovatel Google Ireland Limited)
              pro měření návštěvnosti a zlepšování služeb. GA4 využívá soubory cookies a podobné technologie.
              Aktivujeme je pouze na základě Vašeho souhlasu, vyjma technicky nezbytných cookies.
            </li>
            <br />
            <li>
              V&nbsp;rámci GA4 je aktivní <span class="font-bold">IP anonymizace</span>. Zpracovávaná data mohou zahrnovat
              identifikátory cookie/zařízení, přibližná data o&nbsp;zařízení a interakci s&nbsp;webem.
              Pokud povolíte <span class="font-bold">reklamní funkce</span>, mohou být údaje použity i pro personalizaci reklamy.
            </li>
            <br />
            <li>
              Používáme <span class="font-bold">Google Tag Manager</span> jako správce značek, který sám o&nbsp;sobě
              osobní údaje nevyužívá k&nbsp;vlastním účelům; slouží k&nbsp;nasazování měřicích a marketingových kódů
              v&nbsp;závislosti na Vámi zvolených preferencích v&nbsp;cookie liště.
            </li>
            <br />
            <li>
              Preferencemi cookies můžete kdykoli spravovat prostřednictvím naší cookie lišty.
              Zpracování na základě souhlasu trvá do jeho odvolání; základní (nezbytné) cookies používáme
              na právním základu oprávněného zájmu/nezbytnosti pro poskytování služby.
            </li>
          </ol>

          <br />
          <p class="font-main font-bold text-xl lg:text-2xl text-center text-second">
            V.<br />
            Příjemci osobních údajů a předávání do třetích zemí
          </p>
          <ol>
            <li>
              Příjemci osobních údajů jsou zejména: poskytovatelé IT a hostingových služeb,
              účetní a právní poradci, dopravci a platební služby, nástroje pro e-mailing a analytiku
              (dle zvolených souhlasů), případně další smluvní zpracovatelé správce.
            </li>
            <br />
            <li>
              V&nbsp;odůvodněných případech může docházet k&nbsp;předávání údajů mimo EU/EHP
              (např. společnosti ze skupiny Google). V&nbsp;takových případech se spoléháme na
              <span class="font-bold">standardní smluvní doložky</span> (SCC) a doplňková opatření,
              aby byla zajištěna přiměřená úroveň ochrany v&nbsp;souladu s&nbsp;kapitolou&nbsp;V GDPR.
            </li>
          </ol>

          <br />
          <p class="font-main font-bold text-xl lg:text-2xl text-center text-second">
            VI.<br />
            Doba uchovávání
          </p>
          <ol>
            <li>
              Smluvní a účetní dokumentaci uchováváme po dobu nezbytnou pro splnění právních povinností
              a uplatnění/obranu práv (obvykle až 15&nbsp;let od ukončení smluvního vztahu – dle promlčecích a
              archivačních lhůt).
            </li>
            <br />
            <li>
              Marketing na základě souhlasu zpracováváme do jeho odvolání, nejdéle 5&nbsp;let od udělení souhlasu,
              nebude-li prodloužen.
            </li>
            <br />
            <li>
              Technické a analytické cookies uchováváme podle jejich životnosti a nastavení v&nbsp;cookie liště.
              Po uplynutí lhůt data bezpečně smažeme či anonymizujeme.
            </li>
          </ol>

          <br />
          <p class="font-main font-bold text-xl lg:text-2xl text-center text-second">
            VII.<br />
            Vaše práva
          </p>
          <ol>
            <li>
              Máte právo na přístup (čl.&nbsp;15), opravu (čl.&nbsp;16), výmaz (čl.&nbsp;17),
              omezení zpracování (čl.&nbsp;18), přenositelnost (čl.&nbsp;20) a vznést námitku (čl.&nbsp;21) proti
              zpracování založenému na oprávněném zájmu či proti přímému marketingu.
              Souhlas lze kdykoli odvolat bez vlivu na předchozí zpracování.
            </li>
            <br />
            <li>
              Domníváte-li se, že dochází k&nbsp;porušení GDPR, můžete podat stížnost u
              Úřadu pro ochranu osobních údajů (ČR).
            </li>
          </ol>

          <br />
          <p class="font-main font-bold text-xl lg:text-2xl text-center text-second">
            VIII.<br />
            Zabezpečení osobních údajů
          </p>
          <ol>
            <li>
              Přijímáme vhodná technická a organizační opatření k&nbsp;zajištění úrovně zabezpečení přiměřené rizikům,
              včetně řízení přístupů, šifrování vybraných přenosů, bezpečných hesel, pravidelných aktualizací,
              antivirové ochrany a zabezpečeného zálohování.
              K&nbsp;osobním údajům mají přístup pouze pověřené osoby.
            </li>
          </ol>

          <br />
          <p class="font-main font-bold text-xl lg:text-2xl text-center text-second">
            IX.<br />
            Závěrečná ustanovení
          </p>
          <ol>
            <li>
              Odesláním objednávky či odesláním formuláře potvrzujete, že jste seznámen/a s&nbsp;těmito
              zásadami zpracování osobních údajů a&nbsp;že je v&nbsp;celém rozsahu přijímáte.
            </li>
            <li>
              Souhlas s&nbsp;marketingem a nepovinnými cookies je zcela dobrovolný a lze jej kdykoli změnit
              prostřednictvím cookie lišty nebo odvolat dle pokynů v&nbsp;obchodních sděleních.
            </li>
            <li>
              Správce je oprávněn tyto podmínky průběžně aktualizovat. Novou verzi zveřejníme na webu
              a v&nbsp;případě podstatných změn Vás můžeme informovat e-mailem.
            </li>
          </ol>

          <p class="font-main mt-10 font-bold">
            Tyto podmínky nabývají účinnosti dnem {{ props.date }}.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
