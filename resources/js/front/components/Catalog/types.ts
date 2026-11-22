/**
 * The payload of the public catalog page.
 *
 * The page is a plain listing, so the shapes are narrower than the ones of the configurator: they
 * carry the names the visitor reads and the pictures the cards show, and nothing that only a
 * configured door needs (render masks, prices, presets).
 */

/**
 * The finish of a door model, published so the model heading can name it.
 */
export type CatalogDoorModelType = 'foiled' | 'veneered'

/**
 * One variant of a door model: the size and the opening the visitor compares, plus the reference
 * photo of the door. A variant whose photo has not been uploaded is published with a `null` URL and
 * its card falls back to a text placeholder.
 */
export interface CatalogVariant {
  id: number
  /** The code the catalogue gives the variant; it is the name of the variant card. */
  name: string
  width: number | null
  height: number | null
  opening_type: string | null
  opening_direction: string | null
  has_glass: boolean
  sliding_possible: boolean
  image_url: string | null
}

/**
 * One door model of the catalog. The model itself is only a heading of the listing; the cards under
 * it are its variants.
 */
export interface CatalogDoorModel {
  id: number
  name: string
  category: string | null
  /** The finish of the model. Optional, because a model whose type has not been set carries none. */
  type?: CatalogDoorModelType | null
  /** The Czech label of the finish, published by the server so the page never renames it itself. */
  type_label?: string | null
  description: string | null
  variants: CatalogVariant[]
}

/**
 * A decor, a glass, a frame or a piece of hardware of the catalog.
 *
 * The same option is usually offered for several models, so it is published once and carries the
 * models it belongs to. `image_url` is the picture the catalog shows for it and is `null` when the
 * option has none (or when its picture is hidden in the administration).
 */
export interface CatalogOption {
  id: number
  name: string
  code: string
  image_url: string | null
  model_ids: number[]
  model_names: string[]
}

export interface CatalogListing {
  models: CatalogDoorModel[]
  decors: CatalogOption[]
  glasses: CatalogOption[]
  frames: CatalogOption[]
  hardware: CatalogOption[]
}

/**
 * An empty listing, used as the default of the page props, so the component never reads `undefined`.
 */
export const emptyCatalogListing = (): CatalogListing => ({
  models: [],
  decors: [],
  glasses: [],
  frames: [],
  hardware: []
})
