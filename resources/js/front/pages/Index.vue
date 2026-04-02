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

defineOptions({
  layout: MainLayout
})

const scrollLinesRevealTweens = new WeakMap<HTMLElement, gsap.core.Tween>()
const scrollLinesRevealSizes = new WeakMap<HTMLElement, { width: number; height: number }>()
const scrollLinesRevealPendingElements = new Set<HTMLElement>()
let scrollLinesRevealResizeTimeout: ReturnType<typeof setTimeout> | null = null
let scrollLinesRevealObserverTimeout: ReturnType<typeof setTimeout> | null = null
let scrollLinesRevealObserver: ResizeObserver | null = null
let isScrollLinesRevealUnmounted = false
let revealGsapContext: gsap.Context | null = null

const getRenderedElementSize = (element: HTMLElement): { width: number; height: number } => ({
  width: Math.round(element.getBoundingClientRect().width),
  height: Math.round(element.getBoundingClientRect().height)
})

const hasRenderedSizeChanged = (element: HTMLElement, nextSize: { width: number; height: number }): boolean => {
  const previousSize = scrollLinesRevealSizes.get(element)

  if (!previousSize) {
    return true
  }

  return previousSize.width !== nextSize.width || previousSize.height !== nextSize.height
}

const updateRenderedSizeCache = (element: HTMLElement): void => {
  scrollLinesRevealSizes.set(element, getRenderedElementSize(element))
}

const scheduleObservedElementsRebuild = (): void => {
  if (scrollLinesRevealObserverTimeout !== null) {
    clearTimeout(scrollLinesRevealObserverTimeout)
  }

  scrollLinesRevealObserverTimeout = setTimeout(() => {
    scrollLinesRevealPendingElements.forEach((element) => {
      if (!element.isConnected) {
        scrollLinesRevealPendingElements.delete(element)
        return
      }

      setupScrollLinesRevealElement(element)
      scrollLinesRevealPendingElements.delete(element)
    })

    scrollLinesRevealObserverTimeout = null
  }, 120)
}

const initializeScrollLinesRevealObserver = (): void => {
  if (typeof ResizeObserver === 'undefined') {
    return
  }

  scrollLinesRevealObserver?.disconnect()

  scrollLinesRevealObserver = new ResizeObserver((entries) => {
    let shouldSchedule = false

    entries.forEach((entry) => {
      const element = entry.target

      if (!(element instanceof HTMLElement)) {
        return
      }

      const nextSize = {
        width: Math.round(entry.contentRect.width),
        height: Math.round(entry.contentRect.height)
      }

      if (!hasRenderedSizeChanged(element, nextSize)) {
        return
      }

      scrollLinesRevealSizes.set(element, nextSize)
      scrollLinesRevealPendingElements.add(element)
      shouldSchedule = true
    })

    if (shouldSchedule) {
      scheduleObservedElementsRebuild()
    }
  })
}

