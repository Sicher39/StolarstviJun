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
  }>(),
  {
    widthHorizontally: 'w-[10px]',
    heightHorizontally: 'h-[1px]',
    widthVertically: 'w-[1px]',
    heightVertically: 'h-[10px]',
    animation: 'opacity-100 group-hover:opacity-100 transition-all  duration-[1s,10s] z-10',
    background: 'bg-primary group-hover:bg-white'
  }
)

//Top left corner
const topLeftHorizontallyClass = computed(() =>
  [
    'absolute top-0 left-0',
    props.background,
    props.heightHorizontally,
    props.widthHorizontally,
    props.animation
  ].filter(Boolean)
)

const topLeftVerticallyClass = computed(() =>
  [
    'absolute top-0 left-0',
    props.background,
    props.heightVertically,
    props.widthVertically,
    props.animation
  ].filter(Boolean)
)

//Top right corner
const topRightHorizontallyClass = computed(() =>
  [
    'absolute top-0 right-0',
    props.background,
    props.heightHorizontally,
    props.widthHorizontally,
    props.animation
  ].filter(Boolean)
)

const topRightVerticallyClass = computed(() =>
  [
    'absolute top-0 right-0',
    props.background,
    props.heightVertically,
    props.widthVertically,
    props.animation
  ].filter(Boolean)
)

//bottom left corner
const bottomLeftHorizontallyClass = computed(() =>
  [
    'absolute bottom-0 left-0',
    props.background,
    props.heightHorizontally,
    props.widthHorizontally,
    props.animation
  ].filter(Boolean)
)

const bottomLeftVerticallyClass = computed(() =>
  [
    'absolute bottom-0 left-0',
    props.background,
    props.heightVertically,
    props.widthVertically,
    props.animation
  ].filter(Boolean)
)

//bottom right corner
const bottomRightHorizontallyClass = computed(() =>
  [
    'absolute bottom-0 right-0 ',
    props.background,
    props.heightHorizontally,
    props.widthHorizontally,
    props.animation
  ].filter(Boolean)
)

const bottomRightVerticallyClass = computed(() =>
  [
    'absolute bottom-0 right-0',
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
      'relative h-[30px]  transition-all  duration-[1s,10s] bg-primary/20',
    ]"
  >
    <div class="absolute inset-0">
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
        'absolute top-0 left-0 w-0 opacity-0 group-hover:opacity-100 group-hover:w-full h-full transition-all  duration-[1s,10s] bg-white group-hover:bg-red'
      ]"
    ></div>

    <div class="absolute inset-0 w-full mt-1 smW:mt-[5px] lg:mt-0 px-4">
      <label
        :class="[
          'font-head text-sm smW:text-xs lg:text-lg font-normal uppercase font-main cursor-pointer flex items-center justify-center w-full text-primary group-hover:text-white group-hover:opacity-100 transition-all  duration-[1s,10s]'
        ]"
      >
        <slot />
      </label>
    </div>
  </div>
</template>
