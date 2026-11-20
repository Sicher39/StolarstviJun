<script setup lang="ts">
import { computed } from 'vue'

import type { InquiryItemDraft } from './types'
import { formatPieceCount, formatPrice, formatVatRate, priceWithVat as calculatePriceWithVat, sumPriceWithoutVat, sumQuantities } from './utils'

const props = defineProps<{
  // Only the items the visitor saved into the request. The configuration that is being built right now is
  // deliberately left out, so the recap always equals what will be sent.
  items: InquiryItemDraft[]
  vatRate: number
}>()

const totalDoors = computed((): number => sumQuantities(props.items))
const priceWithoutVat = computed((): number => sumPriceWithoutVat(props.items))
const priceWithVat = computed((): number => calculatePriceWithVat(priceWithoutVat.value, props.vatRate))
// The VAT amount is the difference of the two stored levels, so the recap always adds up on the page.
const vatAmount = computed((): number => Math.round((priceWithVat.value - priceWithoutVat.value) * 100) / 100)
</script>

<template>
  <div class="border border-white/10 bg-dark/40 p-6">
    <p class="text-white/50 uppercase text-xs tracking-[0.2em]">Rekapitulace poptávky</p>

    <p v-if="!items.length" class="mt-3 text-white/45 text-xs leading-relaxed">
      Rekapitulace obsahuje jen položky uložené do poptávky. Rozpracovaná konfigurace se do ní promítne až po
      uložení.
    </p>

    <div class="mt-4 space-y-3 text-sm text-white/70">
      <p class="flex items-center justify-between gap-4 border-b border-white/10 pb-3">
        <span>Celkem dveří</span>
        <span class="text-right text-white">{{ formatPieceCount(totalDoors) }}</span>
      </p>
      <p class="flex items-center justify-between gap-4 border-b border-white/10 pb-3">
        <span>Počet položek</span>
        <span class="text-right text-white">{{ items.length }}</span>
      </p>
      <p class="flex items-center justify-between gap-4">
        <span>Cena bez DPH</span>
        <span class="text-right text-white">{{ formatPrice(priceWithoutVat) }}</span>
      </p>
      <p class="flex items-center justify-between gap-4">
        <span>DPH {{ formatVatRate(vatRate) }} %</span>
        <span class="text-right text-white">{{ formatPrice(vatAmount) }}</span>
      </p>
    </div>

    <div class="mt-6 border border-accent/30 bg-accent/10 p-5">
      <!-- The orientation price and its amount on one line; the breakdown above already carries the
           price without VAT and the VAT amount, so nothing is repeated here. -->
      <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <p class="text-accent uppercase tracking-[0.2em] text-xs">Orientační cena s DPH</p>
        <p class="text-white text-3xl">{{ formatPrice(priceWithVat) }}</p>
      </div>
    </div>
  </div>
</template>
