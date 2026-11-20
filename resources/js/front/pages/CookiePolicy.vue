<script setup lang="ts">
import { computed, onMounted } from 'vue'
import GdprDocument from '@/front/components/Gdpr/GdprDocument.vue'
import MainLayout from '@/front/layouts/MainLayout.vue'
import { useCookieConsent } from '@/front/composables/useCookieConsent'

defineOptions({
  layout: MainLayout
})

// The text is the cookie policy document edited in the administration. The overview of the cookies and
// services the site uses is already inserted into it on the server, so the page never repeats the list.
const props = defineProps<{
  document: {
    title: string
    perex: string | null
    content: string
    version: string
    effective_from: string | null
    updated_at: string | null
  }
  privacy_policy_url: string
}>()

const { config, requiresCookieConsent, loadConfig, openPreferences } = useCookieConsent()

const settingsLabel = computed<string>(() => config.value?.settings.footerLinkLabel ?? 'Nastavení cookies')

onMounted(() => {
  // The dialog is opened from this page too, so its configuration is loaded with the page.
  void loadConfig()
})
</script>

<template>
  <div class="w-full">
    <div class="flex w-full justify-center bg-dark px-5 smW:px-8">
      <h1 class="pt-32 pb-16 text-center text-fluid-3xl text-primary smW:pt-40 smW:pb-20 lg:pt-44 lg:pb-24">
        {{ props.document.title }}
      </h1>
    </div>

    <GdprDocument :document="props.document" />

    <div class="flex w-full justify-center px-5 pb-16 smW:px-8">
      <div class="mx-auto block w-full lg:w-10/12 xl:w-8/12">
        <div class="border-t border-accent/40 pt-8 text-primary">
          <p class="text-base">
            Své předvolby cookies můžete kdykoli zkontrolovat nebo odvolat.
            <button
              v-if="requiresCookieConsent"
              type="button"
              class="font-main text-accent underline underline-offset-4 transition hover:text-primary"
              @click="openPreferences"
            >
              {{ settingsLabel }}
            </button>
            <span v-else>Web momentálně nepoužívá žádné volitelné cookies, není proto co nastavovat.</span>
          </p>
          <p class="mt-4 text-base">
            Jak zpracováváme osobní údaje, popisují
            <a
              :href="props.privacy_policy_url"
              class="text-accent underline underline-offset-4 transition hover:text-primary"
            >zásady ochrany osobních údajů</a>.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
