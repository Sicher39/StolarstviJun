import type { CSSProperties } from 'vue'

import type { DecorOption, DoorVariant, GlassOption, InquiryItemDraft } from './types'

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

// The exact text that names an option which costs nothing. A zero modifier is not a surcharge, so it is named
// instead of being printed as „0 Kč“, and every place of the configurator that lists a modifier reads it here.
export const NO_SURCHARGE_LABEL = 'Bez příplatku'

// Amounts are compared on the haléř, so a modifier rounded to zero by the price formula is not a surcharge either.
// The threshold matches the one that leaves a zero option out of the price breakdown of a saved item.
export const isSurcharge = (priceWithoutVat: number): boolean => Math.abs(priceWithoutVat) >= 0.005

// The gross amount of a modifier with its sign: „+1 230 Kč“, „-1 230 Kč“, or the shared text of a zero modifier.
// The sign is added here rather than left to the number formatter, so a discount never loses it and a zero option
// never turns into „+0 Kč“.
export const formatSurchargePrice = (priceWithoutVat: number, vatRate: number): string => {
  if (!isSurcharge(priceWithoutVat)) {
    return NO_SURCHARGE_LABEL
  }

  const gross = formatPriceWithVat(Math.abs(priceWithoutVat), vatRate)

  return priceWithoutVat < 0 ? `-${gross}` : `+${gross}`
}

// The label of an option card of the configurator states the price in gross terms, because the visitor compares
// it with the gross price of the door. A modifier that costs nothing is named and carries no VAT suffix at all.
export const formatSurchargePriceWithVat = (priceWithoutVat: number, vatRate: number): string => {
  if (!isSurcharge(priceWithoutVat)) {
    return NO_SURCHARGE_LABEL
  }

  return `${formatSurchargePrice(priceWithoutVat, vatRate)} vč. DPH`
}

// The size and the opening of a variant as one line, without the catalogue code. A variant whose record
// publishes no size is named instead of printed as a zero measurement, so the line is never empty. Only
// the four published fields are read, so the public catalog, which lists the same door without the render
// masks, captions its cards with the same helper.
export const variantSpecification = (
  variant: Pick<DoorVariant, 'width' | 'height' | 'opening_direction' | 'opening_type'>
): string => {
  const size = variant.width && variant.height ? `${variant.width} × ${variant.height} mm` : 'Rozměr na dotaz'
  const details = [variant.opening_type, variant.opening_direction].filter(Boolean).join(' / ')

  return [size, details].filter(Boolean).join(' • ')
}

