import type { CSSProperties } from 'vue'

import type { DoorVariant, GlassOption, InquiryItemDraft } from './types'

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

// The same rule for the doors of a request: 1 kus, 2–4 kusy, otherwise kusů.
export const formatPieceCount = (count: number): string => {
  if (count === 1) {
    return `${count} kus`
  }

  if (count >= 2 && count <= 4) {
    return `${count} kusy`
  }

  return `${count} kusů`
}

// And for the items of the request: 1 položka, 2–4 položky, otherwise položek.
export const formatItemCount = (count: number): string => {
  if (count === 1) {
    return `${count} položka`
  }

  if (count >= 2 && count <= 4) {
    return `${count} položky`
  }

  return `${count} položek`
}

// The line price is what the customer pays for the item, the unit price is only one door of it. Both are
// rounded to the haléř on the client as well, so the cart of the browser never shows a 0.01 difference
// against the recap of the server.
export const itemLineTotal = (item: Pick<InquiryItemDraft, 'quantity' | 'unit_price_without_vat'>): number => {
  return Math.round(item.quantity * item.unit_price_without_vat * 100) / 100
}

export const sumQuantities = (items: Pick<InquiryItemDraft, 'quantity'>[]): number => {
  return items.reduce((total, item) => total + item.quantity, 0)
}

export const sumPriceWithoutVat = (items: InquiryItemDraft[]): number => {
  return Math.round(items.reduce((total, item) => total + itemLineTotal(item), 0) * 100) / 100
}

// The label of the item is derived from the selection until the visitor types their own name.
export const defaultItemName = (modelName: string | null, variantCode: string | null): string => {
  return [modelName, variantCode].filter((part): part is string => Boolean(part)).join(' · ')
}

// The door canvas used when a variant has not published its own size. The live preview and the composed
// PNG fall back to the same numbers, so every door on the page keeps the same proportions.
const DOOR_CANVAS_FALLBACK_WIDTH = 426
const DOOR_CANVAS_FALLBACK_HEIGHT = 900

export const doorCanvasSize = (variant: DoorVariant | null): { width: number; height: number } => {
  const width = variant?.canvas_width ?? 0
  const height = variant?.canvas_height ?? 0

  return {
    width: width > 0 ? width : DOOR_CANVAS_FALLBACK_WIDTH,
    height: height > 0 ? height : DOOR_CANVAS_FALLBACK_HEIGHT,
  }
}

// The glass texture is faded by the opacity of the glass type; the `DoorPreview` does the same.
export const glassOpacityFraction = (glass: GlassOption | null): number => {
  const opacity = glass?.opacity

  return typeof opacity === 'number' && !Number.isNaN(opacity) ? Math.min(Math.max(opacity / 100, 0), 1) : 1
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
