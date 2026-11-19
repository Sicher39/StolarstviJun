import type { DoorVariant } from './types'

/**
 * Composes the chosen door into a single PNG right before the inquiry is sent.
 *
 * The layers, their order and the masks are the same ones the live preview draws for the door itself in
 * `DoorPreview.vue`:
 *
 *   1. the glass texture, cropped to the door leaf and faded by the glass opacity,
 *   2. the frame base image,
 *   3. the frame decor - the vertical and the horizontal run of the decor texture,
 *   4. the door decor - the vertical and the horizontal run of the decor texture,
 *   5. the depth, construction and handle overlays of the variant.
 *
 * The room is deliberately left out: no wall, no floor, no scene base, no foreground, no doorway depth and
 * no interior behind the glass. Nothing about the visitor is drawn either, so the file carries no personal
 * data. The image is only an illustration - the selected configuration and the prices are always validated
 * by the server from the database.
 *
 * One difference is intentional: the live preview wraps the door, its decor and the glass in a block that
 * only renders when a decor texture is available, so a variant without a decor texture shows no door at
 * all. Here the frame, the overlays and the glass are always drawn, because the illustration has to match
 * the selection even then.
 *
 * The browser cannot rasterise an SVG that references remote files, so every layer is downloaded first and
 * inlined as a data URL. All layers come from the application's own media disk, so the canvas stays
 * untainted and `toBlob` keeps working.
 */

const SVG_NAMESPACE = 'http://www.w3.org/2000/svg'
const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink'

const PREVIEW_FILE_NAME = 'nahlad-konfigurace.png'

// Mirrors of the values `DoorPreview.vue` uses when it renders the door into the scene.
const FALLBACK_CANVAS_WIDTH = 426
const FALLBACK_CANVAS_HEIGHT = 900
const DOOR_LEAF_CLIP = { x: 50, y: 54, width: 327, height: 786 }
const FRAME_HEADER_HEIGHT = 54
const HORIZONTAL_DECOR_TILE_WIDTH = 540
const HORIZONTAL_DECOR_TILE_HEIGHT = 180
const VERTICAL_DECOR_TILE_WIDTH = 180
const VERTICAL_DECOR_TILE_HEIGHT = 180

// The server accepts at most 2000 px per edge and 5 MB, a 1200 px long edge keeps the PNG well inside.
const TARGET_LONG_EDGE = 1200
const MAX_SCALE = 2

export interface DoorPreviewCompositeInput {
  variant: DoorVariant | null
  decorTextureUrl: string | null
  glassTextureUrl: string | null
  glassOpacity: number
}

export interface DoorPreviewLayers {
  canvasWidth: number
  canvasHeight: number
  hasGlass: boolean
  glassOpacity: number
  frameBase: string
  frameMask: string | null
  doorMask: string | null
  doorVerticalDecorMask: string | null
  doorHorizontalDecorMask: string | null
  frameVerticalDecorMask: string | null
  frameHorizontalDecorMask: string | null
  depthOverlay: string | null
  constructionOverlay: string | null
  handleOverlay: string | null
  decorTexture: string | null
  glassTexture: string | null
}

interface DoorPreviewSources {
  frameBase: string
  frameMask: string | null
  doorMask: string | null
  doorVerticalDecorMask: string | null
  doorHorizontalDecorMask: string | null
  frameVerticalDecorMask: string | null
  frameHorizontalDecorMask: string | null
  depthOverlay: string | null
  constructionOverlay: string | null
  handleOverlay: string | null
  decorTexture: string | null
  glassTexture: string | null
}

const imageTag = (href: string, attributes: Record<string, string | number>): string => {
  const serialized = Object.entries(attributes)
    .map(([name, value]) => `${name}="${value}"`)
    .join(' ')

  return `<image href="${href}" xlink:href="${href}" ${serialized} />`
}

// The masks are luminance masks of the uploaded PNGs, exactly like the ones of the live preview.
const maskTag = (id: string, href: string, width: number, height: number): string => {
  const image = imageTag(href, { width, height, preserveAspectRatio: 'none' })

  return `<mask id="${id}" x="0" y="0" width="${width}" height="${height}" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" mask-type="luminance">${image}</mask>`
}

