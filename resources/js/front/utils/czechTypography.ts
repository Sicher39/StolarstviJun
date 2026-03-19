const NBSP = '\u00A0'
const HTML_NBSP = '&nbsp;'

type TypographyMode = 'text' | 'html'

const SINGLE_LETTER_WORDS = /(^|[\s([{"„])([AaIiKkOoSsUuVvZz])\s+(?=\S)/g
const ABBREVIATIONS = /(^|[\s([{"„])((?:čl|odst|písm|str|č|tel|mob)\.)\s+(?=\S)/gi
const NUMBER_WITH_FOLLOWING_TOKEN = /(\d+(?:[ .,]\d+)*)(?:\s+)(?=(?:%|°C|kW|kWh|MWh|MW|Wp|W|V|A|Ah|Kč|EUR|m|m2|m²|m3|m³|cm|mm|kg|t|ks|let|roku|roků|dní|hodin|hodiny|hod|Sb\.|CZK|\S))/g

function spacer(mode: TypographyMode): string {
  return mode === 'html' ? HTML_NBSP : NBSP
}

function applyTypography(value: string, mode: TypographyMode): string {
  if (!value) return ''

  const joiner = spacer(mode)

  return value
    .replace(SINGLE_LETTER_WORDS, `$1$2${joiner}`)
    .replace(ABBREVIATIONS, `$1$2${joiner}`)
    .replace(NUMBER_WITH_FOLLOWING_TOKEN, `$1${joiner}`)
}

export function nbspText(value: string): string {
  return applyTypography(value, 'text')
}

export function nbspHtml(value: string): string {
  return value
    .split(/(<[^>]+>)/g)
    .map((part) => (part.startsWith('<') ? part : applyTypography(part, 'html')))
    .join('')
}

export function mapTypography<T>(value: T, mode: TypographyMode = 'text'): T {
  if (typeof value === 'string') {
    return (mode === 'html' ? nbspHtml(value) : nbspText(value)) as T
  }

  if (Array.isArray(value)) {
    return value.map((item) => mapTypography(item, mode)) as T
  }

  if (value && typeof value === 'object') {
    return Object.fromEntries(
      Object.entries(value as Record<string, unknown>).map(([key, item]) => [
        key,
        mapTypography(item, mode)
      ])
    ) as T
  }

  return value
}
