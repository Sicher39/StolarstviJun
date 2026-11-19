import { computed, ref } from 'vue'
import {
  defaultCookiePreferences,
  type CookieConfigPayload,
  type CookiePreferences,
  type OptionalCookieCategory
} from '@/front/types/cookies'
import { loadAllowedTrackingScripts } from '@/front/services/trackingLoader'

/**
 * The state of the cookie dialog of one visitor.
 *
 * The chosen categories live in `localStorage` and are mirrored to the server as evidence
 * (POST /api/compliance/consent). A failed mirror never blocks the site: the local choice is kept, the
 * request is retried after the next configuration load, and nothing optional runs while the choice is
 * unknown - the loader is fail-closed.
 *
 * Nothing is loaded before the configuration arrives, and the dialog only exists when the configuration
 * reports at least one optional category: a site that uses only the technical cookies of the application
 * has nothing to ask for.
 */

const storageKeys = {
  uuid: 'cookie_consent_uuid',
  preferences: 'cookie_consent_preferences',
  version: 'cookie_consent_version',
  syncPending: 'cookie_consent_sync_pending'
}

const configEndpoint = '/api/compliance/cookie-config'
const consentEndpoint = '/api/compliance/consent'

const config = ref<CookieConfigPayload | null>(null)
const preferences = ref<CookiePreferences>(defaultCookiePreferences())
const isLoaded = ref(false)
const isBannerVisible = ref(false)
const isPreferencesOpen = ref(false)

let configRequest: Promise<void> | null = null

const createUuid = (): string => {
  if (window.crypto?.randomUUID) {
    return window.crypto.randomUUID()
  }

  // The endpoint validates the uuid, so the fallback must be a valid v4 value and not a timestamp.
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
    const random = Math.floor(Math.random() * 16)
    const value = character === 'x' ? random : (random & 0x3) | 0x8

    return value.toString(16)
  })
}

const getConsentUuid = (): string => {
  const existing = localStorage.getItem(storageKeys.uuid)

  if (existing) {
    return existing
  }

  const uuid = createUuid()
  localStorage.setItem(storageKeys.uuid, uuid)

  return uuid
}

const readStoredPreferences = (): CookiePreferences | null => {
  const raw = localStorage.getItem(storageKeys.preferences)

  if (!raw) {
    return null
  }

  try {
    return { ...defaultCookiePreferences(), ...(JSON.parse(raw) as Partial<CookiePreferences>), necessary: true }
  } catch {
    return null
  }
}

const hasStoredConsentVersion = (version: string): boolean =>
  localStorage.getItem(storageKeys.version) === version && readStoredPreferences() !== null

const readCookie = (name: string): string => {
  const cookie = document.cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith(`${name}=`))

  return cookie ? decodeURIComponent(cookie.slice(name.length + 1)) : ''
}

/**
 * The CSRF headers of a JSON request. Laravel exposes the token in a meta tag only when the root view
 * renders it, so the XSRF cookie is used as the alternative; at least one of them is always present on a
 * page that was served by this application.
 */
const csrfHeaders = (): Record<string, string> => {
  const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
  const cookieToken = readCookie('XSRF-TOKEN')
  const headers: Record<string, string> = {}

  if (metaToken !== '') {
    headers['X-CSRF-TOKEN'] = metaToken
  }

  if (cookieToken !== '') {
    headers['X-XSRF-TOKEN'] = cookieToken
  }

  return headers
}

const writeLocalConsent = (nextPreferences: CookiePreferences): void => {
  if (!config.value) {
    return
  }

  localStorage.setItem(storageKeys.version, config.value.settings.version)
  localStorage.setItem(storageKeys.preferences, JSON.stringify(nextPreferences))
  preferences.value = nextPreferences
}

const syncConsentToServer = async (nextPreferences: CookiePreferences, withdrawal = false): Promise<void> => {
  if (!config.value) {
    return
  }

  try {
    const response = await fetch(consentEndpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...csrfHeaders()
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        consent_uuid: getConsentUuid(),
        type: 'cookie',
        version: config.value.settings.version,
        preferences: nextPreferences,
        withdrawal
      })
    })

    if (!response.ok) {
      localStorage.setItem(storageKeys.syncPending, '1')

      return
    }

    localStorage.removeItem(storageKeys.syncPending)
  } catch {
    localStorage.setItem(storageKeys.syncPending, '1')
  }
}