const decorRectTag = (
  width: number,
  height: number,
  patternId: string,
  maskId: string,
  clipId: string | null = null
): string => {
  const clip = clipId === null ? '' : ` clip-path="url(#${clipId})"`

  return `<rect width="${width}" height="${height}" fill="url(#${patternId})" mask="url(#${maskId})"${clip} />`
}

/**
 * Builds the standalone SVG of the door only. Exported so the layer stack can be inspected without a
 * browser; the layer arguments are already inlined data URLs.
 */
export const buildDoorPreviewSvg = (layers: DoorPreviewLayers): string => {
  const width = layers.canvasWidth
  const height = layers.canvasHeight
  const defs: string[] = []
  const body: string[] = []
  const hasDecor = layers.decorTexture !== null && layers.doorMask !== null

  defs.push(
    `<clipPath id="door-leaf-clip" clipPathUnits="userSpaceOnUse">` +
      `<rect x="${DOOR_LEAF_CLIP.x}" y="${DOOR_LEAF_CLIP.y}" width="${DOOR_LEAF_CLIP.width}" height="${DOOR_LEAF_CLIP.height}" />` +
      `</clipPath>`
  )

  if (hasDecor) {
    if (layers.frameMask !== null) {
      defs.push(maskTag('frame-mask', layers.frameMask, width, height))
    }

    if (layers.doorMask !== null) {
      defs.push(maskTag('door-mask', layers.doorMask, width, height))
    }

    if (layers.doorVerticalDecorMask !== null) {
      defs.push(maskTag('door-vertical-decor-mask', layers.doorVerticalDecorMask, width, height))
    }

    if (layers.doorHorizontalDecorMask !== null) {
      defs.push(maskTag('door-horizontal-decor-mask', layers.doorHorizontalDecorMask, width, height))
    }

    if (layers.frameVerticalDecorMask !== null) {
      defs.push(maskTag('frame-vertical-decor-mask', layers.frameVerticalDecorMask, width, height))
    }

    if (layers.frameHorizontalDecorMask !== null) {
      defs.push(
        maskTag('frame-horizontal-decor-mask', layers.frameHorizontalDecorMask, width, height)
      )
    }

    defs.push(
      `<clipPath id="frame-header-clip" clipPathUnits="userSpaceOnUse">` +
        `<rect x="0" y="0" width="${width}" height="${FRAME_HEADER_HEIGHT}" />` +
        `</clipPath>`
    )

    if (layers.decorTexture !== null) {
      const horizontalTile = imageTag(layers.decorTexture, {
        width: HORIZONTAL_DECOR_TILE_WIDTH,
        height: HORIZONTAL_DECOR_TILE_HEIGHT,
        preserveAspectRatio: 'xMidYMid slice'
      })
      const verticalTile = imageTag(layers.decorTexture, {
        width: height,
        height: VERTICAL_DECOR_TILE_HEIGHT,
        preserveAspectRatio: 'xMidYMid slice',
        transform: `translate(${VERTICAL_DECOR_TILE_WIDTH} 0) rotate(90)`
      })

      defs.push(
        `<pattern id="horizontal-decor-pattern" width="${HORIZONTAL_DECOR_TILE_WIDTH}" height="${HORIZONTAL_DECOR_TILE_HEIGHT}" patternUnits="userSpaceOnUse">${horizontalTile}</pattern>`
      )
      defs.push(
        `<pattern id="vertical-decor-pattern" width="${VERTICAL_DECOR_TILE_WIDTH}" height="${height}" patternUnits="userSpaceOnUse">${verticalTile}</pattern>`
      )
    }
  }

  // Glass texture is drawn once over the door canvas and centre-cropped (`slice`), so the uploaded
  // structure is neither stretched nor tiled; the leaf clip keeps it on the panel.
  if (layers.hasGlass && layers.glassTexture !== null) {
    body.push(
      imageTag(layers.glassTexture, {
        width,
        height,
        preserveAspectRatio: 'xMidYMid slice',
        opacity: layers.glassOpacity,
        'clip-path': 'url(#door-leaf-clip)'
      })
    )
  }

  body.push(imageTag(layers.frameBase, { width, height, preserveAspectRatio: 'none' }))

  if (hasDecor) {
    const splitFrameDecor =
      layers.frameVerticalDecorMask !== null && layers.frameHorizontalDecorMask !== null
    const splitDoorDecor =
      layers.doorVerticalDecorMask !== null && layers.doorHorizontalDecorMask !== null

    if (splitFrameDecor) {
      body.push(decorRectTag(width, height, 'vertical-decor-pattern', 'frame-vertical-decor-mask'))
    } else if (layers.frameMask !== null) {
      body.push(decorRectTag(width, height, 'vertical-decor-pattern', 'frame-mask'))
    }

    if (splitFrameDecor) {
      body.push(
        decorRectTag(width, height, 'horizontal-decor-pattern', 'frame-horizontal-decor-mask')
      )
    } else if (layers.frameMask !== null) {
      body.push(
        decorRectTag(width, height, 'horizontal-decor-pattern', 'frame-mask', 'frame-header-clip')
      )
    }

    if (splitDoorDecor) {
      body.push(decorRectTag(width, height, 'vertical-decor-pattern', 'door-vertical-decor-mask'))
    } else if (layers.doorMask !== null) {
      body.push(decorRectTag(width, height, 'vertical-decor-pattern', 'door-mask'))
    }

    if (splitDoorDecor) {
      body.push(
        decorRectTag(width, height, 'horizontal-decor-pattern', 'door-horizontal-decor-mask')
      )
    }
  }

  if (layers.depthOverlay !== null) {
    body.push(imageTag(layers.depthOverlay, { width, height, preserveAspectRatio: 'none' }))
  }

  if (layers.constructionOverlay !== null) {
    body.push(imageTag(layers.constructionOverlay, { width, height, preserveAspectRatio: 'none' }))
  }

  if (layers.handleOverlay !== null) {
    body.push(imageTag(layers.handleOverlay, { width, height, preserveAspectRatio: 'none' }))
  }

  return [
    `<svg xmlns="${SVG_NAMESPACE}" xmlns:xlink="${XLINK_NAMESPACE}" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none">`,
    `<defs>${defs.join('')}</defs>`,
    body.join(''),
    '</svg>'
  ].join('')
}

