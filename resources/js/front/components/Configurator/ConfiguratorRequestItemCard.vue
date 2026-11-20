<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  name: string
  quantity: number
  // Mirrors of the limits of the inquiry endpoint, so the visitor can not build a request the server refuses.
  maxQuantity: number
  maxItems: number
  // The selection is complete enough to become an item.
  canAdd: boolean
  // The open configuration may be committed: added as a new item, or saved into the edited one.
  canCommit: boolean
  // The open configuration is the reopened copy of an existing item, so committing updates it in place.
  isEditing: boolean
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:quantity': [value: number]
  commit: []
  'cancel-edit': []
}>()

// An empty name means "use the label of the selection", which the page shows as the placeholder.
const resolvedName = computed((): string => props.name.trim())

const clampQuantity = (value: number): number => {
  if (!Number.isFinite(value)) {
    return 1
  }

  return Math.min(Math.max(Math.round(value), 1), props.maxQuantity)
}

const onQuantityInput = (event: Event): void => {
  const target = event.target as HTMLInputElement

  emit('update:quantity', clampQuantity(Number(target.value)))
}

const onNameInput = (event: Event): void => {
  emit('update:name', (event.target as HTMLInputElement).value)
}
</script>

<template>
  <div class="border border-white/10 bg-dark/40 p-6">
    <p class="text-white/50 uppercase text-xs tracking-[0.2em]">{{ isEditing ? 'Úprava položky' : 'Aktuální konfigurace' }}</p>
    <template v-if="isEditing">
      <p class="mt-3 text-white/65 text-sm leading-relaxed">
        Vyberte konfiguraci v krocích výše a uložte ji. Změny se zapíší do této položky, název ani počet kusů
        se přitom neztratí a v poptávce nevznikne druhá položka.
      </p>
      <p class="mt-2 text-white/45 text-xs leading-relaxed">
        Vaše rozpracovaná konfigurace se po uložení i po zrušení úpravy vrátí beze změny.
      </p>
    </template>
    <p v-else class="mt-3 text-white/65 text-sm leading-relaxed">
      Pojmenujte konfiguraci a určete počet kusů. Teprve pak ji přidejte do poptávky a můžete začít další,
      třeba jiný model nebo jinou variantu.
    </p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <label for="current-item-name" class="block text-white/65 uppercase text-xs tracking-[0.2em]">Název položky</label>
        <input
          id="current-item-name"
          :value="name"
          type="text"
          maxlength="160"
          class="mt-2 w-full border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-accent"
          placeholder="Například: Dveře do ložnice"
          @input="onNameInput"
        >
      </div>

      <div class="sm:col-span-1">
        <label for="current-item-quantity" class="block text-white/65 uppercase text-xs tracking-[0.2em]">Počet kusů</label>
        <input
          id="current-item-quantity"
          :value="quantity"
          type="number"
          min="1"
          :max="maxQuantity"
          step="1"
          inputmode="numeric"
          class="mt-2 w-full border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-accent"
          @input="onQuantityInput"
        >
        <p class="mt-1 text-white/45 text-xs">Nejvýše {{ maxQuantity }} kusů u jedné položky.</p>
      </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-end gap-4 border-t border-white/10 pt-5">
      <button
        v-if="isEditing"
        type="button"
        class="text-white/55 text-xs uppercase tracking-[0.18em] transition hover:text-white"
        @click="emit('cancel-edit')"
      >
        Zrušit úpravu
      </button>
      <button
        type="button"
        class="border border-accent px-5 py-3 text-xs uppercase tracking-[0.18em] text-accent transition hover:bg-accent hover:text-dark disabled:cursor-not-allowed disabled:opacity-30"
        :disabled="!canCommit"
        @click="emit('commit')"
      >
        {{ isEditing ? 'Uložit změny' : 'Přidat další konfiguraci' }}
      </button>
    </div>

    <p v-if="!canAdd" class="mt-3 text-sm text-red-300">
      Nejdřív dokončete výběr modelu a varianty.
    </p>
    <p v-else-if="!canCommit" class="mt-3 text-sm text-white/50">
      Poptávka už obsahuje nejvyšší povolený počet položek ({{ maxItems }}). Odešlete ji a případně začněte novou.
    </p>
    <p v-else-if="resolvedName === ''" class="mt-3 text-white/45 text-xs">
      Bez vyplněného názvu použijeme název modelu a varianty.
    </p>
  </div>
</template>
