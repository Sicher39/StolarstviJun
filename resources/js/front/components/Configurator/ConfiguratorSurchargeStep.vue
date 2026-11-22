<script setup lang="ts">
import type { SurchargeOption } from './types'
import { formatSurchargePriceWithVat } from './utils'

defineProps<{
  surcharges: SurchargeOption[]
  selectedSurchargeIds: number[]
  vatRate: number
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
      <!-- The card is named by the option alone: the catalogue code of the surcharge is an internal
           identifier of the administration and is never printed to the visitor. -->
      <span class="text-base text-white">{{ surcharge.name }}</span>
      <span class="shrink-0 text-xs text-accent">{{ formatSurchargePriceWithVat(surcharge.price_without_vat, vatRate) }}</span>
    </button>
  </div>
</template>
