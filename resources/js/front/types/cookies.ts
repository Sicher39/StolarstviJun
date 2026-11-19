/**
 * Types of the cookie dialog payload.
 *
 * The values mirror what App\Services\Compliance\CookieConfigPayload publishes, so the dialog, the
 * browser loader and the server describe the same categories with the same names.
 */

export type CookieCategory = 'necessary' | 'analytics' | 'marketing' | 'preferences'

export type OptionalCookieCategory = Exclude<CookieCategory, 'necessary'>

export type LegalLinkKey = 'privacy_policy' | 'cookie_policy'

export interface LegalLinkPayload {
  title: string
  version: string
  url: string
}

export interface CookiePreferences {
  necessary: true
  analytics: boolean
  marketing: boolean
  preferences: boolean
}

export interface CookieSettingsPayload {
  enabled: boolean
  version: string
  bannerTitle: string | null
  bannerDescription: string | null
  acceptAllLabel: string
  rejectAllLabel: string
  customizeLabel: string
  savePreferencesLabel: string
  necessaryTitle: string | null
  necessaryDescription: string | null
  analyticsTitle: string | null
  analyticsDescription: string | null
  marketingTitle: string | null
  marketingDescription: string | null
  preferencesTitle: string | null
  preferencesDescription: string | null
  footerLinkLabel: string
  privacyPolicyUrl: string
  cookiePolicyUrl: string
}

export interface TrackingScriptPayload {
  id: number
  name: string
  category: CookieCategory
  position: 'head' | 'body_start' | 'body_end'
  description: string | null
  providerName: string | null
  providerPrivacyUrl: string | null
  /** The snippet the operator pasted into the administration. Absent for an informational record. */
  code: string | null
  requiresConsent: boolean
  priority: number
}

export interface CookieConfigPayload {
  settings: CookieSettingsPayload
  trackingScripts: TrackingScriptPayload[]
  legalLinks: Partial<Record<LegalLinkKey, LegalLinkPayload>>
  /** Categories that really carry an enabled optional script; the dialog offers only these. */
  optionalCategories: OptionalCookieCategory[]
  requiresCookieConsent: boolean
}

/**
 * Nothing optional is granted until the visitor says so. The necessary category is always granted,
 * because it is the technical operation of the site.
 */
export const defaultCookiePreferences = (): CookiePreferences => ({
  necessary: true,
  analytics: false,
  marketing: false,
  preferences: false,
})

export const optionalCookieCategories: OptionalCookieCategory[] = ['analytics', 'marketing', 'preferences']

/**
 * Czech label of a category, used where the stored wording is missing.
 */
export const cookieCategoryFallbackLabel = (category: CookieCategory): string => {
  switch (category) {
    case 'necessary':
      return 'Nezbytné cookies'
    case 'analytics':
      return 'Analytické cookies'
    case 'marketing':
      return 'Marketingové cookies'
    case 'preferences':
      return 'Preferenční cookies'
  }
}
