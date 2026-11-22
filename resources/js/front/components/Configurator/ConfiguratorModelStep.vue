<script setup lang="ts">
import { computed } from 'vue'

import type { DoorModel, DoorModelType } from './types'
import { formatPriceWithVat, formatVariantCount } from './utils'

const props = defineProps<{
  doorModels: DoorModel[]
  selectedModelId: number | null
  vatRate: number
}>()

const emit = defineEmits<{
  'update:selectedModelId': [value: number | null]
}>()

// One card of the step: the model itself plus the picture that illustrates it.
interface ModelCard {
  model: DoorModel
  previewUrl: string | null
}

// One heading of the step together with the models printed under it.
interface ModelGroup {
  key: string
  heading: string
  cards: ModelCard[]
}

// The step offers the foiled doors first and the veneered ones second. The order below is the rank of
// DoorModelType on the server, which already sorted the payload by type and then by name, so grouping
// only splits the received list and never reorders it.
const MODEL_TYPE_GROUPS: { type: DoorModelType; heading: string }[] = [
  { type: 'foiled', heading: 'Fóliové dveře' },
  { type: 'veneered', heading: 'Dýhované dveře' },
]

// A model whose type has not been set in the catalogue cannot be put into either group without guessing
// which one it belongs to, so it is kept aside, named neutrally and shown after the classified groups.
const UNCLASSIFIED_GROUP_HEADING = 'Ostatní modely'

// Model cards are illustrated by the reference image of their first variant.
const toCard = (model: DoorModel): ModelCard => ({
  model,
  previewUrl: model.variants[0]?.source_reference_url ?? null,
})

const modelGroups = computed((): ModelGroup[] => {
  const groups: ModelGroup[] = MODEL_TYPE_GROUPS.map((group) => ({
    key: group.type,
    heading: group.heading,
    cards: props.doorModels.filter((model) => model.type === group.type).map(toCard),
  }))

  // Anything that does not carry one of the known types is never merged into a group; it stays apart.
  const unclassified = props.doorModels
    .filter((model) => !MODEL_TYPE_GROUPS.some((group) => group.type === model.type))
    .map(toCard)

  if (unclassified.length > 0) {
    groups.push({ key: 'unclassified', heading: UNCLASSIFIED_GROUP_HEADING, cards: unclassified })
  }

  // A type the catalogue holds no model of is not printed at all, so the step never shows an empty heading.
  return groups.filter((group) => group.cards.length > 0)
})
</script>

<template>
  <div class="grid gap-6">
    <section v-for="group in modelGroups" :key="group.key">
      <p class="text-accent text-xs uppercase tracking-[0.25em]">{{ group.heading }}</p>

      <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <button
          v-for="card in group.cards"
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
              <span class="text-base leading-tight text-white lg:text-lg">{{ card.model.name }}</span>
              <span v-if="card.model.category" class="text-[10px] tracking-[0.16em] text-accent">{{ card.model.category }}</span>
            </span>
            <span class="mt-auto flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[10px] tracking-[0.16em] text-white/55">
              <span>{{ formatVariantCount(card.model.variants.length) }}</span>
              <span>{{ formatPriceWithVat(card.model.base_price_without_vat, vatRate) }} vč. DPH</span>
            </span>
          </span>
        </button>
      </div>
    </section>
  </div>
</template>
