export type PreviewSceneMaterialType = 'wall' | 'floor'

export interface PreviewSceneMaterial {
  id: number
  type: PreviewSceneMaterialType
  name: string
  code: string
  color: string | null
  texture_image_url: string | null
  preview_image_url: string | null
}

export interface PreviewScene {
  id: number
  name: string
  code: string
  canvas_width: number
  canvas_height: number
  door_x: number
  door_y: number
  door_width: number
  door_height: number
  scene_base_url: string | null
  scene_foreground_url: string | null
  doorway_depth_url: string | null
  wall_mask_url: string | null
  floor_mask_url: string | null
  doorway_mask_url: string | null
  interior_background_url: string | null
  interior_background_blurred_url: string | null
  materials: PreviewSceneMaterial[]
}

export interface DoorVariant {
  id: number
  code: string
  width: number | null
  height: number | null
  opening_direction: string | null
  opening_type: string | null
  has_glass: boolean
  sliding_possible: boolean
  price_modifier: number
  canvas_width: number
  canvas_height: number
  frame_base_url: string | null
  frame_mask_url: string | null
  door_mask_url: string | null
  door_vertical_decor_mask_url: string | null
  door_horizontal_decor_mask_url: string | null
  frame_vertical_decor_mask_url: string | null
  frame_horizontal_decor_mask_url: string | null
  depth_overlay_url: string | null
  construction_overlay_url: string | null
  handle_overlay_url: string | null
  source_reference_url: string | null
}

/**
 * The direction the grain runs in the uploaded texture of a decor: downwards in almost every source, across in
 * the few that are stored that way.
 */
export type DecorTextureOrientation = 'vertical' | 'horizontal'

export interface DecorOption {
  id: number
  name: string
  code: string
  price_modifier: number
  texture_image_url: string | null
  /**
   * The direction the grain runs in the uploaded texture. The renderer turns one of the two decor runs by a
   * quarter turn, so the source decides which run is drawn straight. A decor frozen into a saved item by a
   * payload captured before the direction was published does not carry it, so readers go through
   * `decorTextureIsHorizontal()`.
   */
  texture_orientation?: DecorTextureOrientation
  preview_image_url: string | null
}

export interface GlassOption {
  id: number
  name: string
  code: string
  opacity: number
  /**
   * Whether the room behind the glass is drawn blurred. The catalog decides it, so the flag wins over the
   * legacy rule that only knew the clear glass. A glass frozen into a saved item by a payload captured
   * before the flag existed does not carry it, so readers go through `glassBlursInterior()`.
   */
  blur_interior?: boolean
  price_modifier: number
  texture_image_url: string | null
  preview_image_url: string | null
}

export interface SurchargeOption {
  id: number
  name: string
  code: string
  price_without_vat: number
}

/**
 * A frame type of the „Ostatní“ step: bez zárubně, bezfalcová, obložková or obrácená.
 *
 * The frame is an installation type of the whole door, so the catalog publishes its name and its net price
 * only; the gross price is derived from the VAT rate of the page like every other price here. The picture is
 * an optional illustration of the selector card and the catalog can hide it, so the card must render without
 * it.
 */
export interface DoorFrameOption {
  id: number
  name: string
  code: string
  price_without_vat: number
  /**
   * The illustration of the selector card, already filtered by the "show in the configurator" switch of the
   * catalog. It is optional because a frame frozen into a draft by a payload captured before the picture was
   * published does not carry the key at all; both null and a missing key mean the same plain text card.
   */
  image_url?: string | null
}

/**
 * A piece of hardware of the „Ostatní“ step: a handle or another accessory of the finished door.
 *
 * The picture is a product illustration for the selector card, never a layer of the door preview.
 */
export interface HardwareOption {
  id: number
  name: string
  code: string
  price_without_vat: number
  image_url: string | null
}

/**
 * The surface finish of a door model. The catalog publishes it so the listing can present the foiled
 * models before the veneered ones; models saved before the type existed do not carry it.
 */
export type DoorModelType = 'foiled' | 'veneered'

export interface DoorModel {
  id: number
  name: string
  slug: string
  category: string | null
  /**
   * The finish of the model. Optional because a payload captured before the type was published does
   * not carry it, and because a model whose type has not been set yet keeps sorting after the typed ones.
   */
  type?: DoorModelType | null
  description: string | null
  base_price_without_vat: number
  base_price_with_vat: number
  preview_image_url: string | null
  initial_variant_id: number | null
  initial_decor_id: number | null
  variants: DoorVariant[]
  decors: DecorOption[]
  glasses: GlassOption[]
  surcharges: SurchargeOption[]
  /**
   * The accessories offered for this model in the „Ostatní“ step. Both lists are optional, because a payload
   * captured before the step existed does not carry them; readers fall back to an empty list, which is exactly
   * what a model without any accessory shows.
   */
  frames?: DoorFrameOption[]
  hardware?: HardwareOption[]
}

/**
 * The limits the inquiry endpoint enforces. The browser mirrors them, so the visitor is stopped before
 * a request is built that the server would refuse anyway.
 */
export interface ConfiguratorLimits {
  max_items: number
  max_quantity_per_item: number
}

/**
 * One configured door of the request, kept in the browser until the inquiry is sent.
 *
 * The catalog lookups (`variant`, `decor`, `glass`, `frame`, `hardware`, `surcharges`) are frozen copies of
 * the option the visitor picked: the PNG is composed from them right before submitting, so a draft must keep
 * working even after the visitor starts another configuration. The server never trusts any of this. It prices
 * the ids on its own and only stores the uploaded PNG.
 */
export interface InquiryItemDraft {
  /** Local identity of the row, so it can be edited and removed without losing the selection. */
  uid: string
  name: string
  quantity: number
  door_model_id: number
  door_variant_id: number
  decor_id: number | null
  glass_type_id: number | null
  door_frame_id: number | null
  hardware_id: number | null
  surcharge_ids: number[]
  model_name: string
  variant_label: string
  variant: DoorVariant | null
  decor: DecorOption | null
  glass: GlassOption | null
  /**
   * The accessories chosen in the „Ostatní“ step, frozen with the item like every other option. The server
   * prices the ids on its own, so these copies only explain the price of the item in the browser.
   */
  frame: DoorFrameOption | null
  hardware: HardwareOption | null
  surcharges: SurchargeOption[]
  /**
   * The base price of the configured model. It anchors the price breakdown of the item, because every other
   * part of the price (the variant, the decor, the glass, the frame, the hardware and the surcharges) is
   * stored on the frozen option the visitor picked.
   */
  base_price_without_vat: number
  unit_price_without_vat: number
}

/**
 * One item exactly as the inquiry endpoint expects it in the `items` multipart field.
 *
 * A field is omitted rather than sent as `null`: the multipart builder of Inertia would append the
 * string "null" for a null value, which the `exists` and `file` rules would then refuse.
 */
export interface InquiryItemPayload {
  name: string
  quantity: number
  door_model_id: number
  door_variant_id: number
  decor_id?: number
  glass_type_id?: number
  door_frame_id?: number
  hardware_id?: number
  surcharge_ids: number[]
  door_preview?: File
}
