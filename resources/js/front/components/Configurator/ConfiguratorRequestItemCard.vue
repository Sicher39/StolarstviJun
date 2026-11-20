<script setup lang="ts">
import { computed } from 'vue'

// The editor of the open configuration. It is deliberately not a row of „Položky poptávky“: the draft becomes
// an item of the request only when the visitor saves it explicitly, so the same door is never shown twice.
const props = defineProps<{
  // Whether a configuration draft is open in the steps above. When it is false the editor only offers to start
  // another configuration, which keeps an untouched request free of default doors. That offer is the only place
  // the next configuration can be started from, so it appears once the previous one was saved into the request.
  isDraftOpen: boolean
  name: string
  quantity: number
  // Mirrors of the limits of the inquiry endpoint, so the visitor can not build a request the server refuses.
  maxQuantity: number
  maxItems: number
  // The open configuration is complete enough to become an item.
  canAdd: boolean
  // The open configuration may be saved into the request: added as a new item, or written into the edited one.
  canCommit: boolean
  // The open configuration is the reopened copy of a saved item, so saving updates it in place.
  isEditing: boolean
  // A blank draft may be opened: the request still has room for another saved item and no draft is open above.
  canStartNew: boolean
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:quantity': [value: number]
  // „Uložit do poptávky“: the draft becomes a saved item and the editor closes, which is what brings the
  // „Přidat další konfiguraci“ offer of the closed state into reach.
  save: []
  'cancel-edit': []
  // Opens a blank draft from the empty state, where no configuration is being edited. It is the only way to
  // start another configuration, so it exists only while no draft is open, i.e. after a save into the request.
  'start-new': []
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
    <!-- No configuration is open: nothing is preselected and nothing can be mistaken for an item of the request. -->
    <template v-if="!isDraftOpen">
      <p class="text-white/50 uppercase text-xs tracking-[0.2em]">Nová konfigurace</p>
      <p class="mt-3 text-white/65 text-sm leading-relaxed">
        Právě nemáte otevřenou žádnou konfiguraci. Poptávka obsahuje jen položky, které jste do ní uložili, a
        odešle se přesně tak, jak je.
      </p>
      <p class="mt-2 text-white/45 text-xs leading-relaxed">
        Pokud chcete poptat další dveře, začněte novou konfiguraci. Nabízí se až poté, co je předchozí konfigurace
        uložená v poptávce, a sama se do poptávky dostane teprve po svém uložení.
      </p>

      <div class="mt-6 flex flex-wrap items-center justify-end gap-4 border-t border-white/10 pt-5">
        <button
          type="button"
          class="border border-accent px-5 py-3 text-xs uppercase tracking-[0.18em] text-accent transition hover:bg-accent hover:text-dark disabled:cursor-not-allowed disabled:opacity-30"
          :disabled="!canStartNew"
          @click="emit('start-new')"
        >
          Přidat další konfiguraci
        </button>
      </div>

      <p v-if="!canStartNew" class="mt-3 text-sm text-white/50">
        Poptávka už obsahuje nejvyšší povolený počet položek ({{ maxItems }}). Odešlete ji a případně začněte novou.
      </p>
    </template>

    <template v-else>
      <p class="text-white/50 uppercase text-xs tracking-[0.2em]">
        {{ isEditing ? 'Úprava uložené položky' : 'Rozpracovaná konfigurace' }}
      </p>
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
        Tato konfigurace zatím v poptávce není. Tlačítkem „Uložit do poptávky“ z ní uděláte uloženou položku.
        Další konfiguraci pak začnete tlačítkem „Přidat další konfiguraci“ u uložených položek poptávky.
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

      <div class="mt-6 flex flex-wrap items-center justify-start gap-4 border-t border-white/10 pt-5">
        <button
          v-if="isEditing"
          type="button"
          class="text-white/55 text-xs uppercase tracking-[0.18em] transition hover:text-white"
          @click="emit('cancel-edit')"
        >
          Zrušit úpravu
        </button>
        <!-- Only saving is offered while a configuration is open: the next configuration is started from the
             saved items below, so an unfinished draft can never be turned into a second door by accident. -->
        <button
          type="button"
          class="border border-accent px-5 py-3 text-xs uppercase tracking-[0.18em] text-accent transition hover:bg-accent hover:text-dark disabled:cursor-not-allowed disabled:opacity-30"
          :disabled="!canCommit"
          @click="emit('save')"
        >
          {{ isEditing ? 'Uložit změny' : 'Uložit do poptávky' }}
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
    </template>
  </div>
</template>
