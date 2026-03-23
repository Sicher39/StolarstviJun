<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    widthHorizontally?: string
    heightHorizontally?: string
    widthVertically?: string
    heightVertically?: string
    animation?: string
    background?: string
    label?: string
  }>(),
  {
    widthHorizontally: 'w-[10px] group-hover:z-30',
    heightHorizontally: 'h-[1px] group-hover:z-30',
    widthVertically: 'w-[1px] group-hover:z-30',
    heightVertically: 'h-[10px] group-hover:z-30',
    animation: 'opacity-100 group-hover:opacity-100 transition-all  duration-[1s,10s] ',
    background: 'bg-primary group-hover:bg-dark',
    label: ''
  }
)

//Top left corner
const topLeftHorizontallyClass = computed(() =>
  [
    'absolute top-0 -left-[5px]',
    props.background,
    props.heightHorizontally,
    props.widthHorizontally,
    props.animation
  ].filter(Boolean)
)

const topLeftVerticallyClass = computed(() =>
  [
    'absolute -top-[5px] left-0',
    props.background,
    props.heightVertically,
    props.widthVertically,
    props.animation
  ].filter(Boolean)
)

//Top right corner
const topRightHorizontallyClass = computed(() =>
  [
    'absolute top-0 -right-[5px]',
    props.background,
    props.heightHorizontally,
    props.widthHorizontally,
    props.animation
  ].filter(Boolean)
)

const topRightVerticallyClass = computed(() =>
  [
    'absolute -top-[5px] right-0',
    props.background,
    props.heightVertically,
    props.widthVertically,
    props.animation
  ].filter(Boolean)
)

//bottom left corner
const bottomLeftHorizontallyClass = computed(() =>
  [
    'absolute bottom-0 -left-[5px]',
    props.background,
    props.heightHorizontally,
    props.widthHorizontally,
    props.animation
  ].filter(Boolean)
)

const bottomLeftVerticallyClass = computed(() =>
  [
    'absolute -bottom-[5px] left-0',
    props.background,
    props.heightVertically,
    props.widthVertically,
    props.animation
  ].filter(Boolean)
)

//bottom right corner
const bottomRightHorizontallyClass = computed(() =>
  [
    'absolute bottom-0 -right-[5px] ',
    props.background,
    props.heightHorizontally,
    props.widthHorizontally,
    props.animation
  ].filter(Boolean)
)

const bottomRightVerticallyClass = computed(() =>
  [
    'absolute -bottom-[5px] right-0',
    props.background,
    props.heightVertically,
    props.widthVertically,
    props.animation
  ].filter(Boolean)
)
</script>

<template>

  <div
    :class="[
      'relative w-full h-[30px] bg-primary/10 transition-all duration-700 cursor-pointer',
    ]"
  >
    <div class="absolute inset-0 group-hover:z-30">
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

    <div
      :class="[
        'absolute top-0 left-0 h-full w-0 opacity-0 group-hover:opacity-100 group-hover:w-full transition-all duration-700 bg-white group-hover:bg-primary/80'
      ]"
    ></div>


    <div class="relative z-10 px-4 flex items-center justify-center">
      <label
        :class="[
          'flex uppercase text-sm smW:text-xs xl:text-lg md:pt-2 xl:pt-0 font-bold text-primary group-hover:text-white transition-all duration-700'
        ]"
      >
        <slot />

      </label>
    </div>
  </div>

</template>

