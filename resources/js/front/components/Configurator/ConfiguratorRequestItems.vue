<script setup lang="ts">
import { computed } from 'vue'

import ConfiguratorDoorThumbnail from './ConfiguratorDoorThumbnail.vue'
import type { InquiryItemDraft } from './types'
import type { UnitPriceBreakdown } from './utils'
import {
  formatPieceCount,
  formatPrice,
  itemLineTotal,
  priceWithVat as calculatePriceWithVat,
  unitPriceBreakdown
} from './utils'

const props = defineProps<{
  // Only the items the visitor explicitly saved with „Uložit do poptávky“. The configuration that is being
  // built right now is deliberately not part of this list: it lives in the editor above until it is saved.
  items: InquiryItemDraft[]
  // The item whose door is drawn in the large preview; `null` means the open configuration is drawn.
  selectedUid: string | null
  vatRate: number
  maxQuantity: number
}>()

const emit = defineEmits<{
  'update:quantity': [uid: string, quantity: number]
  remove: [uid: string]
  edit: [uid: string]
  // The card was clicked; the page decides whether that selects it or clears the selection.
  select: [uid: string]
}>()

const clampQuantity = (value: number): number => {
  if (!Number.isFinite(value)) {
    return 1
  }

  return Math.min(Math.max(Math.round(value), 1), props.maxQuantity)
}

const onQuantityInput = (uid: string, event: Event): void => {
  emit('update:quantity', uid, clampQuantity(Number((event.target as HTMLInputElement).value)))
}

const linePriceWithVat = (item: InquiryItemDraft): number => {
  return calculatePriceWithVat(itemLineTotal(item), props.vatRate)
}

const unitPriceWithVat = (item: InquiryItemDraft): number => {
  return calculatePriceWithVat(item.unit_price_without_vat, props.vatRate)
}

// The composition of the price of one piece, computed once per item and shared by the template.
const breakdowns = computed((): Record<string, UnitPriceBreakdown> => {
  const result: Record<string, UnitPriceBreakdown> = {}

  for (const item of props.items) {
    result[item.uid] = unitPriceBreakdown(item)
  }

  return result
})

// The base price and the options that really moved the price share one disclosure, so the card stays compact.
const breakdownFor = (item: InquiryItemDraft): UnitPriceBreakdown => breakdowns.value[item.uid]

// The base price of the door is printed with VAT only and without a sign, so the row of the base price and
// the rows of the priced options read the same way.
const basePriceWithVatLabel = (item: InquiryItemDraft): string => {
  return formatPrice(calculatePriceWithVat(breakdownFor(item).base.amount, props.vatRate))
}

// The surcharges are shown with VAT, matching the prominent price of the item, and an option that lowered the
// price keeps its sign instead of pretending to be an addition.
const surchargeAmountLabel = (amountWithoutVat: number): string => {
  const gross = formatPrice(calculatePriceWithVat(Math.abs(amountWithoutVat), props.vatRate))

  return amountWithoutVat < 0 ? `-${gross}` : `+${gross}`
}

const surchargesTotalLabel = (item: InquiryItemDraft): string => {
  const totalWithoutVat = breakdownFor(item).lines.reduce((total, line) => total + line.amount, 0)

  return surchargeAmountLabel(totalWithoutVat)
}

const isSelected = (item: InquiryItemDraft): boolean => item.uid === props.selectedUid

const cardClass = (item: InquiryItemDraft): string => {
  return isSelected(item) ? 'border-accent bg-accent/10' : 'border-white/10 bg-white/5 hover:border-accent/50'
}
</script>

