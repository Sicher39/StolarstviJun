<script setup lang="ts">
import { computed } from 'vue'
import ButtonMain from '@/apps/front/Components/Buttons/ButtonMain.vue'
import { Link } from '@inertiajs/vue3';

const props = withDefaults(
  defineProps<{
    img?: string
    headerOne: string
    headerTwo?: string
    text: string
    result?: string
    link: string
    hidden?: boolean
    widthHorizontally?: string
    heightHorizontally?: string
    widthVertically?: string
    heightVertically?: string
    animation?: string
    background?: string
  }>(),
  {
    img: '',
    link: '',
    widthHorizontally: 'w-[30px] md:w-[60px]',
    heightHorizontally: 'h-[1px]',
    widthVertically: 'w-[1px]',
    heightVertically: 'h-[30px] md:h-[60px]',
    animation: 'opacity100 md:opacity-0 group-hover:opacity-100 transition-all  duration-[1s,10s]',
    background: 'bg-dark dark:bg-white z-10'
  }
)

///Top left corner
const topLeftHorizontallyClass = computed(() =>
  [
    'absolute left-0 top-0',
    props.heightHorizontally,
    props.widthHorizontally,
    props.background,
    props.animation
  ].filter(Boolean)
)

const topLeftVerticallyClass = computed(() =>
  [
    'absolute left-0 top-0',
    props.heightVertically,
    props.widthVertically,
    props.background,
    props.animation
  ].filter(Boolean)
)

//Top right corner
const topRightHorizontallyClass = computed(() =>
  [
    'absolute right-0 top-0',
    props.heightHorizontally,
    props.widthHorizontally,
    props.background,
    props.animation
  ].filter(Boolean)
)

const topRightVerticallyClass = computed(() =>
  [
    'absolute right-0 top-0 ',
    props.heightVertically,
    props.widthVertically,
    props.background,
    props.animation
  ].filter(Boolean)
)

//bottom left corner
const bottomLeftHorizontallyClass = computed(() =>
  [
    'absolute left-0 bottom-0',
    props.heightHorizontally,
    props.widthHorizontally,
    props.background,
    props.animation
  ].filter(Boolean)
)

const bottomLeftVerticallyClass = computed(() =>
  [
    'absolute left-0 bottom-0',
    props.heightVertically,
    props.widthVertically,
    props.background,
    props.animation
  ].filter(Boolean)
)

//bottom right corner
const bottomRightHorizontallyClass = computed(() =>
  [
    'absolute right-0 bottom-0',
    props.heightHorizontally,
    props.widthHorizontally,
    props.background,
    props.animation
  ].filter(Boolean)
)

const bottomRightVerticallyClass = computed(() =>
  [
    'absolute right-0 bottom-0',
    props.heightVertically,
    props.widthVertically,
    props.background,
    props.animation
  ].filter(Boolean)
)
</script>

<template>
  <div class="group">
    <!--    border block-->
    <div
      :class="[
        'w-full  md:aspect-none bg-white group-hover:bg-primary/30 aspect-square border-[1px] border-dark/10 block relative my-2 smW:my-0 transition-all duration-[1s,10s]'
      ]"
    >
      <div v-show="props.headerOne != ''" class="absolute inset-1 transition-all duration-[1s,10s]">
        <!-- TL -->
        <span :class="topLeftHorizontallyClass" />
        <span :class="topLeftVerticallyClass" />

        <!-- TR -->
        <span :class="topRightHorizontallyClass" />
        <span :class="topRightVerticallyClass" />

        <!-- BL -->
        <span :class="bottomLeftHorizontallyClass" />
        <span :class="bottomLeftVerticallyClass" />

        <!-- BR -->
        <span :class="bottomRightHorizontallyClass" />
        <span :class="bottomRightVerticallyClass" />
      </div>

      <!--      text-section -->
      <div class="flex items-center top-0 left-0 w-full h-full p-4">
        <div class="block w-full">
          <div class="flex justify-start w-full text-dark dark:group-hover:text-white mb-2">
            <img
              :src="`/img/photos/main/${props.img}.webp`"
              :alt="`obrázek k ${props.headerOne} ${props.headerTwo}`"
            />
          </div>

          <h3
            class="text-primary uppercase group-hover:text-primary dark:group-hover:text-white font-black text-2xl smW:text-lg md:text-sm lg:text-xl xl:text-3xl 2xl:text-4xl"
          >
            {{ props.headerOne }} <br />
            {{ props.headerTwo }}
          </h3>
          <div class="block w-full pt-5">
            <Link :href="props.link">
              <ButtonMain>přejít</ButtonMain>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
