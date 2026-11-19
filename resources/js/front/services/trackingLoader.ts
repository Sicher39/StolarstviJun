import {
  type CookieCategory,
  type CookiePreferences,
  type OptionalCookieCategory,
  type TrackingScriptPayload
} from '@/front/types/cookies'

/**
 * Inserts the snippets the visitor allowed and nothing else.
 *
 * Rules, in order:
 *
 * 1. a record of an optional category runs only when that category was granted; the `requiresConsent`
 *    flag of an optional record is normalised to `true` on the server, so the check here is a second
 *    line of defence rather than the only one;
 * 2. a record without code never runs - the necessary first-party cookies are set by the application
 *    itself and their records only document them;
 * 3. a record is inserted once per page load, even when the configuration is applied twice.
 */

const loadedScripts = new Set<string>()

const isOptionalCategory = (category: CookieCategory): category is OptionalCookieCategory =>
  category !== 'necessary'

const isConsentRequired = (script: TrackingScriptPayload): boolean =>
  script.requiresConsent || isOptionalCategory(script.category)

const hasConsentForScript = (script: TrackingScriptPayload, preferences: CookiePreferences): boolean => {
  if (!isOptionalCategory(script.category)) {
    return !isConsentRequired(script)
  }

  return preferences[script.category] === true
}

const insertScript = (script: TrackingScriptPayload): void => {
  if (!script.code || script.code.trim() === '') {
    return
  }

  const key = `tracking-${script.id}`

  if (loadedScripts.has(key)) {
    return
  }

  const element = document.createElement('script')
  element.type = 'text/javascript'
  element.text = script.code
  element.dataset.trackingScript = key

  if (script.position === 'head') {
    document.head.appendChild(element)
  } else {
    document.body.appendChild(element)
  }

  loadedScripts.add(key)
}

export const loadAllowedTrackingScripts = (
  scripts: TrackingScriptPayload[],
  preferences: CookiePreferences
): void => {
  scripts
    .filter((script) => hasConsentForScript(script, preferences))
    .sort((left, right) => left.priority - right.priority)
    .forEach(insertScript)
}
