<script setup lang="ts">
import { formatPrice, formatVatRate } from './utils'

defineProps<{
  selectedModelName: string | null
  selectedVariantLabel: string | null
  selectedDecorName: string | null
  selectedGlassName: string | null
  selectedSurchargeNames: string[]
  priceWithoutVat: number
  priceWithVat: number
  vatRate: number
}>()
</script>

<template>
  <div class="border border-white/10 bg-dark/40 p-6">
    <p class="text-white/50 uppercase text-xs tracking-[0.2em]">Vybraný model</p>
    <p class="mt-3 text-white text-2xl">{{ selectedModelName ?? '—' }}</p>

    <div class="mt-6 space-y-4 text-sm text-white/70">
      <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
        <span>Varianta</span>
        <span class="text-right text-white">{{ selectedVariantLabel ?? '—' }}</span>
      </div>
      <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
        <span>Dekor</span>
        <span class="text-right text-white">{{ selectedDecorName ?? '—' }}</span>
      </div>
      <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
        <span>Sklo</span>
        <span class="text-right text-white">{{ selectedGlassName ?? 'Bez skla' }}</span>
      </div>
      <div class="flex items-center justify-between gap-4">
        <span>Příplatky</span>
        <span class="text-right text-white">
          {{ selectedSurchargeNames.length ? selectedSurchargeNames.join(', ') : 'Bez příplatků' }}
        </span>
      </div>
    </div>

    <div class="mt-8 border border-accent/30 bg-accent/10 p-5">
      <p class="text-accent uppercase tracking-[0.2em] text-xs">Orientační cena s DPH</p>
      <p class="mt-3 text-white text-3xl">{{ formatPrice(priceWithVat) }}</p>
      <p class="mt-2 text-white/70 text-xs">{{ formatPrice(priceWithoutVat) }} bez DPH</p>
      <p class="mt-1 text-white/45 text-xs">Sazba DPH {{ formatVatRate(vatRate) }} %</p>
    </div>
  </div>
</template>
