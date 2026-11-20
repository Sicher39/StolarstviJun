<script setup lang="ts">
import { computed } from 'vue'

interface InquiryFormShape {
  customer_name: string
  customer_email: string
  customer_phone: string
  customer_message: string
  gdpr_consent: boolean
  processing: boolean
  errors: Partial<Record<'customer_name' | 'customer_email' | 'customer_phone' | 'customer_message' | 'gdpr_consent' | 'door_preview' | 'items', string>>
}

const props = defineProps<{
  form: InquiryFormShape
  flashSuccess: string
  canSubmitInquiry: boolean
  // Why the request can not be sent yet (no saved item, a pending edit). Empty while sending is possible.
  blockedReason: string
  // True while the browser composes the PNG of the chosen door, which happens right before the request.
  isPreparingPreview: boolean
  // Set when that composition failed: the message explains why nothing was sent.
  previewError: string
}>()

const emit = defineEmits<{
  submit: []
}>()

// Submitting is blocked while a request is in flight, while the illustration is being composed and while
// the configuration is still incomplete.
const isSubmitDisabled = computed((): boolean => props.form.processing || props.isPreparingPreview || !props.canSubmitInquiry)

const submitLabel = computed((): string => {
  if (props.isPreparingPreview) {
    return 'Připravuji náhled…'
  }

  return props.form.processing ? 'Odesílání…' : 'Odeslat poptávku'
})

// The acknowledgement is a real GDPR consent, so the form links the document the server will store with
// the record instead of keeping a second copy of the wording here.
const privacyPolicyUrl = computed((): string => route('front.gdpr'))
</script>

<template>
  <div class="border border-white/10 bg-dark/40 p-6">
    <p class="text-white text-2xl uppercase">Nezávazná poptávka</p>
    <p class="mt-4 text-white/65 uppercase text-sm leading-relaxed">
      Odešlete specifikaci a my připravíme individuální nabídku podle vybrané konfigurace.
    </p>

    <div v-if="flashSuccess" class="mt-6 border border-accent/40 bg-accent/10 p-4 text-accent text-sm">
      {{ flashSuccess }}
    </div>

    <form class="mt-6 space-y-4" @submit.prevent="emit('submit')">
      <div>
        <label class="block text-white/65 uppercase text-xs tracking-[0.2em]">Jméno a příjmení</label>
        <input
          v-model="form.customer_name"
          type="text"
          class="mt-2 w-full border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-accent"
          placeholder="Jan Novák"
        >
        <p v-if="form.errors.customer_name" class="mt-2 text-sm text-red-300">
          {{ form.errors.customer_name }}
        </p>
      </div>

      <div>
        <label class="block text-white/65 uppercase text-xs tracking-[0.2em]">E-mail</label>
        <input
          v-model="form.customer_email"
          type="email"
          class="mt-2 w-full border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-accent"
          placeholder="jan@example.cz"
        >
        <p v-if="form.errors.customer_email" class="mt-2 text-sm text-red-300">
          {{ form.errors.customer_email }}
        </p>
      </div>

      <div>
        <label class="block text-white/65 uppercase text-xs tracking-[0.2em]">Telefon</label>
        <input
          v-model="form.customer_phone"
          type="text"
          class="mt-2 w-full border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-accent"
          placeholder="+420 777 000 000"
        >
        <p v-if="form.errors.customer_phone" class="mt-2 text-sm text-red-300">
          {{ form.errors.customer_phone }}
        </p>
      </div>

      <div>
        <label class="block text-white/65 uppercase text-xs tracking-[0.2em]">Zpráva</label>
        <textarea
          v-model="form.customer_message"
          class="mt-2 min-h-[140px] w-full border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-accent"
          placeholder="Upřesněte požadavek, termín nebo montáž."
        />
        <p v-if="form.errors.customer_message" class="mt-2 text-sm text-red-300">
          {{ form.errors.customer_message }}
        </p>
      </div>

      <div class="pt-2">
        <label class="flex items-start gap-3">
          <input
            v-model="form.gdpr_consent"
            type="checkbox"
            name="gdpr_consent"
            class="mt-1 h-5 w-5 shrink-0 accent-accent"
          >
          <span class="text-sm leading-relaxed text-white/70">
            Odesláním poptávky potvrzuji, že jsem se seznámil/a se
            <a
              :href="privacyPolicyUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="text-accent underline underline-offset-4"
            >zásadami ochrany osobních údajů</a>
            a rozumím tomu, jak budou mé údaje zpracovány. Údaje použijeme k vyřízení této poptávky.
          </span>
        </label>
        <p v-if="form.errors.gdpr_consent" class="mt-2 text-sm text-red-300">
          {{ form.errors.gdpr_consent }}
        </p>
      </div>

      <div class="pt-2">
        <p v-if="previewError" class="mt-3 border border-red-400/40 bg-red-400/10 p-3 text-sm text-red-200" role="alert">
          {{ previewError }}
        </p>

        <p v-if="form.errors.items" class="mt-3 border border-red-400/40 bg-red-400/10 p-3 text-sm text-red-200" role="alert">
          {{ form.errors.items }}
        </p>

        <p v-if="form.errors.door_preview" class="mt-3 text-sm text-red-300">
          {{ form.errors.door_preview }}
        </p>

        <!-- An empty request would be refused by the endpoint, so the reason is stated next to the button
             instead of leaving a disabled control without an explanation. The sentence names the control that
             saves the configuration, so no second copy of that button is needed here. -->
        <p
          v-if="!canSubmitInquiry && blockedReason"
          class="mt-3 border border-white/15 bg-white/5 p-3 text-sm leading-relaxed text-white/70"
          role="status"
        >
          {{ blockedReason }}
        </p>
      </div>

      <div class="pt-2">
        <button
          type="submit"
          :disabled="isSubmitDisabled"
          :aria-busy="form.processing || isPreparingPreview"
          class="group relative flex h-[30px] w-full items-center justify-center overflow-hidden bg-primary/20 transition-all duration-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-40"
          :class="isSubmitDisabled ? 'cursor-not-allowed' : 'cursor-pointer'"
        >
          <!-- Decorative corner frame carried over from ButtonMain so the control keeps the site's button look. -->
          <span class="pointer-events-none absolute inset-0">
            <span class="absolute top-0 left-0 h-2.5 w-2.5 border-t border-l border-primary" />
            <span class="absolute top-0 right-0 h-2.5 w-2.5 border-t border-r border-primary" />
            <span class="absolute bottom-0 left-0 h-2.5 w-2.5 border-b border-l border-primary" />
            <span class="absolute right-0 bottom-0 h-2.5 w-2.5 border-b border-r border-primary" />
          </span>

          <span
            class="pointer-events-none absolute inset-y-0 left-0 h-full w-0 bg-primary transition-all duration-500"
            :class="isSubmitDisabled ? null : 'group-hover:w-full group-hover:bg-red'"
          />

          <span
            class="relative z-10 px-4 font-main text-sm font-normal uppercase text-primary transition-all duration-500 smW:text-xs lg:text-lg"
            :class="isSubmitDisabled ? null : 'group-hover:text-white'"
          >
            {{ submitLabel }}
          </span>
        </button>
      </div>
    </form>
  </div>
</template>
