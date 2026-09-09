<script setup lang="ts">
import { computed } from 'vue'

import type { DoorVariant, PreviewScene, PreviewSceneMaterial } from './types'

const props = defineProps<{
  modelName: string | null
  canRenderDecorLayer: boolean
  selectedPreviewImageUrl: string | null
  selectedDecorImageUrl: string | null
  selectedVariant: DoorVariant | null
  previewScene: PreviewScene | null
  selectedWall: PreviewSceneMaterial | null
  selectedFloor: PreviewSceneMaterial | null
  doorMaskId: string
  glassLayerImageUrl: string | null
  selectedGlassOpacity: number
  useBlurredInterior: boolean
}>()

const sceneWidth = computed((): number => props.previewScene?.canvas_width ?? 1400)
const sceneHeight = computed((): number => props.previewScene?.canvas_height ?? 1100)
const sceneViewBox = computed((): string => `0 0 ${sceneWidth.value} ${sceneHeight.value}`)
const sceneAspectRatio = computed((): string => `${sceneWidth.value} / ${sceneHeight.value}`)
const doorCanvasWidth = computed((): number => props.selectedVariant?.canvas_width ?? 426)
const doorCanvasHeight = computed((): number => props.selectedVariant?.canvas_height ?? 900)
const doorViewBox = computed((): string => `0 0 ${doorCanvasWidth.value} ${doorCanvasHeight.value}`)
const doorX = computed((): number => props.previewScene?.door_x ?? 487)
const doorY = computed((): number => props.previewScene?.door_y ?? 100)
const doorWidth = computed((): number => props.previewScene?.door_width ?? 426)
const doorHeight = computed((): number => props.previewScene?.door_height ?? 900)
const frameMaskId = computed((): string => `${props.doorMaskId}-frame-mask`)
const glassTexturePatternId = computed((): string => `${props.doorMaskId}-glass-texture-pattern`)
const wallMaskId = computed((): string => `${props.doorMaskId}-wall-mask`)
const floorMaskId = computed((): string => `${props.doorMaskId}-floor-mask`)
const doorwayMaskId = computed((): string => `${props.doorMaskId}-doorway-mask`)
const doorLeafClipId = computed((): string => `${props.doorMaskId}-door-leaf-clip`)
const roomBehindGlassUrl = computed((): string | null => {
  if (!props.previewScene) {
    return null
  }

  const shouldUseBlurredInterior = props.canRenderDecorLayer
    && props.selectedVariant?.has_glass
    && props.useBlurredInterior

  return shouldUseBlurredInterior
    ? props.previewScene.interior_background_blurred_url ?? props.previewScene.interior_background_url
    : props.previewScene.interior_background_url
})
</script>