const readAsDataUrl = async (url: string): Promise<string> => {
  const response = await fetch(url, { credentials: 'same-origin' })

  if (!response.ok) {
    throw new Error(`Podklad náhledu se nepodařilo načíst (${response.status}).`)
  }

  const blob = await response.blob()

  return await new Promise<string>((resolve, reject) => {
    const reader = new FileReader()

    reader.onload = (): void => {
      if (typeof reader.result === 'string') {
        resolve(reader.result)

        return
      }

      reject(new Error('Podklad náhledu se nepodařilo převést na data URL.'))
    }

    reader.onerror = (): void => {
      reject(new Error('Podklad náhledu se nepodařilo převést na data URL.'))
    }

    reader.readAsDataURL(blob)
  })
}

const inlineSources = async (sources: DoorPreviewSources): Promise<Map<string, string>> => {
  const urls = [...new Set(Object.values(sources).filter((url): url is string => url !== null))]
  const entries = await Promise.all(
    urls.map(async (url): Promise<[string, string]> => [url, await readAsDataUrl(url)])
  )

  return new Map(entries)
}

const loadImage = (source: string): Promise<HTMLImageElement> =>
  new Promise<HTMLImageElement>((resolve, reject) => {
    const image = new Image()

    image.onload = (): void => resolve(image)
    image.onerror = (): void => reject(new Error('Složený náhled se nepodařilo vykreslit.'))
    image.src = source
  })

