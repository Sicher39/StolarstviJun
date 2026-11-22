import { emptyCatalogListing, type CatalogListing } from './types'

/**
 * The sections of the catalog page, in the order they are printed.
 *
 * Labels, headings and the anchor prefix are the only catalog structure kept in the frontend; the
 * items themselves always come from the server listing. The bar of the page and the sections are
 * built from this single list, so an item of the bar can never point at a section that does not
 * exist.
 */
export type CatalogCategoryKey = 'dvere' | 'dekory' | 'skla' | 'zarubne' | 'kovani'

/**
 * Prefix of every anchor of the page. The navigation also accepts `#katalog-dekory` in the URL, so a
 * section can be linked from another page.
 */
export const CATALOG_HASH_PREFIX = 'katalog-'

export type CatalogCategoryMeta = {
  key: CatalogCategoryKey
  /** Label of the sticky navigation item. */
  text: string
  /** Heading of the section. */
  title: string
  /** One sentence under the heading, explaining what the section lists. */
  note: string
}

export type CatalogCategory = CatalogCategoryMeta & {
  /** Anchor of the section: the navigation scrolls to it and the section is rendered with it. */
  anchor: string
  /** How many items the section lists; a section without items is not rendered at all. */
  count: number
}

export const catalogCategoryMeta: CatalogCategoryMeta[] = [
  {
    key: 'dvere',
    text: 'Dveře',
    title: 'Modely dveří',
    note: 'Každý model se dodává v několika variantách. Pod hlavičkou modelu jsou vypsané všechny varianty, ve kterých se vyrábí.'
  },
  {
    key: 'dekory',
    text: 'Dekory',
    title: 'Dekory',
    note: 'Povrchové úpravy, ze kterých lze dveře vyrobit. U každého dekoru je uvedeno, ke kterým modelům se nabízí.'
  },
  {
    key: 'skla',
    text: 'Skla',
    title: 'Skla',
    note: 'Druhy prosklení, které lze k jednotlivým modelům zvolit. U každého skla je uvedeno, ke kterým modelům patří.'
  },
  {
    key: 'zarubne',
    text: 'Zárubně',
    title: 'Zárubně',
    note: 'Způsoby osazení dveří. U každé zárubně je uvedeno, ke kterým modelům se nabízí.'
  },
  {
    key: 'kovani',
    text: 'Kování',
    title: 'Kování',
    note: 'Kliky a doplňky, které se montují na hotové dveře. U každého kování je uvedeno, ke kterým modelům patří.'
  }
]

/**
 * How many items a section has. An option is listed once for every model that offers it, so the
 * merged lists of the payload can be counted directly.
 */
const countItems = (listing: CatalogListing, key: CatalogCategoryKey): number => {
  switch (key) {
    case 'dvere':
      return listing.models.length
    case 'dekory':
      return listing.decors.length
    case 'skla':
      return listing.glasses.length
    case 'zarubne':
      return listing.frames.length
    case 'kovani':
      return listing.hardware.length
  }
}

/**
 * Merges the published items into the fixed section metadata, so every section keeps the same
 * position and anchor even when it currently has nothing to print.
 */
export const buildCatalogCategories = (
  listing: CatalogListing | null | undefined
): CatalogCategory[] => {
  const items = listing ?? emptyCatalogListing()

  return catalogCategoryMeta.map((meta) => ({
    ...meta,
    anchor: `${CATALOG_HASH_PREFIX}${meta.key}`,
    count: countItems(items, meta.key)
  }))
}
