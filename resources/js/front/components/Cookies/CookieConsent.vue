<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { cookieCategoryFallbackLabel, defaultCookiePreferences, type CookiePreferences } from '@/front/types/cookies'
import { useCookieConsent } from '@/front/composables/useCookieConsent'

/**
 * The cookie dialog of the public site.
 *
 * It renders nothing at all unless the server reports at least one optional category, because a site
 * that only uses the technical cookies of the application has nothing to ask for. When it is rendered,
 * three controls are always available: accept everything, refuse the optional categories, and choose
 * them one by one. The necessary category can not be switched off, which is why it is rendered disabled.
 *
 * The footer and the cookie policy page open the same dialog by dispatching `open-cookie-settings`, so
 * the state stays in one place.
 */

const {
  config,
  preferences,
  isBannerVisible,
  isPreferencesOpen,
  optionalCategories,
  requiresCookieConsent,
  loadConfig,
  acceptAll,
  rejectAll,
  savePreferences,
  withdraw,
  openPreferences
} = useCookieConsent()

const localPreferences = ref<Record<keyof CookiePreferences, boolean>>({ ...defaultCookiePreferences() })
const panelRef = ref<HTMLDivElement | null>(null)

const settings = computed(() => config.value?.settings)
const legalLinks = computed(() => config.value?.legalLinks ?? {})

interface DialogCategory {
  key: keyof CookiePreferences
  title: string
  description: string
  disabled: boolean
}

/**
 * The categories the dialog offers: the necessary one is always listed (it is the technical operation of
 * the site) and only the optional categories that really carry a configured script are added.
 */
const categories = computed<DialogCategory[]>(() => {
  const current = settings.value

  if (!current) {
    return []
  }

  const listed: DialogCategory[] = [
    {
      key: 'necessary',
      title: current.necessaryTitle ?? cookieCategoryFallbackLabel('necessary'),
      description: current.necessaryDescription ?? '',
      disabled: true
    }
  ]

  const optionalDescriptions = {
    analytics: { title: current.analyticsTitle, description: current.analyticsDescription },
    marketing: { title: current.marketingTitle, description: current.marketingDescription },
    preferences: { title: current.preferencesTitle, description: current.preferencesDescription }
  }

  for (const category of optionalCategories.value) {
    const copy = optionalDescriptions[category]

    listed.push({
      key: category,
      title: copy.title ?? cookieCategoryFallbackLabel(category),
      description: copy.description ?? '',
      disabled: false
    })
  }

  return listed
})

const syncLocalPreferences = (): void => {
  localPreferences.value = { ...preferences.value, necessary: true }
}

const handleSave = async (): Promise<void> => {
  await savePreferences({ ...localPreferences.value, necessary: true })
}

const handleReopen = (): void => {
  void openPreferences()
}

const handleEscape = (event: KeyboardEvent): void => {
  if (event.key === 'Escape' && isPreferencesOpen.value) {
    isPreferencesOpen.value = false
  }
}

watch(isPreferencesOpen, async (isOpen) => {
  if (!isOpen) {
    return
  }

  syncLocalPreferences()
  await nextTick()
  panelRef.value?.focus()
})

onMounted(() => {
  // The listeners are registered before the configuration resolves, so an early click is not lost.
  window.addEventListener('open-cookie-settings', handleReopen)
  window.addEventListener('keydown', handleEscape)

  void loadConfig()
})

onBeforeUnmount(() => {
  window.removeEventListener('open-cookie-settings', handleReopen)
  window.removeEventListener('keydown', handleEscape)
})
</script>

