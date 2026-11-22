<script setup lang="ts">
import { computed } from 'vue'

// The picture of one card of the catalog. The catalog is a listing of pictures and names, so a card
// never hides because its picture is missing: the frame stays and only the picture is replaced by the
// text placeholder. Every card of the page therefore keeps the same height.
const props = withDefaults(
  defineProps<{
    imageUrl?: string | null
    alt?: string
    /** A door is taller than it is wide, a decor or a handle is as tall as it is wide. */
    aspect?: 'portrait' | 'square'
    /**
     * A surface fills its frame and may be cropped by it, a door does not: the whole leaf has to stay
     * visible even in a frame that is not as tall as the door, so it is shrunk into the frame instead.
     */
    fit?: 'cover' | 'contain'
  }>(),
  {
    imageUrl: null,
    alt: '',
    aspect: 'square',
    fit: 'cover'
  }
)

// Both ratios are written as whole class names, so the utility scan of Tailwind sees them.
const ASPECT_CLASSES: Record<'portrait' | 'square', string> = {
  portrait: 'aspect-[3/4]',
  square: 'aspect-square'
}

const aspectClass = computed<string>(() => ASPECT_CLASSES[props.aspect])

// A contained picture keeps a margin to the border of the frame, so the leaf never touches it, and the
// dark fill of the frame stays visible as a plain backdrop around the door.
const imageClass = computed<string>(() =>
  props.fit === 'contain'
    ? 'block h-full w-full object-contain p-2 smW:p-3'
    : 'block h-full w-full object-cover'
)
</script>

<template>
  <div
    class="block w-full overflow-hidden border-b border-white/10 bg-white/5"
    :class="aspectClass"
  >
    <img
      v-if="props.imageUrl"
      :src="props.imageUrl"
      :alt="props.alt"
      :class="imageClass"
      loading="lazy"
    />
    <span
      v-else
      class="flex h-full w-full items-center justify-center px-2 text-center text-[10px] uppercase tracking-[0.16em] text-white/40"
    >
      Bez náhledu
    </span>
  </div>
</template>