const persistConsent = async (nextPreferences: CookiePreferences, withdrawal = false): Promise<void> => {
  writeLocalConsent(nextPreferences)
  await syncConsentToServer(nextPreferences, withdrawal)
}

const applyPreferences = (nextPreferences: CookiePreferences): void => {
  loadAllowedTrackingScripts(config.value?.trackingScripts ?? [], nextPreferences)
}

/**
 * A granted category that is revoked again has to stop a script that already runs, and only a reload can
 * do that reliably. A first-time refusal does not reload, because nothing was started yet.
 */
const reloadIfConsentRevoked = (previous: CookiePreferences, next: CookiePreferences): void => {
  const revoked = (['analytics', 'marketing', 'preferences'] as const).some(
    (category) => previous[category] === true && next[category] === false
  )

  if (revoked) {
    window.location.reload()
  }
}

const optionalCategories = computed<OptionalCookieCategory[]>(() => config.value?.optionalCategories ?? [])

const hasOptionalCategories = computed<boolean>(() => optionalCategories.value.length > 0)

const requiresCookieConsent = computed<boolean>(
  () => Boolean(config.value?.settings.enabled) && hasOptionalCategories.value
)

const fetchConfig = async (): Promise<void> => {
  try {
    const response = await fetch(configEndpoint, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    })

    if (!response.ok) {
      throw new Error(`Cookie configuration request failed with status ${response.status}`)
    }

    config.value = (await response.json()) as CookieConfigPayload

    const storedPreferences = readStoredPreferences()
    const hasValidConsent =
      storedPreferences !== null && hasStoredConsentVersion(config.value.settings.version)

    preferences.value = hasValidConsent ? storedPreferences : defaultCookiePreferences()

    applyPreferences(preferences.value)

    isBannerVisible.value = requiresCookieConsent.value && !hasValidConsent

    if (hasValidConsent && localStorage.getItem(storageKeys.syncPending) === '1') {
      await syncConsentToServer(preferences.value)
    }
  } catch {
    // An unknown configuration means an unknown set of cookies: nothing optional is loaded.
    preferences.value = defaultCookiePreferences()
    isBannerVisible.value = false
    applyPreferences(preferences.value)
  } finally {
    isLoaded.value = true
    configRequest = null
  }
}

const loadConfig = (): Promise<void> => {
  if (isLoaded.value) {
    return Promise.resolve()
  }

  // The dialog, the floating button and the cookie page all ask for the configuration on mount; they
  // have to share one request instead of racing each other.
  configRequest = configRequest ?? fetchConfig()

  return configRequest
}

const acceptAll = async (): Promise<void> => {
  const next: CookiePreferences = { necessary: true, analytics: true, marketing: true, preferences: true }

  await persistConsent(next)
  applyPreferences(next)
  isBannerVisible.value = false
  isPreferencesOpen.value = false
}

const rejectAll = async (): Promise<void> => {
  const next = defaultCookiePreferences()
  const previous = { ...preferences.value }

  await persistConsent(next)
  applyPreferences(next)
  preferences.value = next
  isBannerVisible.value = false
  isPreferencesOpen.value = false

  reloadIfConsentRevoked(previous, next)
}

const savePreferences = async (nextPreferences: CookiePreferences): Promise<void> => {
  const next: CookiePreferences = { ...nextPreferences, necessary: true }
  const previous = { ...preferences.value }

  await persistConsent(next)
  applyPreferences(next)
  preferences.value = next
  isBannerVisible.value = false
  isPreferencesOpen.value = false

  reloadIfConsentRevoked(previous, next)
}

const withdraw = async (): Promise<void> => {
  const next = defaultCookiePreferences()
  const previous = { ...preferences.value }

  await persistConsent(next, true)
  applyPreferences(next)
  preferences.value = next
  isPreferencesOpen.value = false

  reloadIfConsentRevoked(previous, next)
}

/**
 * Opens the preferences from anywhere (footer link, cookie page, floating button). The configuration is
 * loaded first, so an early click still opens a populated dialog.
 */
const openPreferences = async (): Promise<void> => {
  await loadConfig()

  if (!requiresCookieConsent.value) {
    return
  }

  isPreferencesOpen.value = true
}

export const useCookieConsent = () => ({
  config,
  preferences,
  isLoaded,
  isBannerVisible,
  isPreferencesOpen,
  optionalCategories,
  hasOptionalCategories,
  requiresCookieConsent,
  loadConfig,
  acceptAll,
  rejectAll,
  savePreferences,
  withdraw,
  openPreferences
})
