<script setup lang="ts">
import { computed } from 'vue'

import CatalogCardMedia from './CatalogCardMedia.vue'
import type { CatalogVariant } from './types'
import { variantSpecification } from '@/front/components/Configurator/utils'

// One variant of a model. The card is titled by the code of the variant, which is the name the
// catalogue gives it, and the line under it repeats the size and the opening of the door.
// The reference photo is the whole door, so it is shrunk into the frame instead of being cropped by it:
// the visitor compares the shape of the leaf, which a cut picture hides.
const props = defineProps<{
  variant: CatalogVariant
}>()

const specification = computed<string>(() => variantSpecification(props.variant))
</script>

<template>
  <article class="flex flex-col border border-white/10 bg-dark/40">
    <CatalogCardMedia
      :image-url="props.variant.image_url"
      :alt="props.variant.name"
      aspect="portrait"
      fit="contain"
    />

    <div class="flex flex-1 flex-col gap-1 p-3">
      <p class="text-base leading-tight text-white">{{ props.variant.name }}</p>
      <p class="mt-auto text-xs leading-relaxed text-white/50">{{ specification }}</p>
    </div>
  </article>
</template>
