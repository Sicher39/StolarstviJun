import type { CSSProperties } from 'vue'

import type { DoorVariant } from './types'

const CURRENCY_LABEL = 'Kč'

// The currency label is appended manually so the output always uses the Czech „Kč“ spelling and never
// depends on the ICU currency symbol of the runtime.
export const formatPrice = (value: number): string => {
  const formatted = new Intl.NumberFormat('cs-CZ', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)

  return `${formatted}\u00A0${CURRENCY_LABEL}`
}

export const formatVatRate = (vatRate: number): string => {
  return new Intl.NumberFormat('cs-CZ', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(vatRate)
}

export const priceWithVat = (priceWithoutVat: number, vatRate: number): number => {
  return Math.round(priceWithoutVat * (1 + vatRate / 100) * 100) / 100
}

export const formatPriceWithVat = (priceWithoutVat: number, vatRate: number): string => {
  return formatPrice(priceWithVat(priceWithoutVat, vatRate))
}

export const variantLabel = (variant: DoorVariant): string => {
  const size = variant.width && variant.height ? `${variant.width} × ${variant.height} mm` : 'Rozměr na dotaz'
  const details = [variant.opening_type, variant.opening_direction].filter(Boolean).join(' / ')

  return [variant.code, size, details].filter(Boolean).join(' • ')
}

// Czech counting uses the nominative plural only for 2–4. Every other value (0, 5, 11, 21, 25, …) takes the
// genitive plural, so the noun cannot be concatenated as a fixed string next to the number.
export const formatVariantCount = (count: number): string => {
  if (count === 1) {
    return `${count} varianta`
  }

  if (count >= 2 && count <= 4) {
    return `${count} varianty`
  }

  return `${count} variant`
}

export const swatchStyle = (url: string | null, type: 'decor' | 'glass'): CSSProperties => {
  if (!url) {
    return {}
  }

  if (type === 'glass') {
    // Glass previews are square compositions (pane plus planter). They are contained in a
    // square frame so nothing gets scaled beyond the frame or cropped at the edges.
    return {
      backgroundImage: `url("${url}"), linear-gradient(45deg, rgba(255,255,255,.045) 25%, transparent 25%), linear-gradient(-45deg, rgba(255,255,255,.045) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, rgba(255,255,255,.045) 75%), linear-gradient(-45deg, transparent 75%, rgba(255,255,255,.045) 75%)`,
      backgroundPosition: 'center, 0 0, 0 9px, 9px -9px, -9px 0',
      backgroundRepeat: 'no-repeat, repeat, repeat, repeat, repeat',
      backgroundSize: 'contain, 18px 18px, 18px 18px, 18px 18px, 18px 18px',
    }
  }

  return {
    backgroundImage: `url("${url}")`,
    backgroundPosition: 'center',
    backgroundRepeat: 'repeat',
    backgroundSize: '96px 96px',
  }
}
