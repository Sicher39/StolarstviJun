<script setup lang="ts">
import { computed } from 'vue'

import type { DoorVariant } from './types'
import { doorCanvasSize } from './utils'

/**
 * The door and nothing else: the glass texture, the frame base, the decor of the frame and of the leaf,
 * and the depth, construction and handle overlays of the variant.
 *
 * The direction of the decor run follows the production drawing: the leaf and the side jambs of the frame carry
 * the grain downwards over their whole area (the moulded strips and the solid stiles of the leaf alike) and the
 * head piece of the frame carries it across. The decor zones of the leaf only say which part of the leaf is
 * textured, never in which direction.
 *
 * The run that goes downwards repeats in mirror: the tile below another tile is the same tile turned upside down
 * about the edge the two share, so the grain folds back over the same wood instead of jumping to the first row of
 * the texture. The run across the head piece is not mirrored, it repeats the single tile.
 *
 * It is shared by the living room preview (`DoorPreview.vue`) and by the thumbnail of one request item,
 * so both always draw the same stack with the same masks. The room, the wall, the floor and the interior
 * behind the glass are the business of the caller: a request item is illustrated by the door only, which
 * is exactly what the PNG stored with it contains.
 */
const props = defineProps<{
  variant: DoorVariant | null
  decorImageUrl: string | null
  // The direction the grain runs in the uploaded texture: the run that has to go downwards is drawn from the
  // stored tile when the source already runs downwards, and from the quarter-turned tile when it runs across.
  // The opposite run always takes the other tile.
  decorTextureIsHorizontal: boolean
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

// The uploaded decor texture is a square close-up, so both decor runs share one square tile: on a square source
// the `slice` crop removes nothing, the leaf, the head piece and the side jambs are drawn at one scale, and the
// square tile repeats once per 540 canvas units instead of once per 180 as a wide band did. Kept in sync with
// `DECOR_TILE_SIZE` of `doorPreviewImage.ts`, so the live preview and the PNG tile identically.
const decorTileSize = 540

// The downwards run repeats a mirror pair of the tile instead of the tile itself. One period of the pattern is
// therefore two tiles: the upper one drawn as it is, the lower one turned upside down about the edge the two
// share. The pair - never the single tile - is what repeats, so the height of the mirror period is fixed by the
// tile alone and no renderer has to assume a canvas height: a canvas that ends inside the lower half of the pair
// shows only part of it and the next pair still starts seamlessly. Kept in sync with
// `DECOR_MIRROR_PATTERN_HEIGHT` of `doorPreviewImage.ts`.
const decorMirrorPatternHeight = decorTileSize * 2

// A quarter turn swaps the two axes of the square tile, so it is what turns a downwards source into an across
// run and an across source into a downwards run. The translate equals the tile size, so the turned tile covers
// the square tile exactly. The two orientations therefore only differ in which run is drawn straight.
const quarterTurn = `translate(${decorTileSize} 0) rotate(90)`

// The lower tile of the mirror pair is the tile above it reflected in their shared edge: both sides of the seam
// carry the same texture row and the copy runs back up the same wood. The translate equals the height of the
// whole pair, and the quarter turn of an across source stays innermost, so the copy is that turned tile seen
// upside down.
const mirrorTile = `translate(0 ${decorMirrorPatternHeight}) scale(1 -1)`

const verticalDecorTileTransform = computed((): string | undefined => {
  return props.decorTextureIsHorizontal ? quarterTurn : undefined
})
const mirroredVerticalDecorTileTransform = computed((): string => {
  return props.decorTextureIsHorizontal ? `${mirrorTile} ${quarterTurn}` : mirrorTile
})
const horizontalDecorTileTransform = computed((): string | undefined => {
  return props.decorTextureIsHorizontal ? undefined : quarterTurn
})

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
        <!-- Two runs of the very same square tile, differing only in the direction of the grain. One run is drawn
             straight, the other turned by a quarter turn, so the grain runs along the canvas width in one of them
             and downwards in the other. Which one is straight depends on the source: a texture that already runs
             downwards is drawn straight on the leaf and the jambs, a texture that runs across is drawn straight
             on the head piece. That keeps the leaf and the jambs downwards and the head piece across for every
             uploaded source, without touching the image. Both runs share one crop and one scale, so a texture
             that reads as one close-up on the leaf reads as the same close-up on the jambs.
             The run across the head piece repeats the single tile. The downwards run of the leaf and of the jambs
             repeats a mirror pair: the upper tile of the pair is drawn as it is, the lower tile is the same tile
             reflected in the edge they share, so both sides of the seam show one texture row and the grain never
             jumps at a tile boundary. Both tiles of the pair carry the same turn, so a source that runs across is
             turned before it is reflected and the fold reads the same for either orientation. -->
        <pattern v-if="hasDecor && decorImageUrl" :id="horizontalDecorPatternId" x="0" y="0" :width="decorTileSize" :height="decorTileSize" patternUnits="userSpaceOnUse">
          <image :href="decorImageUrl" :width="decorTileSize" :height="decorTileSize" preserveAspectRatio="xMidYMid slice" :transform="horizontalDecorTileTransform" />
        </pattern>
        <pattern v-if="hasDecor && decorImageUrl" :id="verticalDecorPatternId" x="0" y="0" :width="decorTileSize" :height="decorMirrorPatternHeight" patternUnits="userSpaceOnUse">
          <image :href="decorImageUrl" :width="decorTileSize" :height="decorTileSize" preserveAspectRatio="xMidYMid slice" :transform="verticalDecorTileTransform" />
          <image :href="decorImageUrl" :width="decorTileSize" :height="decorTileSize" preserveAspectRatio="xMidYMid slice" :transform="mirroredVerticalDecorTileTransform" />
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
      <!-- The frame keeps the direction drawn on the production drawing: the side jambs run the grain
           downwards (the vertical pattern), the head piece runs it across (the horizontal pattern). -->
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
      <!-- The leaf carries the grain downwards over its whole area, the moulded strips and the two solid
           stiles included. The door zones therefore do not choose the direction of the run: they only say which
           part of the leaf is textured, and every one of them is filled with the vertical pattern, so the grain
           runs downwards without a break across the whole leaf - and, because the leaf and the side jambs share
           one pattern origin, in phase with them. -->
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
        :fill="`url(#${verticalDecorPatternId})`"
        :mask="`url(#${doorHorizontalDecorMaskId})`"
      />
      <image v-if="variant.depth_overlay_url" :href="variant.depth_overlay_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
      <image v-if="variant.construction_overlay_url" :href="variant.construction_overlay_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
      <image v-if="variant.handle_overlay_url" :href="variant.handle_overlay_url" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="none" />
    </g>
  </svg>
</template>
