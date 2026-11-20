<script setup lang="ts">
import ConfiguratorDoorThumbnail from './ConfiguratorDoorThumbnail.vue'
import type { InquiryItemDraft } from './types'
import { formatPieceCount, formatPrice, itemLineTotal, priceWithVat as calculatePriceWithVat } from './utils'

const props = defineProps<{
  // Every item of the request, including the configuration that is being built right now.
  items: InquiryItemDraft[]
  // The uid of the configuration that is still open in the steps above; it can not be removed, only changed.
  currentUid: string
  // The item that was reopened through „Upravit“: the open configuration is its exact copy.
  editingUid: string | null
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

const isCurrent = (item: InquiryItemDraft): boolean => item.uid === props.currentUid
const isEditing = (item: InquiryItemDraft): boolean => item.uid === props.editingUid
const isSelected = (item: InquiryItemDraft): boolean => item.uid === props.selectedUid

const cardClass = (item: InquiryItemDraft): string => {
  if (isSelected(item)) {
    return 'border-accent bg-accent/10'
  }

  if (isCurrent(item)) {
    return 'border-accent/40 bg-accent/5'
  }

  return 'border-white/10 bg-white/5 hover:border-accent/50'
}

const currentLabel = (item: InquiryItemDraft): string => {
  return isEditing(item) ? 'Upravovaná položka' : 'Aktuální konfigurace'
}
</script>

<template>
  <div class="border border-white/10 bg-dark/40 p-6">
    <div class="flex flex-wrap items-baseline justify-between gap-3">
      <p class="text-white/50 uppercase text-xs tracking-[0.2em]">Položky poptávky</p>
      <p v-if="items.length" class="text-white/50 text-xs">{{ formatPieceCount(items.reduce((total, item) => total + item.quantity, 0)) }}</p>
    </div>

    <p v-if="!items.length" class="mt-4 text-white/55 text-sm leading-relaxed">
      Zatím není co poptat: nejdřív vyberte model a variantu. Aktuální konfigurace se do poptávky přidá sama,
      tlačítkem „Přidat další konfiguraci“ ji uzamknete a začnete novou.
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
        <!-- One card per item: the door stays on the left, the rest of the card is split in two rows. The upper row
             holds the identity in the upper left corner and the controls (or the status) in the upper right one, the
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
              </div>

              <!-- The upper right corner belongs to the controls of a saved item, or to the status of the open
                   configuration; the figures never share a line with them. -->
              <div class="flex flex-wrap items-center gap-4 sm:justify-end">
                <span v-if="isCurrent(item)" class="text-accent text-[10px] uppercase tracking-[0.18em]">
                  {{ currentLabel(item) }}
                </span>
                <template v-else>
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
                </template>
              </div>
            </div>

            <!-- The lower row: the only quantity of the item on the left, editable for every row (the open
                 configuration included) so the count of the item and every total above rise from the same number,
                 and the figures on the right. Both blocks are labelled the same way, so their captions and values
                 share the baseline; the figures without VAT stay directly underneath their own price. -->
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
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>
