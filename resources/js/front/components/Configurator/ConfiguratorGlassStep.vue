<script setup lang="ts">
import { computed } from 'vue'

import type { GlassOption } from './types'
import { formatPriceWithVat, swatchStyle } from './utils'

const props = defineProps<{
  glasses: GlassOption[]
  selectedGlassId: number | null
  vatRate: number
  // The step is a permanent part of the configurator menu, so it is opened even when nothing can be chosen yet:
  // a configuration without a variant and a variant that is made without glazing both explain themselves here
  // instead of the step disappearing from the navigation.
  hasSelectedVariant: boolean
}>()

const emit = defineEmits<{
  'update:selectedGlassId': [value: number | null]
}>()

// Only an empty list of glasses reaches this state, because a variant that cannot be glazed never publishes any.
const emptyState = computed((): { title: string; hint: string } => {
  if (!props.hasSelectedVariant) {
    return {
      title: 'Zatím není z čeho vybírat.',
      hint: 'Nejdřív zvolte model a variantu dveří. Prosklení se nabídne podle zvolené varianty.',
    }
  }

  return {
    title: 'Sklo není pro tuto variantu k dispozici.',
    hint: 'Vybraná varianta se vyrábí bez prosklení, proto se pro ni sklo nevybírá. Pokračujte tlačítkem „Pokračovat“ na doplňky a poptávku.',
  }
})
</script>

<template>
  <div class="grid gap-3">
    <div v-if="glasses.length === 0" class="max-w-2xl border border-white/10 bg-dark/40 p-4">
      <p class="text-base text-white">{{ emptyState.title }}</p>
      <p class="mt-2 text-sm leading-6 text-white/60">{{ emptyState.hint }}</p>
    </div>

    <div v-else class="grid gap-3 sm:grid-cols-2">
      <button
        v-for="glass in glasses"
        :key="glass.id"
        type="button"
        class="flex items-center gap-3 border p-3 text-left transition"
        :class="selectedGlassId === glass.id ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'"
        @click="emit('update:selectedGlassId', glass.id)"
      >
        <span class="h-12 w-12 shrink-0 overflow-hidden border border-white/10 bg-white/5" :style="swatchStyle(glass.preview_image_url ?? glass.texture_image_url, 'glass')" />
        <span class="min-w-0 flex-1">
          <span class="block text-base text-white">{{ glass.name }}</span>
          <span class="mt-1 block text-xs text-white/55">{{ glass.price_modifier > 0 ? '+' : '' }}{{ formatPriceWithVat(glass.price_modifier, vatRate) }} vč. DPH</span>
        </span>
      </button>
    </div>
  </div>
</template>
