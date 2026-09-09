import type { CSSProperties } from 'vue'

import type { DoorVariant } from './types'

export const formatPrice = (value: number): string => {
  return new Intl.NumberFormat('cs-CZ', {
    style: 'currency',
    currency: 'CZK',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

export const formatPriceWithVat = (priceWithoutVat: number): string => {
  return formatPrice(Math.round(priceWithoutVat * 1.21 * 100) / 100)
}

export const variantLabel = (variant: DoorVariant): string => {
  const size = variant.width && variant.height ? `${variant.width} × ${variant.height} mm` : 'Rozměr na dotaz'
  const details = [variant.opening_type, variant.opening_direction].filter(Boolean).join(' / ')

  return [variant.code, size, details].filter(Boolean).join(' • ')
}

export const swatchStyle = (url: string | null, type: 'decor' | 'glass'): CSSProperties => {
  if (!url) {
    return {}
  }

  if (type === 'glass') {
    return {
      backgroundImage: `url("${url}"), linear-gradient(45deg, rgba(255,255,255,.045) 25%, transparent 25%), linear-gradient(-45deg, rgba(255,255,255,.045) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, rgba(255,255,255,.045) 75%), linear-gradient(-45deg, transparent 75%, rgba(255,255,255,.045) 75%)`,
      backgroundPosition: 'center, 0 0, 0 9px, 9px -9px, -9px 0',
      backgroundRepeat: 'repeat',
      backgroundSize: '88px 88px, 18px 18px, 18px 18px, 18px 18px, 18px 18px',
    }
  }

  return {
    backgroundImage: `url("${url}")`,
    backgroundPosition: 'center',
    backgroundRepeat: 'repeat',
    backgroundSize: '96px 96px',
  }
}
