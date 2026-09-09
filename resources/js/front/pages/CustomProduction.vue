<script setup lang="ts">

import MainLayout from "@/front/layouts/MainLayout.vue";
import SecondHeaderSection from "@/front/components/Sections/SecondHeaderSection.vue";
import ScrollLinesRevealSection from "@/front/components/Sections/ScrollLinesRevealSection.vue";
import FullSection from "@/front/components/Sections/FullSection.vue";
import VerticalScrollLine from "@/front/components/gsap/VerticalScrollLine.vue";
import {gsap} from "gsap";
import {nextTick, onBeforeUnmount, onMounted, ref} from "vue";
import {ScrollTrigger} from "gsap/ScrollTrigger";
import TwoGridSection from "@/front/components/Sections/TwoGridSection.vue";
import VerticalScrollCards from "@/front/components/VerticalScrollCards/VerticalScrollCards.vue";
import ReferenceGallerySlider from "@/front/components/ReffereceCardGallery/ReferenceGallerySlider.vue";

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

const sections = ref([
  {
    img: 'Doors03',
    title: 'Kuchyně',
    listTitle: 'Možnosti provedení:',
    texts:[
      'Kuchyň je jedním z nejvíce namáhaných prostorů v domě a zároveň místem, kde se setkává funkčnost s estetikou. Vyrábíme kuchyňské linky na míru, které respektují prostor, způsob používání i individuální styl zákazníka.\n' +
      'Každá kuchyně vzniká na základě pečlivého návrhu, správné volby materiálů a promyšlené konstrukce. Důraz klademe na odolnost, ergonomii a dlouhou životnost, ať už se jedná o moderní nebo klasické provedení.',

    ],
    lists: [
        'masivní dřevo i dýhované materiály',
        'kuchyně do rodinných domů i bytů',
        'atypická řešení, rohové sestavy, vestavby',
        'sladění s ostatním nábytkem v interiéru'
    ]
  },
  {
    img: 'Doors03',
    title: 'Nábytek ',
    listTitle: 'Typy nábytku:',
    texts:[
      'Vyrábíme nábytek na míru, který přirozeně zapadne do prostoru a odpovídá jeho využití. Ať už se jedná o vestavěné skříně, knihovny, stoly, komody nebo koupelnový nábytek, vždy klademe důraz na funkčnost a čisté zpracování.',
      'Každý kus navrhujeme individuálně s ohledem na rozměry, zatížení i charakter interiéru. Díky zakázkové výrobě dokážeme využít prostor efektivně a bez kompromisů.\n'


    ],
    lists: [
      'vestavěné a samostatně stojící skříně',
      'jídelní a pracovní stoly',
      'knihovny, komody, police',
      'koupelnový a ložnicový nábytek'
    ]
  },
  {
    img: 'Doors03',
    title: 'Schodiště a zábradlí ',
    listTitle: 'Možnosti provedení:',
    texts:[
      'Schodiště je výrazný stavební i designový prvek interiéru. Vyrábíme dřevěná schodiště a zábradlí na míru s důrazem na bezpečnost, stabilitu a estetiku.',
      'Každé schodiště řešíme individuálně podle prostoru, konstrukčních možností a stylu stavby. Dbáme na přesnost výroby, pevné spoje a kvalitní povrchovou úpravu, aby schodiště dlouhodobě obstálo v každodenním provozu.\n'


    ],
    lists: [
      'přímá i točitá schodiště',
      'schodiště s dřevěným nebo kombinovaným zábradlím',
      'různé typy povrchových úprav',
      'řešení pro novostavby i rekonstrukce'
    ]
  }
])

</script>

<template>

  <SecondHeaderSection
      img="wood-door"
  >
    <template v-slot:header>
      Zakázková výroba <br>
      pro nás není výjimka,<br>
      ale standard
    </template>
    <template v-slot:title>
      Každý projekt řešíme individuálně – nejen po stránce vzhledu, ale hlavně z technického hlediska.
    </template>
  </SecondHeaderSection>

  <FullSection>
    <div class="block mt-[300px] mb-48">
      <div class="relative w-full">
        <div class="absolute left-0 top-0 bottom-0 flex justify-center w-full">
            <VerticalScrollLine/>
        </div>
        <div class="block space-y-48">
          <TwoGridSection
              v-for="(section, i) in sections"
              :key="i"
              v-bind="section"
              :left=" i % 2 === 0 "
          />
        </div>

      </div>
    </div>
  </FullSection>


  <ScrollLinesRevealSection>
    Velký důraz klademe na detaily a technické návaznosti – spoje, kotvení, povrchové úpravy, kování nebo montážní řešení.
  </ScrollLinesRevealSection>

  <FullSection>
    <div class="block my-48">
      <h4 class="text-accent text-6xl 2xl:text-8xl reveal">Galerie <br> realizovaných zakázek,</h4>
      <h5 class="mt-5">které vydrží roky, ne sezóny.</h5>
    </div>
    <div class="block w-full">
      <ReferenceGallerySlider/>
    </div>
  </FullSection>
</template>