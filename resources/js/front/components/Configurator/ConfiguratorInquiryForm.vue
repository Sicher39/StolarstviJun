<script setup lang="ts">
import ButtonMain from '@/front/components/Buttons/ButtonMain.vue'

interface InquiryFormShape {
  customer_name: string
  customer_email: string
  customer_phone: string
  customer_message: string
  processing: boolean
  errors: Partial<Record<'customer_name' | 'customer_email' | 'customer_phone' | 'customer_message', string>>
}

defineProps<{
  form: InquiryFormShape
  flashSuccess: string
  canSubmitInquiry: boolean
}>()

const emit = defineEmits<{
  submit: []
}>()
</script>

<template>
  <div class="border border-white/10 bg-dark/40 p-6">
    <p class="text-white text-2xl uppercase">Nezávazná poptávka</p>
    <p class="mt-4 text-white/65 uppercase text-sm leading-relaxed">
      Odešlete specifikaci a my připravíme individuální nabídku podle vybrané konfigurace.
    </p>

    <div v-if="flashSuccess" class="mt-6 border border-accent/40 bg-accent/10 p-4 text-accent uppercase text-sm">
      {{ flashSuccess }}
    </div>

    <form class="mt-6 space-y-4" @submit.prevent="emit('submit')">
      <div>
        <label class="block text-white/65 uppercase text-xs tracking-[0.2em]">Jméno a příjmení</label>
        <input
          v-model="form.customer_name"
          type="text"
          class="mt-2 w-full border border-white/10 bg-white/5 px-4 py-3 text-white uppercase outline-none transition focus:border-accent"
          placeholder="Jan Novák"
        >
        <p v-if="form.errors.customer_name" class="mt-2 text-sm text-red-300 uppercase">
          {{ form.errors.customer_name }}
        </p>
      </div>

      <div>
        <label class="block text-white/65 uppercase text-xs tracking-[0.2em]">E-mail</label>
        <input
          v-model="form.customer_email"
          type="email"
          class="mt-2 w-full border border-white/10 bg-white/5 px-4 py-3 text-white uppercase outline-none transition focus:border-accent"
          placeholder="jan@example.cz"
        >
        <p v-if="form.errors.customer_email" class="mt-2 text-sm text-red-300 uppercase">
          {{ form.errors.customer_email }}
        </p>
      </div>

      <div>
        <label class="block text-white/65 uppercase text-xs tracking-[0.2em]">Telefon</label>
        <input
          v-model="form.customer_phone"
          type="text"
          class="mt-2 w-full border border-white/10 bg-white/5 px-4 py-3 text-white uppercase outline-none transition focus:border-accent"
          placeholder="+420 777 000 000"
        >
        <p v-if="form.errors.customer_phone" class="mt-2 text-sm text-red-300 uppercase">
          {{ form.errors.customer_phone }}
        </p>
      </div>

      <div>
        <label class="block text-white/65 uppercase text-xs tracking-[0.2em]">Zpráva</label>
        <textarea
          v-model="form.customer_message"
          class="mt-2 min-h-[140px] w-full border border-white/10 bg-white/5 px-4 py-3 text-white uppercase outline-none transition focus:border-accent"
          placeholder="Upřesněte požadavek, termín nebo montáž."
        />
        <p v-if="form.errors.customer_message" class="mt-2 text-sm text-red-300 uppercase">
          {{ form.errors.customer_message }}
        </p>
      </div>

      <div class="pt-2">
        <ButtonMain :disabled="form.processing || !canSubmitInquiry" type="submit">
          {{ form.processing ? 'Odesílání…' : 'Odeslat poptávku' }}
        </ButtonMain>
      </div>
    </form>
  </div>
</template>
