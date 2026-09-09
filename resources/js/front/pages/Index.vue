<script lang="ts" setup>
import MainLayout from '@/front/layouts/MainLayout.vue'
import SeoHead, {SeoProps} from '@/front/components/Seo/SeoHead.vue'
import FullSection from "@/front/components/Sections/FullSection.vue"
import ButtonMain from "@/front/components/Buttons/ButtonMain.vue";
import TextGridFour from "@/front/components/TextGrid/TextGridFour.vue";
import VerticalScrollLine from '@/front/components/gsap/VerticalScrollLine.vue'
import {nextTick, onBeforeUnmount, onMounted, ref} from 'vue'
import {gsap} from 'gsap'
import {ScrollTrigger} from 'gsap/ScrollTrigger'
import HeaderSection from "@/front/components/Sections/HeaderSection.vue";
import ScrollLinesRevealSection from '@/front/components/Sections/ScrollLinesRevealSection.vue'
import {Link} from "@inertiajs/vue3";

defineOptions({
  layout: MainLayout
})

let revealGsapContext: gsap.Context | null = null

onMounted(async () => {
  await nextTick()

  gsap.registerPlugin(ScrollTrigger)

  revealGsapContext = gsap.context(() => {
    gsap.set('.reveal', { y: 60, opacity: 0 })
    ScrollTrigger.batch('.reveal', {
      start: 'top 80%',
      once: true,
      onEnter: (batch) =>
        gsap.to(batch, {
          y: 0,
          opacity: 1,
          stagger: 0.15,
          duration: 1.6,
          ease: 'power2.out'
        })
    })
  })
})

onBeforeUnmount((): void => {
  revealGsapContext?.revert()
  revealGsapContext = null
})

const seoHome: SeoProps = {
  title: '',
  description:
      '',
  keywords: [
    '',
  ],
  canonical: '',
  ogImage: '',
  ogSiteName: '',
  structuredData: {
    '@context': 'https://schema.org',
    '@type': '',
    name: '',
    url: '',
    address: {
      '@type': 'PostalAddress',
      streetAddress: '',
      addressLocality: '',
      postalCode: '',
      addressCountry: 'CZ'
    },
    areaServed: ''
  }
}

const facts = ref([
  {
    sectionHeader: 'Každý výrobek navrhujeme a vyrábíme individuálně podle konkrétního prostoru, účelu a technických možností. Nepracujeme s univerzálními řešeními, ale s funkčním návrhem, který dává dlouhodobě smysl.',
    content: [
      {
        header: 'kvalitní masivní dřevo a ověřené materiály',
        note: 'Používáme materiály, se kterými máme dlouhodobé zkušenosti a jejich vlastnosti známe v praxi. Volbu dřeva i dalších komponent vždy přizpůsobujeme konkrétnímu použití a zatížení výrobku.'
      },
      {
        header: 'precizní zpracování detailů',
        note: 'Důraz klademe na přesnost, čisté spoje a kvalitní povrchovou úpravu. Právě detaily rozhodují o funkčnosti, vzhledu i životnosti celého výrobku.'
      },
      {
        header: 'osobní přístup od návrhu po montáž',
        note: 'Se zákazníkem komunikujeme přímo a po celou dobu realizace. Od prvního návrhu až po montáž řešíme zakázku osobně a s maximální pečlivostí.'
      }
    ]
  }
])

const woodFactory = ref([
  {
    sectionHeader: 'Navrhujeme a vyrábíme dřevěné prvky na míru s důrazem na funkčnost, detail a dlouhou životnost. Každý projekt řešíme individuálně podle prostoru, účelu i nároků na každodenní používání.',
    content: [
      {
        header: 'Schody, zábradlí',
        note: 'Navrhujeme konstrukčně spolehlivá řešení, která odpovídají prostoru i provoznímu zatížení. Každý detail řešíme s ohledem na bezpečnost, komfort i dlouhodobou odolnost.'
      },
      {
        header: 'Obložení interiérů',
        note: 'Vytváříme čisté a přesné realizace, které přirozeně navazují na architekturu prostoru. Důraz klademe na návaznosti, detaily a celkový vizuální dojem.'
      },
      {
        header: 'Kuchyně, skříně a další',
        note: 'Navrhujeme praktická a promyšlená řešení, která respektují každodenní provoz i individuální potřeby. Každý prvek má své místo, funkci i logiku.'
      }
    ]
  }
])


</script>