const rasterize = async (svg: string, width: number, height: number): Promise<Blob> => {
  const scale = Math.min(MAX_SCALE, TARGET_LONG_EDGE / Math.max(width, height))
  const canvas = document.createElement('canvas')

  canvas.width = Math.max(1, Math.round(width * scale))
  canvas.height = Math.max(1, Math.round(height * scale))

  const context = canvas.getContext('2d')

  if (context === null) {
    throw new Error('Prohlížeč neumí náhled složit.')
  }

  if (typeof canvas.toBlob !== 'function') {
    throw new Error('Prohlížeč neumí náhled uložit jako PNG.')
  }

  context.imageSmoothingEnabled = true
  context.imageSmoothingQuality = 'high'

  const raster = await loadImage(`data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`)
  context.drawImage(raster, 0, 0, canvas.width, canvas.height)

  const blob = await new Promise<Blob | null>((resolve) => {
    canvas.toBlob((result) => resolve(result), 'image/png')
  })

  if (blob === null) {
    throw new Error('Složený náhled se nepodařilo uložit jako PNG.')
  }

  return blob
}

/**
 * Returns the PNG of the chosen door, or `null` when the variant has no renderable frame at all - an
 * inquiry without a catalog image must still be submittable, so nothing is generated in that case and the
 * administration keeps using its catalog fallback.
 *
 * Every other problem (a layer that cannot be downloaded, a canvas that cannot be drawn) is thrown, so the
 * caller can show a Czech error and keep the inquiry from being sent.
 */
export const composeDoorPreviewPng = async (
  input: DoorPreviewCompositeInput
): Promise<File | null> => {
  const variant = input.variant

  if (variant === null || !variant.frame_base_url) {
    return null
  }

  const canvasWidth = variant.canvas_width > 0 ? variant.canvas_width : FALLBACK_CANVAS_WIDTH
  const canvasHeight = variant.canvas_height > 0 ? variant.canvas_height : FALLBACK_CANVAS_HEIGHT
  const sources: DoorPreviewSources = {
    frameBase: variant.frame_base_url,
    frameMask: variant.frame_mask_url,
    doorMask: variant.door_mask_url,
    doorVerticalDecorMask: variant.door_vertical_decor_mask_url,
    doorHorizontalDecorMask: variant.door_horizontal_decor_mask_url,
    frameVerticalDecorMask: variant.frame_vertical_decor_mask_url,
    frameHorizontalDecorMask: variant.frame_horizontal_decor_mask_url,
    depthOverlay: variant.depth_overlay_url,
    constructionOverlay: variant.construction_overlay_url,
    handleOverlay: variant.handle_overlay_url,
    // The decor texture is only used with a door mask, the glass only on glass variants.
    decorTexture: variant.door_mask_url ? input.decorTextureUrl : null,
    glassTexture: variant.has_glass ? input.glassTextureUrl : null
  }

  const inlined = await inlineSources(sources)
  const frameBase = inlined.get(sources.frameBase)

  if (frameBase === undefined) {
    throw new Error('Rám dveří se nepodařilo načíst.')
  }

  const resolve = (url: string | null): string | null =>
    url === null ? null : inlined.get(url) ?? null

  const svg = buildDoorPreviewSvg({
    canvasWidth,
    canvasHeight,
    hasGlass: variant.has_glass,
    glassOpacity: input.glassOpacity,
    frameBase,
    frameMask: resolve(sources.frameMask),
    doorMask: resolve(sources.doorMask),
    doorVerticalDecorMask: resolve(sources.doorVerticalDecorMask),
    doorHorizontalDecorMask: resolve(sources.doorHorizontalDecorMask),
    frameVerticalDecorMask: resolve(sources.frameVerticalDecorMask),
    frameHorizontalDecorMask: resolve(sources.frameHorizontalDecorMask),
    depthOverlay: resolve(sources.depthOverlay),
    constructionOverlay: resolve(sources.constructionOverlay),
    handleOverlay: resolve(sources.handleOverlay),
    decorTexture: resolve(sources.decorTexture),
    glassTexture: resolve(sources.glassTexture)
  })

  const blob = await rasterize(svg, canvasWidth, canvasHeight)

  return new File([blob], PREVIEW_FILE_NAME, { type: 'image/png' })
}
