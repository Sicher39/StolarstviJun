<script setup lang="ts">
import FullSection from "@/front/components/Sections/FullSection.vue";
import ButtonMain from "@/front/components/Buttons/ButtonMain.vue";
import {Link} from "@inertiajs/vue3";
import {nextTick, onBeforeUnmount, onMounted, ref} from "vue";
import {gsap} from "gsap";
import {ScrollTrigger} from "gsap/ScrollTrigger";

const props = withDefaults(
    defineProps<{
      img: string
      button?: string
      link?: string
      groupDetailsReveal?: boolean
    }>(),
    {
      img: '',
      button: '',
      link: '',
      groupDetailsReveal: false
    }
)

// Cascade timing: the reveal of one element starts after the reveal of the previous one (DOM order),
// so a group of elements (e.g. all map links) never arrives at the same time.
const REVEAL_STAGGER = 0.15
const REVEAL_GROUP_PAUSE = 0.3
// Opt-in grouped reveal (Contact): the header heading arrives on its own, then everything below it (company name
// and the whole details slot) arrives together in one shared block after this short pause.
const REVEAL_SHARED_BLOCK_DELAY = 0.25
const REVEAL_BLOCK_GAP_PX = 24
const REVEAL_OFFSET_Y = 40
const REVEAL_DURATION = 1.6
const REVEAL_TEXT_DURATION = 1.1
const REVEAL_TEXT_CLIP_START = 'inset(0 100% 0 0)'
const REVEAL_TEXT_CLIP_END = 'inset(0 -10% 0 0)'
const REVEAL_TEXT_BLUR_START = 'blur(16px)'
const REVEAL_TEXT_BLUR_END = 'blur(0px)'

const rootElement = ref<HTMLElement | null>(null)
let secondHeaderRevealContext: gsap.Context | null = null

const isTextReveal = (element: HTMLElement): boolean => element.classList.contains('reveal-text')

const sortByDocumentOrder = (elements: HTMLElement[]): HTMLElement[] =>
    [...elements].sort((firstElement, secondElement) => {
      const position = firstElement.compareDocumentPosition(secondElement)

      return (position & Node.DOCUMENT_POSITION_FOLLOWING) !== 0 ? -1 : 1
    })

const setRevealStartState = (element: HTMLElement): void => {
  const startState: gsap.TweenVars = {y: REVEAL_OFFSET_Y, opacity: 0}

  if (isTextReveal(element)) {
    startState.clipPath = REVEAL_TEXT_CLIP_START
    startState.filter = REVEAL_TEXT_BLUR_START
    startState.x = 0
  }

  gsap.set(element, startState)
}

// Delays follow the rendered order of the elements. A bigger vertical gap between two elements starts a new
// visual block (address / map links / contacts), so it gets one extra pause before it starts.
const buildCascadeDelays = (elements: HTMLElement[]): number[] => {
  let delay = 0
  let previousBottom: number | null = null

  return elements.map((element, index) => {
    const bounds = element.getBoundingClientRect()

    if (index > 0) {
      delay += REVEAL_STAGGER

      if (previousBottom !== null && bounds.top - previousBottom > REVEAL_BLOCK_GAP_PX) {
        delay += REVEAL_GROUP_PAUSE
      }
    }

    previousBottom = bounds.bottom

    return delay
  })
}

const revealElement = (element: HTMLElement, delay: number): gsap.core.Tween => {
  const textReveal = isTextReveal(element)

  const revealState: gsap.TweenVars = {
    y: 0,
    opacity: 1,
    duration: textReveal ? REVEAL_TEXT_DURATION : REVEAL_DURATION,
    ease: textReveal ? 'power3.out' : 'power2.out',
    delay
  }

  if (textReveal) {
    revealState.clipPath = REVEAL_TEXT_CLIP_END
    revealState.filter = REVEAL_TEXT_BLUR_END
  }

  return gsap.to(element, revealState)
}