<template>
  <div class="my-5 overflow-hidden bg-black">
    <div class="relative w-full overflow-hidden" :style="{ aspectRatio: sceneAspectRatio }">
      <svg
        v-if="previewScene"
        class="absolute inset-0 h-full w-full"
        :viewBox="sceneViewBox"
        preserveAspectRatio="xMidYMid meet"
        :aria-label="modelName ?? 'Náhled dveří v místnosti'"
        role="img"
      >
        <defs>
          <mask v-if="previewScene.wall_mask_url" :id="wallMaskId" x="0" y="0" :width="sceneWidth" :height="sceneHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
            <image :href="previewScene.wall_mask_url" :width="sceneWidth" :height="sceneHeight" preserveAspectRatio="none" />
          </mask>
          <mask v-if="previewScene.floor_mask_url" :id="floorMaskId" x="0" y="0" :width="sceneWidth" :height="sceneHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
            <image :href="previewScene.floor_mask_url" :width="sceneWidth" :height="sceneHeight" preserveAspectRatio="none" />
          </mask>
        </defs>

        <image v-if="selectedWall?.texture_image_url && previewScene.wall_mask_url" :href="selectedWall.texture_image_url" :width="sceneWidth" :height="sceneHeight" preserveAspectRatio="xMidYMid slice" :mask="`url(#${wallMaskId})`" />
        <image v-if="selectedFloor?.texture_image_url && previewScene.floor_mask_url" :href="selectedFloor.texture_image_url" :width="sceneWidth" :height="sceneHeight" preserveAspectRatio="xMidYMid slice" :mask="`url(#${floorMaskId})`" />
        <image v-if="previewScene.scene_base_url" :href="previewScene.scene_base_url" :width="sceneWidth" :height="sceneHeight" preserveAspectRatio="none" />

        <svg
          v-if="roomBehindGlassUrl && previewScene.doorway_mask_url"
          :x="doorX"
          :y="doorY"
          :width="doorWidth"
          :height="doorHeight"
          :viewBox="doorViewBox"
          preserveAspectRatio="none"
        >
          <defs>
            <mask :id="doorwayMaskId" x="0" y="0" :width="doorCanvasWidth" :height="doorCanvasHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
              <image :href="previewScene.doorway_mask_url" :width="doorCanvasWidth" :height="doorCanvasHeight" preserveAspectRatio="none" />
            </mask>
          </defs>
          <image :href="roomBehindGlassUrl" :width="doorCanvasWidth" :height="doorCanvasHeight" preserveAspectRatio="none" :mask="`url(#${doorwayMaskId})`" />
        </svg>

        <image v-if="previewScene.doorway_depth_url" :href="previewScene.doorway_depth_url" :width="sceneWidth" :height="sceneHeight" preserveAspectRatio="none" />

        <svg
          v-if="canRenderDecorLayer && selectedDecorImageUrl && selectedVariant?.door_mask_url"
          :x="doorX"
          :y="doorY"
          :width="doorWidth"
          :height="doorHeight"
          :viewBox="doorViewBox"
          preserveAspectRatio="none"
          overflow="visible"
        >
          <defs>
            <mask v-if="selectedVariant.frame_mask_url" :id="frameMaskId" x="0" y="0" :width="doorCanvasWidth" :height="doorCanvasHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
              <image :href="selectedVariant.frame_mask_url" :width="doorCanvasWidth" :height="doorCanvasHeight" preserveAspectRatio="none" />
            </mask>
            <mask :id="doorMaskId" x="0" y="0" :width="doorCanvasWidth" :height="doorCanvasHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
              <image :href="selectedVariant.door_mask_url" :width="doorCanvasWidth" :height="doorCanvasHeight" preserveAspectRatio="none" />
            </mask>
            <clipPath :id="doorLeafClipId" clipPathUnits="userSpaceOnUse">
              <rect x="50" y="54" width="327" height="786" />
            </clipPath>
            <pattern v-if="glassLayerImageUrl" :id="glassTexturePatternId" width="96" height="96" patternUnits="userSpaceOnUse">
              <image :href="glassLayerImageUrl" width="96" height="96" preserveAspectRatio="none" />
            </pattern>
          </defs>

          <rect v-if="glassLayerImageUrl && selectedVariant.has_glass" :width="doorCanvasWidth" :height="doorCanvasHeight" :fill="`url(#${glassTexturePatternId})`" :opacity="selectedGlassOpacity" :clip-path="`url(#${doorLeafClipId})`" />
          <image v-if="selectedVariant.frame_base_url" :href="selectedVariant.frame_base_url" :width="doorCanvasWidth" :height="doorCanvasHeight" preserveAspectRatio="none" />
          <image v-if="selectedVariant.frame_mask_url" :href="selectedDecorImageUrl" :width="doorCanvasWidth" :height="doorCanvasHeight" preserveAspectRatio="xMidYMid slice" :mask="`url(#${frameMaskId})`" />
          <image :href="selectedDecorImageUrl" :width="doorCanvasWidth" :height="doorCanvasHeight" preserveAspectRatio="xMidYMid slice" :mask="`url(#${doorMaskId})`" />
          <image v-if="selectedVariant.construction_overlay_url" :href="selectedVariant.construction_overlay_url" :width="doorCanvasWidth" :height="doorCanvasHeight" preserveAspectRatio="none" />
          <image v-if="selectedVariant.handle_overlay_url" :href="selectedVariant.handle_overlay_url" :width="doorCanvasWidth" :height="doorCanvasHeight" preserveAspectRatio="none" />
        </svg>

        <image v-if="previewScene.scene_foreground_url" :href="previewScene.scene_foreground_url" :width="sceneWidth" :height="sceneHeight" preserveAspectRatio="none" />
      </svg>

      <img
        v-else
        :src="selectedPreviewImageUrl ?? ''"
        :alt="modelName ?? 'Náhled dveří'"
        class="absolute inset-0 h-full w-full object-contain"
      >

      <div v-if="previewScene && !canRenderDecorLayer" class="absolute inset-x-4 bottom-4 border border-white/20 bg-dark/80 px-3 py-2 text-center text-[10px] uppercase tracking-[0.14em] text-white/70">
        Náhled dveří se zobrazí po výběru renderovací varianty
      </div>
    </div>
  </div>
</template>
