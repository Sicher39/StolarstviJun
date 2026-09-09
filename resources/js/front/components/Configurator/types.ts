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
  variants: DoorVariant[]
  decors: DecorOption[]
  glasses: GlassOption[]
  surcharges: SurchargeOption[]
}
