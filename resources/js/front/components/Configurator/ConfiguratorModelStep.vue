<script setup lang="ts">
import { computed } from 'vue'

import type { DoorModel } from './types'
import { formatPriceWithVat } from './utils'

const props = defineProps<{
  doorModels: DoorModel[]
  selectedModelId: number | null
}>()

const emit = defineEmits<{
  'update:selectedModelId': [value: number | null]
}>()

// Model cards are illustrated by the reference image of their first variant.
const modelCards = computed(() => {
  return props.doorModels.map((model) => ({
    model,
    previewUrl: model.variants[0]?.source_reference_url ?? null,
  }))
})
</script>

<template>
  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <button
      v-for="card in modelCards"
      :key="card.model.id"
      type="button"
      class="flex flex-col border text-left transition"
      :class="selectedModelId === card.model.id ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'"
      @click="emit('update:selectedModelId', card.model.id)"
    >
      <span class="block h-28 w-full overflow-hidden border-b border-white/10 bg-white/5 sm:h-32 lg:h-36">
        <img
          v-if="card.previewUrl"
          :src="card.previewUrl"
          :alt="card.model.name"
          class="block h-full w-full object-contain"
          loading="lazy"
        />
        <span v-else class="flex h-full w-full items-center justify-center text-[10px] uppercase tracking-[0.16em] text-white/40">Bez náhledu</span>
      </span>

      <span class="flex flex-1 flex-col gap-2 p-3">
        <span class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
          <span class="text-base leading-tight text-white uppercase lg:text-lg">{{ card.model.name }}</span>
          <span v-if="card.model.category" class="text-[10px] uppercase tracking-[0.16em] text-accent">{{ card.model.category }}</span>
        </span>
        <span class="mt-auto flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[10px] uppercase tracking-[0.16em] text-white/55">
          <span>{{ card.model.variants.length }} variant</span>
          <span>{{ formatPriceWithVat(card.model.base_price_without_vat) }} vč. DPH</span>
        </span>
      </span>
    </button>
  </div>
</template>