// The full label of a variant: the code the catalogue calls the name of the variant, the size and the opening.
// It captions the preview and is stored with the item of the request, so it has to stay recognizable on its own.
export const variantLabel = (variant: DoorVariant): string => {
  return [variant.code, variantSpecification(variant)].filter(Boolean).join(' • ')
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

// Both price levels are rounded to the haléř on the client as well, so the cart of the browser never shows a
// 0.01 difference against the recap of the server.
const roundPrice = (value: number): number => Math.round(value * 100) / 100

// The line price is what the customer pays for the item, the unit price is only one door of it.
export const itemLineTotal = (item: Pick<InquiryItemDraft, 'quantity' | 'unit_price_without_vat'>): number => {
  return roundPrice(item.quantity * item.unit_price_without_vat)
}

// The exact text of the frame row of a configuration that contains the door only. It is not the price of an
// option but a statement about what the customer gets, so it is printed even though it adds nothing to the
// price: a missing row would read like a missing piece of the breakdown and leave „is a frame included?“
// unanswered. Neither the price of the item nor any total changes because of it.
export const NO_FRAME_LABEL = 'Bez zárubně (pouze dveře)'

/**
 * One part of the price of a single door, in the order the configurator adds it up.
 */
export interface UnitPriceBreakdownLine {
  key: 'base' | 'variant' | 'decor' | 'glass' | 'frame' | 'hardware' | 'surcharge'
  label: string
  amount: number
  /**
   * Whether the row describes the selection itself instead of a charged option, so it is printed even when it
   * adds nothing to the price. Only the frame uses it: „Bez zárubně (pouze dveře)“ has to stay readable in the
   * breakdown, while a zero-priced decor, glass, hardware or surcharge keeps being left out.
   */
  describesSelection?: boolean
}

export interface UnitPriceBreakdown {
  /**
   * The base price of the configured model. It is the first row of the collapsed breakdown of the item,
   * because it is the part of the price the visitor picked first.
   */
  base: UnitPriceBreakdownLine
  /**
   * The selected options in the order they are added. An option with a zero price modifier is not a surcharge,
   * so it is left out completely — with one exception: the frame row describes the selection, so a
   * configuration without a frame names it („Bez zárubně (pouze dveře)“) and a frame that costs nothing stays
   * visible with its own name. Its single amount is zero, so the sum of the lines is unaffected.
   */
  lines: UnitPriceBreakdownLine[]
}

// Mirrors the documented server formula: base price of the model + variant + decor + glass + frame + hardware
// + surcharges. The base price is returned on its own and the priced parts are listed one by one, so a
// customer who selects a glass, a frame or a piece of hardware sees which option moved the price per piece
// and by how much, instead of only the resulting total. A selected option whose modifier is zero adds nothing
// and is not named at all: the disclosure of surcharges may only contain amounts that were really charged. The
// frame row is the single exception, because it answers „does the door come with jambs?“ rather than „what does
// this option cost?“; the answer names the frame that was picked, or the door only when none was.
export const unitPriceBreakdown = (item: InquiryItemDraft): UnitPriceBreakdown => {
  const variantName = item.variant?.code ?? item.variant_label
  const modifiers: UnitPriceBreakdownLine[] = [
    {
      key: 'variant',
      label: ['Varianta', variantName].filter((part): part is string => Boolean(part)).join(' '),
      amount: item.variant?.price_modifier ?? 0,
    },
  ]

  if (item.decor) {
    modifiers.push({
      key: 'decor',
      label: `Dekor ${item.decor.name}`,
      amount: item.decor.price_modifier,
    })
  }

  if (item.glass) {
    modifiers.push({
      key: 'glass',
      label: `Sklo ${item.glass.name}`,
      amount: item.glass.price_modifier,
    })
  }

  // The accessories of the „Ostatní“ step are priced per door as a whole, so their amount is the net price of
  // the catalog row itself and not a modifier of anything. The frame is the one accessory that is always named:
  // the configuration either carries a frame, and then the customer reads which one, or it carries the door
  // only, which the breakdown says explicitly instead of staying silent about it.
  modifiers.push({
    key: 'frame',
    label: item.frame ? `Zárubeň ${item.frame.name}` : NO_FRAME_LABEL,
    amount: item.frame?.price_without_vat ?? 0,
    describesSelection: true,
  })

  if (item.hardware) {
    modifiers.push({
      key: 'hardware',
      label: `Kování ${item.hardware.name}`,
      amount: item.hardware.price_without_vat,
    })
  }

  for (const surcharge of item.surcharges) {
    modifiers.push({
      key: 'surcharge',
      label: `Příplatek ${surcharge.name}`,
      amount: surcharge.price_without_vat,
    })
  }

  // The base price is the anchor of the list. A draft of an older item without it keeps the documented sum by
  // taking the rest of the price per piece, so the base price and the listed surcharges always add up to the
  // amount the row shows.
  const additions = modifiers.reduce((sum, line) => sum + line.amount, 0)
  const basePrice = typeof item.base_price_without_vat === 'number'
    ? item.base_price_without_vat
    : item.unit_price_without_vat - additions

  const lines = modifiers
    .filter((modifier: UnitPriceBreakdownLine): boolean => {
      // Zero-amount rows are dropped, except the frame: it explains the selection even when it costs nothing.
      return isSurcharge(modifier.amount) || modifier.describesSelection === true
    })
    .map((modifier: UnitPriceBreakdownLine): UnitPriceBreakdownLine => ({
      ...modifier,
      amount: roundPrice(modifier.amount),
    }))

  return {
    base: { key: 'base', label: 'Základní cena dveří', amount: roundPrice(basePrice) },
    lines,
  }
}

export const sumQuantities = (items: Pick<InquiryItemDraft, 'quantity'>[]): number => {
  return items.reduce((total, item) => total + item.quantity, 0)
}

export const sumPriceWithoutVat = (items: InquiryItemDraft[]): number => {
  return roundPrice(items.reduce((total, item) => total + itemLineTotal(item), 0))
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

// Whether the room behind the selected glass is drawn blurred. The catalog decides it per glass, so the
// configurator and the item previews of the request read the same flag. A glass frozen into a saved item by
// an older payload carries no flag at all; it keeps the rule that was in force when it was stored, where
// every glass but the clear one was drawn with a blurred interior.
export const glassBlursInterior = (glass: GlassOption | null): boolean => {
  if (glass === null) {
    return false
  }

  return glass.blur_interior ?? (glass.code !== 'cire-sklo')
}

// Whether the grain of the uploaded texture runs across instead of downwards. The catalog decides it per decor,
// because every source is a square-ish close-up and the direction cannot be derived from the image; the renderer
// therefore only turns the run the source does not already have. A decor frozen into a saved item by a payload
// captured before the direction was published carries no value at all; it is read as the downwards run, which is
// the direction of almost every source and the rule the renderer follows without metadata.
export const decorTextureIsHorizontal = (decor: DecorOption | null): boolean => {
  return decor?.texture_orientation === 'horizontal'
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
