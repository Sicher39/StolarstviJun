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

export interface DecorOption {
  id: number
  name: string
  code: string
  price_modifier: number
  texture_image_url: string | null
  preview_image_url: string | null
}

export interface GlassOption {
  id: number
  name: string
  code: string
  opacity: number
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

export interface DoorModel {
  id: number
  name: string
  slug: string
  category: string | null
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
 * The catalog lookups (`variant`, `decor`, `glass`, `surcharges`) are frozen copies of the option the
 * visitor picked: the PNG is composed from them right before submitting, so a draft must keep working
 * even after the visitor starts another configuration. The server never trusts any of this. It prices
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
  surcharge_ids: number[]
  model_name: string
  variant_label: string
  variant: DoorVariant | null
  decor: DecorOption | null
  glass: GlassOption | null
  surcharges: SurchargeOption[]
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
  surcharge_ids: number[]
  door_preview?: File
}