const buildScrollRevealLines = (element: HTMLElement): void => {
  const cachedSource = element.dataset.scrollSource
  const sourceText = cachedSource
      ?? element.textContent
          ?.replace(/[\t\n\f\r ]+/g, ' ')
          .replace(/^ +| +$/g, '')

  if (!sourceText) {
    return
  }

  if (!cachedSource) {
    element.dataset.scrollSource = sourceText
  }

  const measurableUnits = sourceText.split(/ +/).filter((unit) => unit.length > 0)

  if (!measurableUnits.length) {
    return
  }

  element.textContent = ''

  const measureLayer = document.createElement('span')
  measureLayer.className = 'block'
  element.appendChild(measureLayer)

  measurableUnits.forEach((unit, index) => {
    const unitElement = document.createElement('span')
    unitElement.className = 'inline-block'
    unitElement.textContent = unit
    measureLayer.appendChild(unitElement)

    if (index < measurableUnits.length - 1) {
      measureLayer.appendChild(document.createTextNode(' '))
    }
  })

  const measuredUnits = Array.from(measureLayer.querySelectorAll<HTMLElement>('span'))
  const lines: string[] = []
  let currentTop: number | null = null
  let currentLineUnits: string[] = []

  measuredUnits.forEach((unitElement) => {
    if (currentTop === null) {
      currentTop = unitElement.offsetTop
    }

    if (Math.abs(unitElement.offsetTop - currentTop) > 1) {
      lines.push(currentLineUnits.join(' '))
      currentLineUnits = []
      currentTop = unitElement.offsetTop
    }

    currentLineUnits.push(unitElement.textContent ?? '')
  })

  if (currentLineUnits.length) {
    lines.push(currentLineUnits.join(' '))
  }

  element.textContent = ''

  lines.forEach((line) => {
    const lineElement = document.createElement('span')
    lineElement.className = 'relative block w-fit max-w-full mx-auto overflow-hidden'
    lineElement.style.lineHeight = 'inherit'

    const baseElement = document.createElement('span')
    baseElement.className = 'block whitespace-nowrap text-primary/10'
    baseElement.style.lineHeight = 'inherit'
    baseElement.textContent = line

    const overlayElement = document.createElement('span')
    overlayElement.className = 'scroll-line-overlay absolute top-0 left-0 block w-full overflow-hidden whitespace-nowrap text-white'
    overlayElement.style.lineHeight = 'inherit'
    overlayElement.setAttribute('aria-hidden', 'true')
    overlayElement.textContent = line

    lineElement.appendChild(baseElement)
    lineElement.appendChild(overlayElement)
    element.appendChild(lineElement)
  })
}

const setupScrollLinesRevealElement = (element: HTMLElement): void => {
  const existingTween = scrollLinesRevealTweens.get(element)

  if (existingTween) {
    existingTween.scrollTrigger?.kill()
    existingTween.kill()
    scrollLinesRevealTweens.delete(element)
  }

  buildScrollRevealLines(element)

  const overlays = element.querySelectorAll<HTMLElement>('.scroll-line-overlay')

  gsap.set(overlays, {width: '0%'})

  const tween = gsap.to(overlays, {
    width: '100%',
    ease: 'none',
    stagger: 0.12,
    scrollTrigger: {
      trigger: element,
      start: 'top 85%',
      end: 'top 20%',
      scrub: true
    }
  })

  scrollLinesRevealTweens.set(element, tween)
  updateRenderedSizeCache(element)
}

const reinitializeScrollLinesReveal = (): void => {
  gsap.utils.toArray<HTMLElement>('.scroll-lines-reveal').forEach((element) => {
    setupScrollLinesRevealElement(element)
    scrollLinesRevealObserver?.observe(element)
  })
}

const handleScrollLinesRevealResize = (): void => {
  if (scrollLinesRevealResizeTimeout !== null) {
    clearTimeout(scrollLinesRevealResizeTimeout)
  }

  scrollLinesRevealResizeTimeout = setTimeout(() => {
    reinitializeScrollLinesReveal()
    scrollLinesRevealResizeTimeout = null
  }, 180)
}

