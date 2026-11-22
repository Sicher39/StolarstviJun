<script setup lang="ts">
import { computed } from 'vue'

import type { DecorOption } from './types'
import { formatSurchargePriceWithVat, swatchStyle } from './utils'

const props = defineProps<{
  decors: DecorOption[]
  selectedDecorId: number | null
  vatRate: number
}>()

const emit = defineEmits<{
  'update:selectedDecorId': [value: number | null]
}>()

// The catalogue publishes the decors in the order of its rows, so the step lists them alphabetically by the name
// the visitor reads. The comparison uses the Czech collator and ignores case, so „Akát“ and „akát“ stand next to
// each other; two names that still compare equal keep the order of the catalogue, because the sort of an array is
// stable. Only the rendered collection is sorted: the selection, the price and every payload keep reading the
// array as it was received.
const decorNameCollator = new Intl.Collator('cs', { sensitivity: 'accent' })

const sortedDecors = computed<DecorOption[]>(() => {
  return [...props.decors].sort((left: DecorOption, right: DecorOption): number => {
    return decorNameCollator.compare(left.name, right.name)
  })
})
</script>

<template>
  <div class="grid gap-3 sm:grid-cols-2">
    <button
      v-for="decor in sortedDecors"
      :key="decor.id"
      type="button"
      class="flex items-center gap-3 border p-3 text-left transition"
      :class="selectedDecorId === decor.id ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'"
      @click="emit('update:selectedDecorId', decor.id)"
    >
      <span class="h-12 w-12 shrink-0 border border-white/10 bg-white/5" :style="swatchStyle(decor.texture_image_url ?? decor.preview_image_url, 'decor')" />
      <span class="min-w-0 flex-1">
        <span class="block text-base text-white">{{ decor.name }}</span>
        <span class="mt-1 block text-xs text-white/55">{{ formatSurchargePriceWithVat(decor.price_modifier, vatRate) }}</span>
      </span>
    </button>
  </div>
</template>
