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
    }>(),
    {
      img: '',
      button: '',
      link: ''
    }
)

const rootElement = ref<HTMLElement | null>(null)
let headerRevealContext: gsap.Context | null = null

onMounted(async (): Promise<void> => {
  await nextTick()

  if (!rootElement.value) {
    return
  }

  gsap.registerPlugin(ScrollTrigger)

  headerRevealContext = gsap.context(() => {
    gsap.set('.revealHeader', {y: 40, opacity: 0})
    gsap.set('.reveal-text', {
      clipPath: 'inset(0 100% 0 0)',
      filter: 'blur(16px)',
      x: 0
    })
    gsap.set('.header-reveal-media', {y: 60, opacity: 0})

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

    gsap.utils.toArray<HTMLElement>('.reveal-text').forEach((element) => {
      gsap.to(element, {
        clipPath: 'inset(0 -10% 0 0)',
        filter: 'blur(0px)',
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

    gsap.to('.header-reveal-media', {
      y: 0,
      opacity: 1,
      duration: 1.6,
      ease: 'power2.out',
      scrollTrigger: {
        trigger: '.header-reveal-media',
        start: 'top 80%',
        once: true
      }
    })
  }, rootElement.value)
})

onBeforeUnmount((): void => {
  headerRevealContext?.revert()
  headerRevealContext = null
})
</script>

<template>
  <FullSection>
    <div ref="rootElement" class="relative w-full">
      <div class="relative pt-32 z-20 w-full">
        <div class="revealHeader">
          <h1 class="text-white items-end lg:leading-[150px] text-5xl lg:text-8xl reveal-text">
            <slot name="header"/>
          </h1>
        </div>

        <div class="revealHeader">
          <h2 class="text-white items-end lg:leading-[10px] text-5xl lg:text-8xl reveal-text mt-20">
            <slot name="title"/>
          </h2>
        </div>

        <div v-if="button !== '' && link !== ''" class="block w-[250px] group">
          <Link :href="route(`front.${props.link}`)">
            <ButtonMain>{{ props.button }}</ButtonMain>
          </Link>
        </div>
      </div>

      <div class="absolute w-full top-[400px] z-10 header-reveal-media">
        <div class="flex w-full justify-center">
          <img :src="`/img/bg/headers/${img}.webp`" class="w-[1080px]" alt="">
        </div>
      </div>
    </div>

  </FullSection>
</template>