const revealBatch = (batch: Element[]): void => {
  const orderedElements = sortByDocumentOrder(batch as HTMLElement[])
  const delays = buildCascadeDelays(orderedElements)

  orderedElements.forEach((element, index) => {
    revealElement(element, delays[index] ?? 0)
  })
}

onMounted(async (): Promise<void> => {
  await nextTick()

  if (!rootElement.value) {
    return
  }

  gsap.registerPlugin(ScrollTrigger)

  // Scoped selector, so the reveal also reaches markup rendered through the optional slots (e.g. Contact details).
  const scopedSelector = gsap.utils.selector(rootElement.value)

  secondHeaderRevealContext = gsap.context(() => {
    // One shared set in DOM order keeps the natural reading order of the cascade (title, address, map links, contacts).
    // Elements nested inside an opt-in `revealHeaderGroup` block are skipped: that block is animated as a whole.
    const headerRevealElements = scopedSelector<HTMLElement>('.revealHeader, .reveal-text')
        .filter((element): boolean => !element.closest('.revealHeaderGroup'))
    const sharedBlockRevealElements = props.groupDetailsReveal
        ? scopedSelector<HTMLElement>('.revealHeaderGroup')
        : []
    const mediaRevealElements = scopedSelector<HTMLElement>('.second-header-reveal-media')

    if (headerRevealElements.length) {
      headerRevealElements.forEach(setRevealStartState)

      ScrollTrigger.batch(headerRevealElements, {
        start: 'top 90%',
        once: true,
        onEnter: revealBatch
      })
    }

    // All elements of the shared block start in the same tween call, so the block never cascades line by line.
    if (sharedBlockRevealElements.length) {
      sharedBlockRevealElements.forEach(setRevealStartState)

      ScrollTrigger.create({
        trigger: sharedBlockRevealElements[0],
        start: 'top 90%',
        once: true,
        onEnter: (): void => {
          sharedBlockRevealElements.forEach((element) => {
            revealElement(element, REVEAL_SHARED_BLOCK_DELAY)
          })
        }
      })
    }

    if (mediaRevealElements.length) {
      gsap.set(mediaRevealElements, {y: 60, opacity: 0})

      gsap.to(mediaRevealElements, {
        y: 0,
        opacity: 1,
        duration: 1.6,
        ease: 'power2.out',
        scrollTrigger: {
          trigger: mediaRevealElements[0],
          start: 'top 80%',
          once: true
        }
      })
    }
  }, rootElement.value)
})

onBeforeUnmount((): void => {
  secondHeaderRevealContext?.revert()
  secondHeaderRevealContext = null
})
</script>

<template>
  <FullSection>
    <div ref="rootElement" class="relative w-full mb-48">
      <div class="relative pt-32 z-20 w-full">
        <h1 class="text-white items-end lg:leading-[150px] text-5xl lg:text-8xl revealHeader  reveal-text">
          <slot name="header"/>
        </h1>

        <!-- Opt-in (Contact): `revealHeaderGroup` turns the company name and the whole details slot into one
             commonly animated block instead of a per element cascade. -->
        <div :class="props.groupDetailsReveal ? 'revealHeaderGroup reveal-text' : ''">
          <div class="block w-1/3 pl-20">
            <h2 :class="['text-white text-4xl mt-20', props.groupDetailsReveal ? '' : 'revealHeader reveal-text']">
              <slot name="title"/>
            </h2>
          </div>

          <div v-if="$slots.details" class="block w-full lg:w-1/3 pl-0 lg:pl-20">
            <slot name="details"/>
          </div>
        </div>

        <div v-if="button !== '' && link !== ''" class="block w-[250px] group mt-10">
          <Link :href="route(`front.${props.link}`)">
            <ButtonMain><span class="uppercase font-main">{{ props.button }}</span></ButtonMain>
          </Link>
        </div>
      </div>

      <div class="absolute w-full top-[300px] z-10 revealHeader reveal">
        <div class="flex w-full justify-end">
          <img :src="`/img/bg/headers/${img}.webp`" class="w-[600px] aspect-auto mr-[300px]" alt="">
        </div>
      </div>
    </div>

  </FullSection>
</template>