<template>
  <Teleport to="body">
    <div>
      <!-- The floating button keeps the settings reachable after the banner was answered. -->
      <button
        v-if="requiresCookieConsent && settings"
        type="button"
        class="fixed right-5 bottom-5 z-40 border border-accent/60 bg-dark/95 p-3 text-accent shadow-xl transition hover:bg-accent hover:text-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
        aria-label="Otevřít nastavení cookies"
        title="Nastavení cookies"
        @click="openPreferences"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.6"
          class="h-6 w-6"
          aria-hidden="true"
        >
          <circle cx="12" cy="12" r="9" />
          <circle cx="9" cy="9" r="1.3" fill="currentColor" stroke="none" />
          <circle cx="14.5" cy="8" r="1" fill="currentColor" stroke="none" />
          <circle cx="15.5" cy="13.5" r="1.2" fill="currentColor" stroke="none" />
          <circle cx="8" cy="14.5" r="0.9" fill="currentColor" stroke="none" />
        </svg>
      </button>

      <div
        v-if="isBannerVisible && requiresCookieConsent && settings"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-accent/50 bg-dark/95 p-4 shadow-2xl md:p-6"
        role="dialog"
        aria-live="polite"
        aria-label="Nastavení cookies"
      >
        <div class="mx-auto flex max-w-6xl flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div class="max-w-3xl">
            <h2 class="text-xl text-accent">{{ settings.bannerTitle ?? 'Nastavení cookies' }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-primary/85">
              {{ settings.bannerDescription }}
            </p>
          </div>

          <div class="flex flex-col gap-2 smW:flex-row">
            <button
              type="button"
              class="border border-primary/40 px-5 py-3 text-xs uppercase tracking-[0.18em] text-primary transition hover:border-primary hover:bg-primary hover:text-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
              @click="rejectAll"
            >
              {{ settings.rejectAllLabel }}
            </button>

            <button
              type="button"
              class="border border-primary/40 px-5 py-3 text-xs uppercase tracking-[0.18em] text-primary transition hover:border-primary hover:bg-primary hover:text-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
              @click="openPreferences"
            >
              {{ settings.customizeLabel }}
            </button>

            <button
              type="button"
              class="border border-accent bg-accent px-5 py-3 text-xs uppercase tracking-[0.18em] text-dark transition hover:border-primary hover:bg-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
              @click="acceptAll"
            >
              {{ settings.acceptAllLabel }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="isPreferencesOpen && requiresCookieConsent && settings"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cookie-preferences-title"
      >
        <div
          ref="panelRef"
          tabindex="-1"
          class="max-h-[90vh] w-full max-w-2xl overflow-y-auto border border-accent/50 bg-dark p-6 text-primary shadow-2xl focus:outline-none"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 id="cookie-preferences-title" class="text-2xl text-accent">
                {{ settings.customizeLabel }}
              </h2>
              <p class="mt-2 text-sm text-primary/75">
                Nezbytné cookies jsou vždy aktivní. Ostatní kategorie jsou dobrovolné a můžete je kdykoli změnit.
              </p>
            </div>
            <button
              type="button"
              class="border border-primary/40 px-3 py-1 text-sm transition hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
              aria-label="Zavřít nastavení cookies"
              @click="isPreferencesOpen = false"
            >
              ×
            </button>
          </div>

          <div class="mt-6 space-y-4">
            <label
              v-for="category in categories"
              :key="category.key"
              class="flex items-start justify-between gap-4 border border-primary/15 p-4"
            >
              <span>
                <span class="block text-lg text-primary">{{ category.title }}</span>
                <span class="mt-1 block text-sm text-primary/70">{{ category.description }}</span>
              </span>
              <input
                v-model="localPreferences[category.key]"
                type="checkbox"
                class="mt-1 h-5 w-5 accent-accent"
                :disabled="category.disabled"
                :aria-label="category.title"
              >
            </label>
          </div>

          <p class="mt-5 text-sm text-primary/75">
            <a
              v-if="legalLinks.privacy_policy"
              :href="legalLinks.privacy_policy.url"
              class="underline underline-offset-4 hover:text-accent"
            >{{ legalLinks.privacy_policy.title }}</a>
            <template v-if="legalLinks.privacy_policy && legalLinks.cookie_policy"> · </template>
            <a
              v-if="legalLinks.cookie_policy"
              :href="legalLinks.cookie_policy.url"
              class="underline underline-offset-4 hover:text-accent"
            >{{ legalLinks.cookie_policy.title }}</a>
          </p>

          <div class="mt-6 flex flex-col gap-2 smW:flex-row smW:justify-between">
            <button
              type="button"
              class="border border-primary/40 px-5 py-3 text-xs uppercase tracking-[0.18em] text-primary transition hover:border-primary hover:bg-primary hover:text-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
              @click="rejectAll"
            >
              {{ settings.rejectAllLabel }}
            </button>

            <button
              type="button"
              class="border border-primary/40 px-5 py-3 text-xs uppercase tracking-[0.18em] text-primary transition hover:border-primary hover:bg-primary hover:text-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
              @click="withdraw"
            >
              Odvolat souhlas
            </button>

            <button
              type="button"
              class="border border-accent bg-accent px-5 py-3 text-xs uppercase tracking-[0.18em] text-dark transition hover:border-primary hover:bg-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
              @click="handleSave"
            >
              {{ settings.savePreferencesLabel }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
