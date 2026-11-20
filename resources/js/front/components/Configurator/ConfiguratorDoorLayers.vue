<script setup lang="ts">
import { computed } from 'vue'

import type { DoorVariant } from './types'
import { doorCanvasSize } from './utils'

/**
 * The door and nothing else: the glass texture, the frame base, the decor of the frame and of the leaf,
 * and the depth, construction and handle overlays of the variant.
 *
 * It is shared by the living room preview (`DoorPreview.vue`) and by the thumbnail of one request item,
 * so both always draw the same stack with the same masks. The room, the wall, the floor and the interior
 * behind the glass are the business of the caller: a request item is illustrated by the door only, which
 * is exactly what the PNG stored with it contains.
 */
const props = defineProps<{
  variant: DoorVariant | null
  decorImageUrl: string | null
  glassImageUrl: string | null
  glassOpacity: number
  // Prefix of every mask and clip of this instance: several doors may be on the page at once.
  maskIdBase: string
  // Placement inside a surrounding scene. Omitted, the door is drawn in its own canvas.
  x?: number
  y?: number
  width?: number
  height?: number
  overflowVisible?: boolean
}>()

const canvasWidth = computed((): number => doorCanvasSize(props.variant).width)
const canvasHeight = computed((): number => doorCanvasSize(props.variant).height)
const viewBox = computed((): string => `0 0 ${canvasWidth.value} ${canvasHeight.value}`)

// The tile run around the frame and around the leaf needs the texture and the mask of the leaf; without
// them the door is still drawn (the frame base and the overlays), only undecorated.
const hasDecor = computed((): boolean => props.decorImageUrl !== null && Boolean(props.variant?.door_mask_url))

const frameMaskId = computed((): string => `${props.maskIdBase}-frame-mask`)
const doorMaskId = computed((): string => `${props.maskIdBase}-door-mask`)
const doorVerticalDecorMaskId = computed((): string => `${props.maskIdBase}-door-vertical-decor-mask`)
const doorHorizontalDecorMaskId = computed((): string => `${props.maskIdBase}-door-horizontal-decor-mask`)
const frameVerticalDecorMaskId = computed((): string => `${props.maskIdBase}-frame-vertical-decor-mask`)
const frameHorizontalDecorMaskId = computed((): string => `${props.maskIdBase}-frame-horizontal-decor-mask`)
const horizontalDecorPatternId = computed((): string => `${props.maskIdBase}-horizontal-decor-pattern`)
const verticalDecorPatternId = computed((): string => `${props.maskIdBase}-vertical-decor-pattern`)
const frameHeaderClipId = computed((): string => `${props.maskIdBase}-frame-header-clip`)
const doorLeafClipId = computed((): string => `${props.maskIdBase}-door-leaf-clip`)
</script>

