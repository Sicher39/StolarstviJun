<script setup lang="ts">
import { formatPrice } from './utils'

defineProps<{
  selectedModelName: string | null
  selectedVariantLabel: string | null
  selectedDecorName: string | null
  selectedGlassName: string | null
  selectedSurchargeNames: string[]
  priceWithVat: number
}>()
</script>

<template>
  <div class="border border-white/10 bg-dark/40 p-6">
    <p class="text-white/50 uppercase text-xs tracking-[0.2em]">Vybraný model</p>
    <p class="mt-3 text-white text-2xl uppercase">{{ selectedModelName ?? '—' }}</p>

    <div class="mt-6 space-y-4 text-sm uppercase text-white/70">
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
      <p class="text-accent uppercase tracking-[0.2em] text-xs">Orientační cena</p>
      <p class="mt-3 text-white text-3xl uppercase">{{ formatPrice(priceWithVat) }}</p>
      <p class="mt-2 text-white/60 uppercase text-xs">včetně odhadované sazby DPH 21 %</p>
    </div>
  </div>
</template>