onMounted(async () => {
  await nextTick()

  gsap.registerPlugin(ScrollTrigger)

  revealGsapContext = gsap.context(() => {
    gsap.set('.reveal', {y: 60, opacity: 0})
    gsap.set('.revealHeader', {y: 40, opacity: 0})
    gsap.set('.reveal-text', {
      clipPath: 'inset(0 100% 0 0)',
      filter: 'blur(16px)',
      opacity: 0,
      x: 0
    })

    ScrollTrigger.batch('.revealHeader', {
      start: 'top 90%',
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

    gsap.utils.toArray<HTMLElement>('.reveal-text').forEach((element) => {
      gsap.to(element, {
        clipPath: 'inset(0 -10% 0 0)',
        filter: 'blur(0px)',
        opacity: 1,
        x: 0,
        duration: 1.1,
        ease: 'power3.out',
        scrollTrigger: {
          trigger: element,
          start: 'top 90%',
          once: true
        }
      })
    })
  })

  initializeScrollLinesRevealObserver()
  reinitializeScrollLinesReveal()
  window.addEventListener('resize', handleScrollLinesRevealResize)

  const fontFaceSet = document.fonts

  if (fontFaceSet?.ready) {
    void fontFaceSet.ready.then(() => {
      if (!isScrollLinesRevealUnmounted) {
        reinitializeScrollLinesReveal()
      }
    })
  }
})


onBeforeUnmount((): void => {
  isScrollLinesRevealUnmounted = true

  window.removeEventListener('resize', handleScrollLinesRevealResize)

  if (scrollLinesRevealResizeTimeout !== null) {
    clearTimeout(scrollLinesRevealResizeTimeout)
    scrollLinesRevealResizeTimeout = null
  }

  if (scrollLinesRevealObserverTimeout !== null) {
    clearTimeout(scrollLinesRevealObserverTimeout)
    scrollLinesRevealObserverTimeout = null
  }

  scrollLinesRevealPendingElements.clear()
  scrollLinesRevealObserver?.disconnect()
  scrollLinesRevealObserver = null

  gsap.utils.toArray<HTMLElement>('.scroll-lines-reveal').forEach((element) => {
    const tween = scrollLinesRevealTweens.get(element)

    if (tween) {
      tween.scrollTrigger?.kill()
      tween.kill()
      scrollLinesRevealTweens.delete(element)
    }
  })

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


  <FullSection>
    <div class="relative w-full">
      <div class="relative pt-32 z-20 w-full">
        <h1 class="text-white items-end lg:leading-[150px]  text-5xl lg:text-8xl revealHeader">
          Každý kus dřeva <br>
          má svůj příběh, <br>
          my mu dáme tvar <br>
          podle vašich představ
        </h1>
        <div class="block w-[250px] group mt-10">
          <ButtonMain><span class="uppercase font-main">Konfigurátor dveří</span></ButtonMain>
        </div>
      </div>

      <div class="absolute w-full -mt-[400px] z-10 reveal">
        <div class="flex w-full justify-center">
          <img src="/img/bg/01/red-door.webp" class="w-[1080px] " alt="">
        </div>
      </div>
    </div>

  </FullSection>


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

  <FullSection>
    <div class="w-full pb-32">
      <h4 class="w-full text-center leading-12 xl:leading-20 3xl:leading-24 text-4xl md:text-5xl xl:text-6xl 3xl:text-7xl scroll-lines-reveal">
        Výroba dveří u&nbsp;nás stojí na poctivém řemesle, zkušenostech a&nbsp;citu pro detail. Práce se dřevem má v&nbsp;naší
        firmě
        dlouhou tradici, kterou dnes rozvíjí už druhá generace.
      </h4>

    </div>
  </FullSection>

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
              <VerticalScrollLine />
            </div>
            <div class="col-span-9 grid grid-cols-12 pt-20">
              <div class="col-span-9">
                <p>Vyrábíme interiérové i&nbsp;vchodové dveře na míru. <br> Každý kus vzniká v&nbsp;naší dílně s&nbsp;důrazem
                  na detail, <br> funkčnost a&nbsp;dlouhou životnost. Spojujeme poctivé<br> stolařské řemeslo s&nbsp;moderními
                  technologiemi.</p>
                <div class="block w-[250px] group mt-20">
                  <ButtonMain><span class="uppercase font-main">Katalog produktů</span></ButtonMain>
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

                  <p class="mt-10">Navrhněte si dveře přesně podle svých představ – od typu (interiérové, vchodové či protipožární) až po každý detail provedení.
                    V konfigurátoru si snadno zvolíte materiál, design i kování, aby vše dokonale ladilo s vaším prostorem.
                    Okamžitě tak získáte přehled o výsledné podobě i orientační ceně dveří, které vám opravdu vyhovují.</p>
                  <div class="block w-[250px] group mt-20">
                    <ButtonMain><span class="uppercase font-main">spustit konfigurátor</span></ButtonMain>
                  </div>
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
      <div class="block w-[250px] group">
        <ButtonMain><span class="uppercase font-main">Zakázková výroba</span></ButtonMain>
      </div>
    </div>

  </FullSection>


</template>
