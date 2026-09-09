<script setup lang="ts">
import type { SurchargeOption } from './types'
import { formatPriceWithVat } from './utils'

defineProps<{
  surcharges: SurchargeOption[]
  selectedSurchargeIds: number[]
}>()

const emit = defineEmits<{
  'toggle-surcharge': [surchargeId: number]
}>()
</script>

<template>
  <div class="grid gap-3">
    <button
      v-for="surcharge in surcharges"
      :key="surcharge.id"
      type="button"
      class="flex items-center justify-between gap-4 border p-4 text-left transition"
      :class="selectedSurchargeIds.includes(surcharge.id) ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'"
      @click="emit('toggle-surcharge', surcharge.id)"
    >
      <span>
        <span class="block text-base text-white uppercase">{{ surcharge.name }}</span>
        <span class="mt-1 block text-[10px] uppercase tracking-[0.16em] text-white/50">{{ surcharge.code }}</span>
      </span>
      <span class="shrink-0 text-xs uppercase text-accent">+{{ formatPriceWithVat(surcharge.price_without_vat) }} vč. DPH</span>
    </button>
  </div>
</template>