<template>
  <svg
    :x="x"
    :y="y"
    :width="width ?? canvasWidth"
    :height="height ?? canvasHeight"
    :viewBox="viewBox"
    preserveAspectRatio="none"
    :overflow="overflowVisible ? 'visible' : undefined"
  >
    <!-- A real `<g>` rather than a `<template>`: the children stay in the SVG namespace even without a variant. -->
    <g v-if="variant">
      <defs>
        <mask v-if="hasDecor && variant.frame_mask_url" :id="frameMaskId" x="0" y="0" :width="canvasWidth" :height="canvasHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
          <image :href="variant.frame_mask_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
        </mask>
        <mask v-if="hasDecor" :id="doorMaskId" x="0" y="0" :width="canvasWidth" :height="canvasHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
          <image :href="variant.door_mask_url ?? ''" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
        </mask>
        <mask v-if="hasDecor && variant.door_vertical_decor_mask_url" :id="doorVerticalDecorMaskId" x="0" y="0" :width="canvasWidth" :height="canvasHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
          <image :href="variant.door_vertical_decor_mask_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
        </mask>
        <mask v-if="hasDecor && variant.door_horizontal_decor_mask_url" :id="doorHorizontalDecorMaskId" x="0" y="0" :width="canvasWidth" :height="canvasHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
          <image :href="variant.door_horizontal_decor_mask_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
        </mask>
        <mask v-if="hasDecor && variant.frame_vertical_decor_mask_url" :id="frameVerticalDecorMaskId" x="0" y="0" :width="canvasWidth" :height="canvasHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
          <image :href="variant.frame_vertical_decor_mask_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
        </mask>
        <mask v-if="hasDecor && variant.frame_horizontal_decor_mask_url" :id="frameHorizontalDecorMaskId" x="0" y="0" :width="canvasWidth" :height="canvasHeight" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">
          <image :href="variant.frame_horizontal_decor_mask_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
        </mask>
        <clipPath :id="doorLeafClipId" clipPathUnits="userSpaceOnUse">
          <rect x="50" y="54" width="327" height="786" />
        </clipPath>
        <clipPath v-if="hasDecor" :id="frameHeaderClipId" clipPathUnits="userSpaceOnUse">
          <rect x="0" y="0" :width="canvasWidth" height="54" />
        </clipPath>
        <pattern v-if="hasDecor && decorImageUrl" :id="horizontalDecorPatternId" width="540" height="180" patternUnits="userSpaceOnUse">
          <image :href="decorImageUrl" width="540" height="180" preserveAspectRatio="xMidYMid slice" />
        </pattern>
        <pattern v-if="hasDecor && decorImageUrl" :id="verticalDecorPatternId" width="180" :height="canvasHeight" patternUnits="userSpaceOnUse">
          <image :href="decorImageUrl" :width="canvasHeight" height="180" preserveAspectRatio="xMidYMid slice" transform="translate(180 0) rotate(90)" />
        </pattern>
      </defs>

      <!-- Glass texture is drawn exactly once over the door canvas and centre-cropped (`slice`),
           so the uploaded structure is never stretched nor tiled; the leaf clip keeps it on the panel. -->
      <image
        v-if="glassImageUrl && variant.has_glass"
        :href="glassImageUrl"
        :width="canvasWidth"
        :height="canvasHeight"
        preserveAspectRatio="xMidYMid slice"
        :opacity="glassOpacity"
        :clip-path="`url(#${doorLeafClipId})`"
      />
      <image v-if="variant.frame_base_url" :href="variant.frame_base_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
      <rect
        v-if="hasDecor && variant.frame_vertical_decor_mask_url && variant.frame_horizontal_decor_mask_url"
        :width="canvasWidth"
        :height="canvasHeight"
        :fill="`url(#${verticalDecorPatternId})`"
        :mask="`url(#${frameVerticalDecorMaskId})`"
      />
      <rect
        v-else-if="hasDecor && variant.frame_mask_url"
        :width="canvasWidth"
        :height="canvasHeight"
        :fill="`url(#${verticalDecorPatternId})`"
        :mask="`url(#${frameMaskId})`"
      />
      <rect
        v-if="hasDecor && variant.frame_vertical_decor_mask_url && variant.frame_horizontal_decor_mask_url"
        :width="canvasWidth"
        :height="canvasHeight"
        :fill="`url(#${horizontalDecorPatternId})`"
        :mask="`url(#${frameHorizontalDecorMaskId})`"
      />
      <rect
        v-else-if="hasDecor && variant.frame_mask_url"
        :width="canvasWidth"
        :height="canvasHeight"
        :fill="`url(#${horizontalDecorPatternId})`"
        :mask="`url(#${frameMaskId})`"
        :clip-path="`url(#${frameHeaderClipId})`"
      />
      <rect
        v-if="hasDecor && variant.door_vertical_decor_mask_url && variant.door_horizontal_decor_mask_url"
        :width="canvasWidth"
        :height="canvasHeight"
        :fill="`url(#${verticalDecorPatternId})`"
        :mask="`url(#${doorVerticalDecorMaskId})`"
      />
      <rect
        v-else-if="hasDecor"
        :width="canvasWidth"
        :height="canvasHeight"
        :fill="`url(#${verticalDecorPatternId})`"
        :mask="`url(#${doorMaskId})`"
      />
      <rect
        v-if="hasDecor && variant.door_vertical_decor_mask_url && variant.door_horizontal_decor_mask_url"
        :width="canvasWidth"
        :height="canvasHeight"
        :fill="`url(#${horizontalDecorPatternId})`"
        :mask="`url(#${doorHorizontalDecorMaskId})`"
      />
      <image v-if="variant.depth_overlay_url" :href="variant.depth_overlay_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
      <image v-if="variant.construction_overlay_url" :href="variant.construction_overlay_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
      <image v-if="variant.handle_overlay_url" :href="variant.handle_overlay_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
    </g>
  </svg>
</template>
