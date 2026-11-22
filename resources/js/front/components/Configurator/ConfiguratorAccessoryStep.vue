<script setup lang="ts">
import { computed } from 'vue'

import type { DoorFrameOption, HardwareOption } from './types'
import { formatSurchargePriceWithVat } from './utils'

const props = defineProps<{
  // The accessories the chosen model publishes. The catalog decides both lists, so a model without any
  // accessory shows an explanation instead of an empty step.
  frames: DoorFrameOption[]
  hardware: HardwareOption[]
  selectedFrameId: number | null
  selectedHardwareId: number | null
  vatRate: number
  // The step is a permanent part of the menu, so it is opened even before a model is chosen: it explains
  // what is missing instead of disappearing, which keeps the numbering of the steps stable.
  hasSelectedModel: boolean
}>()

const emit = defineEmits<{
  'update:selectedFrameId': [value: number | null]
  'update:selectedHardwareId': [value: number | null]
}>()

const hasFrames = computed((): boolean => props.frames.length > 0)
const hasHardware = computed((): boolean => props.hardware.length > 0)
const isEmpty = computed((): boolean => !hasFrames.value && !hasHardware.value)

// The step always renders one of two things: the accessories of the chosen model, or a sentence explaining why
// there is nothing to choose. Every reason has its own sentence, so an empty list is the only signal the
// template needs. The texts live here rather than in the template, because both are full sentences.
const emptyTitle = computed((): string => {
  return props.hasSelectedModel ? 'K tomuto modelu nejsou žádné doplňky.' : 'Zatím není z čeho vybírat.'
})

const emptyHint = computed((): string => {
  if (!props.hasSelectedModel) {
    return 'Nejdřív zvolte model dveří. Zárubeň a kování se nabídnou podle toho, co katalog k modelu přiřadil.'
  }

  return 'Katalog k tomuto modelu neobsahuje zárubeň ani kování. Pokračujte tlačítkem „Pokračovat“ na poptávku, dveře se objednají bez těchto doplňků.'
})

// Both accessories are optional, so a selected card is cleared by clicking it again. Without this the visitor
// could only switch to another option but never back to "no frame" or "no hardware".
const toggleFrame = (frameId: number): void => {
  emit('update:selectedFrameId', props.selectedFrameId === frameId ? null : frameId)
}

const toggleHardware = (hardwareId: number): void => {
  emit('update:selectedHardwareId', props.selectedHardwareId === hardwareId ? null : hardwareId)
}

// The prices are informational: they mirror the documented server formula with the VAT rate of the page, while
// the inquiry itself is always priced from the database. An option that costs nothing is named instead of
// printed as a column of zeros, exactly like the price breakdown of a saved item does it.
const priceLabel = (priceWithoutVat: number): string =>
  formatSurchargePriceWithVat(priceWithoutVat, props.vatRate)

const cardClass = (isSelected: boolean): string => {
  return isSelected ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'
}
</script>

<template>
  <div class="grid gap-6">
    <div v-if="isEmpty" class="max-w-2xl border border-white/10 bg-dark/40 p-4">
      <p class="text-base text-white">{{ emptyTitle }}</p>
      <p class="mt-2 text-sm leading-6 text-white/60">{{ emptyHint }}</p>
    </div>

    <template v-else>
      <p class="max-w-2xl text-sm leading-6 text-white/65">
        Zárubeň i kování jsou nepovinné. Vybranou volbu zrušíte kliknutím na ni.
      </p>

      <section v-if="hasFrames">
        <p class="text-accent text-xs uppercase tracking-[0.25em]">Zárubeň</p>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-white/60">
          Způsob osazení dveří. Cena je za jedny dveře.
        </p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
          <button
            v-for="frame in frames"
            :key="frame.id"
            type="button"
            class="flex items-center justify-between gap-3 border p-4 text-left transition"
            :class="cardClass(selectedFrameId === frame.id)"
            :aria-pressed="selectedFrameId === frame.id"
            @click="toggleFrame(frame.id)"
          >
            <!-- The picture is optional: the catalog can hide it, so the card keeps the name and the price
                 alone and the switch of the frame only removes this illustration. -->
            <span
              v-if="frame.image_url"
              class="h-14 w-14 shrink-0 overflow-hidden border border-white/10 bg-white/5"
            >
              <img
                :src="frame.image_url"
                :alt="frame.name"
                class="h-full w-full object-cover"
                loading="lazy"
              >
            </span>
            <!-- The name of the option is what the visitor reads: the catalogue code of the frame is an
                 internal identifier of the administration, so it is never printed under the name. -->
            <span class="min-w-0 flex-1">
              <span class="block text-base text-white">{{ frame.name }}</span>
            </span>
            <span class="shrink-0 text-right text-xs text-accent">{{ priceLabel(frame.price_without_vat) }}</span>
          </button>
        </div>
      </section>

      <section v-if="hasHardware">
        <p class="text-accent text-xs uppercase tracking-[0.25em]">Kování</p>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-white/60">
          Kliky a doplňky, které se montují na hotové dveře. Cena je za jedny dveře.
        </p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
          <button
            v-for="option in hardware"
            :key="option.id"
            type="button"
            class="flex items-center gap-3 border p-3 text-left transition"
            :class="cardClass(selectedHardwareId === option.id)"
            :aria-pressed="selectedHardwareId === option.id"
            @click="toggleHardware(option.id)"
          >
            <!-- The picture is a product illustration of the accessory, not a layer of the door preview. -->
            <span class="h-14 w-14 shrink-0 overflow-hidden border border-white/10 bg-white/5">
              <img
                v-if="option.image_url"
                :src="option.image_url"
                :alt="option.name"
                class="h-full w-full object-cover"
                loading="lazy"
              >
            </span>
            <!-- The name and the price are what the visitor compares: the catalogue code of the hardware is
                 an internal identifier of the administration, so it is never printed under the name. -->
            <span class="min-w-0 flex-1">
              <span class="block text-base text-white">{{ option.name }}</span>
              <span class="mt-1 block text-xs text-accent">{{ priceLabel(option.price_without_vat) }}</span>
            </span>
          </button>
        </div>
      </section>
    </template>
  </div>
</template>