<template>
  <div class="border border-white/10 bg-dark/40 p-6">
    <div class="flex flex-wrap items-baseline justify-between gap-3">
      <p class="text-white/50 uppercase text-xs tracking-[0.2em]">Uložené položky poptávky</p>
      <p v-if="items.length" class="text-white/50 text-xs">{{ formatPieceCount(items.reduce((total, item) => total + item.quantity, 0)) }}</p>
    </div>

    <p v-if="!items.length" class="mt-4 text-white/55 text-sm leading-relaxed">
      Zatím jste do poptávky neuložili žádnou konfiguraci. Rozpracovaná konfigurace se sem přesune teprve tehdy,
      když ji v editoru uložíte tlačítkem „Uložit do poptávky“.
    </p>

    <ul v-else class="mt-4 grid gap-3">
      <li
        v-for="(item, index) in items"
        :key="item.uid"
        class="cursor-pointer border p-4 transition"
        :class="cardClass(item)"
        role="button"
        tabindex="0"
        :aria-pressed="isSelected(item)"
        @click="emit('select', item.uid)"
        @keydown.enter="emit('select', item.uid)"
      >
        <!-- One card per saved item: the door stays on the left, the rest of the card is split in two rows. The
             upper row holds the identity in the upper left corner and the controls in the upper right one, the
             lower row holds the quantity control on the left and the prices on the right, so both sit on one
             horizontal baseline. Narrow widths wrap instead of scrolling. -->
        <div class="flex items-start gap-4 sm:gap-x-5">
          <!-- The door of this item only: the room of the selection is shown in the large preview. -->
          <ConfiguratorDoorThumbnail
            class="w-20 shrink-0 sm:w-24"
            :item="item"
            :id-base="`item-preview-${item.uid}`"
          />

          <div class="flex min-w-0 flex-1 flex-col gap-y-3">
            <div class="flex flex-wrap items-start justify-between gap-x-5 gap-y-2">
              <div class="min-w-0 flex-1 basis-48">
                <p class="text-white text-base leading-snug">{{ index + 1 }}. {{ item.name }}</p>
                <p class="mt-1 text-white/50 text-xs">{{ item.model_name }} · {{ item.variant_label }}</p>

                <p v-if="isSelected(item)" class="mt-2 text-accent text-[10px] uppercase tracking-[0.18em]">
                  Zobrazeno v náhledu
                </p>
                <p v-else class="mt-2 text-white/40 text-[10px] uppercase tracking-[0.18em]">Uloženo v poptávce</p>
              </div>

              <!-- The upper right corner belongs to the controls of the saved item; the figures never share a
                   line with them. -->
              <div class="flex flex-wrap items-center gap-4 sm:justify-end">
                <button
                  type="button"
                  class="text-white/55 text-xs uppercase tracking-[0.18em] transition hover:text-accent"
                  @click.stop="emit('edit', item.uid)"
                  @keydown.stop
                >
                  Upravit
                </button>
                <button
                  type="button"
                  class="text-white/55 text-xs uppercase tracking-[0.18em] transition hover:text-white"
                  @click.stop="emit('remove', item.uid)"
                  @keydown.stop
                >
                  Odebrat
                </button>
              </div>
            </div>

            <!-- The lower row: the only quantity of the item on the left, editable so the count of the item and
                 every total above rise from the same number, and the figures on the right. Both blocks are
                 labelled the same way, so their captions and values share the baseline; the figures without VAT
                 stay directly underneath their own price. -->
            <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
              <div class="min-w-0">
                <label
                  :for="`item-quantity-${item.uid}`"
                  class="block text-white/50 uppercase text-[10px] tracking-[0.14em]"
                >
                  Počet kusů
                </label>
                <input
                  :id="`item-quantity-${item.uid}`"
                  :value="item.quantity"
                  type="number"
                  min="1"
                  :max="maxQuantity"
                  step="1"
                  inputmode="numeric"
                  class="mt-1 block w-16 border border-white/10 bg-dark/40 px-2 py-1 text-white text-xs outline-none transition focus:border-accent"
                  @click.stop
                  @keydown.stop
                  @input="onQuantityInput(item.uid, $event)"
                >
              </div>

              <div class="flex flex-wrap items-start gap-x-6 gap-y-3 sm:justify-end">
                <div class="min-w-0">
                  <p class="text-white/50 uppercase text-[10px] tracking-[0.14em]">Cena za kus s DPH</p>
                  <p class="text-accent text-lg leading-tight sm:text-xl">{{ formatPrice(unitPriceWithVat(item)) }}</p>
                  <p class="text-white/45 text-[11px]">{{ formatPrice(item.unit_price_without_vat) }} bez DPH</p>
                </div>
                <div class="min-w-0">
                  <p class="text-white/50 uppercase text-[10px] tracking-[0.14em]">Cena celkem s DPH</p>
                  <p class="text-accent text-lg leading-tight sm:text-xl">{{ formatPrice(linePriceWithVat(item)) }}</p>
                  <p class="text-white/45 text-[11px]">{{ formatPrice(itemLineTotal(item)) }} bez DPH</p>
                </div>
              </div>
            </div>

            <!-- The whole price breakdown of one door sits in a single disclosure that is collapsed by
                 default: the base price is the first row inside it and the priced options follow. The summary
                 is always rendered, even when nothing was charged on top of the base price, because the base
                 price is part of the breakdown; only the extras are summed next to the heading, so the closed
                 card stays compact and still says what the door itself costs. -->
            <div class="border-t border-white/10 pt-3">
              <details class="group">
                <summary
                  class="flex cursor-pointer list-none flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-white/55 text-xs transition hover:text-accent [&::-webkit-details-marker]:hidden"
                  @click.stop
                  @keydown.stop
                >
                  <!-- The native marker is replaced by an arrow of our own, so the affordance looks the same in every
                       browser and can turn around when the list opens. The expanded state still comes from
                       `<details>`, so the summary stays readable and announced without any script. -->
                  <span class="inline-flex items-baseline gap-1.5">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 20 20"
                      stroke-width="2"
                      stroke="currentColor"
                      class="size-3 shrink-0 self-center transition-transform duration-200 group-open:rotate-180"
                      aria-hidden="true"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" d="m5 8 5 5 5-5" />
                    </svg>
                    <span>Cenový rozpis</span>
                  </span>
                  <!-- Beside the heading the visitor sees the sum of the extras only, explicitly labelled, so
                       the figure cannot be mistaken for the base price of the door. -->
                  <span
                    v-if="breakdownFor(item).lines.length"
                    class="flex flex-wrap items-baseline gap-x-1.5"
                  >
                    <span class="text-white/45">Příplatky</span>
                    <span class="text-white/85">{{ surchargesTotalLabel(item) }}</span>
                    <span class="text-white/45">s DPH</span>
                  </span>
                  <span v-else class="text-white/40">bez příplatků</span>
                </summary>

                <div class="mt-2 border-t border-white/10 pt-2">
                  <!-- The base price of the door is the first row of the breakdown and is typeset exactly like the
                       priced options below it: one label on the left, one amount with VAT on the right, no second
                       level underneath. The unit prices above the disclosure keep both levels. -->
                  <ul class="space-y-1 pl-4 text-white/65 text-xs">
                    <li class="flex items-baseline justify-between gap-4">
                      <span class="min-w-0">Základní cena dveří</span>
                      <span class="shrink-0 text-white/85">{{ basePriceWithVatLabel(item) }}</span>
                    </li>
                    <li
                      v-for="(line, lineIndex) in breakdownFor(item).lines"
                      :key="`${line.key}-${lineIndex}`"
                      class="flex items-baseline justify-between gap-4"
                    >
                      <span class="min-w-0">{{ line.label }}</span>
                      <span class="shrink-0 text-white/85">
                        {{ surchargeAmountLabel(line.amount) }}
                      </span>
                    </li>
                  </ul>
                </div>
              </details>
            </div>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>