<template>
  <SeoHead v-bind="seoHome"/>

  <HeaderSection
      img="red-door"
      button="konfigurátor dveří"
      link="configurator"
  >
    <template v-slot:header>
      Každý kus dřeva <br>
      má svůj příběh, <br>
      my mu dáme tvar <br>
      podle vašich představ
    </template>
  </HeaderSection>


  <FullSection>
    <div class="block w-full lg:pb-10 mt-[350px]">
      <h3 class="text-accent text-6xl 2xl:text-8xl reveal">Poctivá výroba dveří<br>
        bez kompromisů kvality</h3>
      <p class="mt-10 reveal text-xl uppercase">Nejsme sériová výroba. Každé dveře vznikají na zakázku – podle prostoru,
        <br> stylu i přání
        zákazníka.</p>
      <div class="block pt-10">
        <TextGridFour v-for="(item, i) in facts" :key="i" v-bind="item"/>
      </div>
    </div>
  </FullSection>

  <ScrollLinesRevealSection>
    Výroba dveří u&nbsp;nás stojí na poctivém řemesle, zkušenostech a&nbsp;citu pro detail. Práce se dřevem má v&nbsp;naší
    firmě
    dlouhou tradici, kterou dnes rozvíjí už druhá generace.
  </ScrollLinesRevealSection>

  <div class="block relative w-full mt-32">
    <div class="absolute top-0 left-0 flex justify-end w-full">
      <img src="/img/bg/small/Doors01.webp" alt="">
    </div>
    <div class="relative top-0 left-0">
      <FullSection>
        <div class="block w-full ">
          <h3 class="text-accent text-6xl 2xl:text-8xl reveal">Zakázková výroba<br>
            dveří na míru</h3>
          <p class="mt-5 reveal text-xl uppercase">Kvalitní řemeslo ze dřeva, které vydrží generace.</p>
          <div class="grid grid-cols-12 w-full mt-10" data-vertical-scroll-line-container>
            <div class="col-span-3 flex justify-center">
              <VerticalScrollLine/>
            </div>
            <div class="col-span-9 grid grid-cols-12 pt-20">
              <div class="col-span-9">
                <p>Vyrábíme interiérové i&nbsp;vchodové dveře na míru. <br> Každý kus vzniká v&nbsp;naší dílně s&nbsp;důrazem
                  na detail, <br> funkčnost a&nbsp;dlouhou životnost. Spojujeme poctivé<br> stolařské řemeslo s&nbsp;moderními
                  technologiemi.</p>
                <div class="block w-[250px] group mt-20">
                  <Link :href="route(`front.doors`)">
                    <ButtonMain><span class="uppercase font-main">Katalog produktů</span></ButtonMain>
                  </Link>
                </div>
                <div class="block mt-32">
                  <img src="/img/bg/small/Doors02.webp" class="w-full" alt="">
                </div>
              </div>

              <div class="col-span-12 grid grid-cols-12 mt-[150px]">
                <div class="col-span-12">
                  <h3 class="text-accent text-6xl 2xl:text-8xl reveal">Vyberte si, co vám sedí</h3>
                  <p>Připravili jsme pro vás konfigurátor dveří</p>
                </div>
                <div class="col-span-6 relative -left-[180px] mt-20">
                  <img src="/img/bg/small/Doors03.webp" class="w-full" alt="">
                </div>
                <div class="col-span-4 relative -left-[140px] mt-10">

                  <p class="mt-10">Navrhněte si dveře přesně podle svých představ – od typu (interiérové, vchodové či
                    protipožární) až po každý detail provedení.
                    V konfigurátoru si snadno zvolíte materiál, design i kování, aby vše dokonale ladilo s vaším
                    prostorem.
                    Okamžitě tak získáte přehled o výsledné podobě i orientační ceně dveří, které vám opravdu
                    vyhovují.</p>
                  <Link :href="route('front.configurator')" class="block w-[250px] group mt-20">
                    <ButtonMain><span class="uppercase font-main">spustit konfigurátor</span></ButtonMain>
                  </Link>
                </div>

              </div>

            </div>
          </div>
        </div>
      </FullSection>
    </div>
  </div>

  <FullSection>
    <div class="block w-full lg:pb-10 my-20">
      <div class="block">
        <h3 class="text-accent text-6xl 2xl:text-8xl reveal">Zakázková výroba<br>
          ze dřeva</h3>
        <p class="mt-10 reveal text-xl uppercase">
          Schody, obložení, kuchyňské linky,
          vestavěné skříně <br> i další prvky přesně podle prostoru a přání zákazníka.
        </p>
      </div>
      <div class="block">
        <TextGridFour v-for="(item, i) in woodFactory" :key="i" v-bind="item"/>
      </div>
    </div>

  </FullSection>


</template>
