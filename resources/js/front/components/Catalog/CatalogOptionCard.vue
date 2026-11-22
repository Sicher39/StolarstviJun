<script setup lang="ts">
import { computed } from 'vue'

import CatalogCardMedia from './CatalogCardMedia.vue'
import type { CatalogOption } from './types'

// One decor, glass, frame or piece of hardware. The option is published once for all the models that
// offer it, so the card names those models instead of the page repeating the same picture for each of
// them.
const props = defineProps<{
  option: CatalogOption
}>()

const modelNamesLabel = computed<string>(() => {
  if (props.option.model_names.length === 0) {
    return 'Bez přiřazeného modelu'
  }

  return `Modely: ${props.option.model_names.join(', ')}`
})
</script>

<template>
  <article class="flex flex-col border border-white/10 bg-dark/40">
    <CatalogCardMedia
      :image-url="props.option.image_url"
      :alt="props.option.name"
      aspect="square"
    />

    <div class="flex flex-1 flex-col gap-1 p-3">
      <p class="text-base leading-tight text-white">{{ props.option.name }}</p>
      <p class="mt-auto text-xs leading-relaxed text-white/50">{{ modelNamesLabel }}</p>
    </div>
  </article>
</template>
