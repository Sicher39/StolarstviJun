import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  shallowRef,
  type ComponentPublicInstance,
  type ComputedRef,
  type Ref
} from 'vue'

/**
 * The sticky section navigation of a long listing page.
 *
 * The page renders one sticky bar with the sections of the page and one section per item of that bar.
 * The bar has to stay right below the fixed site header (which is not part of the document flow) and
 * the sections have to be scrolled to with the height of both bars subtracted, so the composable owns
 * the measurement, the scroll handler and the active section of the bar.
 *
 * It has to be called from `setup()` of the page component, because it mounts the window listeners.
 */

// The site header is fixed by the layout, so every sticky element below it is offset by the measured
// height of the bar instead of a hard-coded number.
const SITE_HEADER_ID = 'site-header'
const FALLBACK_HEADER_HEIGHT = 120
// A section becomes active once its top passes the bottom edge of the sticky bar. The small tolerance
// keeps the section that was just scrolled to from flickering between two neighbours.
const ACTIVE_SECTION_TOLERANCE = 16
const BOTTOM_TOLERANCE = 2
const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)'

export interface StickySectionNavOptions<TKey extends string> {
  /** Keys of the sections that are rendered right now, in document order. */
  keys: () => TKey[]
  /** Every rendered section is anchored by this prefix and its key (`#prefix-key`). */
  hashPrefix: string
  /** Height used until the fixed site header can be measured. */
  fallbackHeaderHeight?: number
}

export interface StickySectionNav<TKey extends string> {
  headerOffset: Ref<number>
  navHeight: Ref<number>
  stickyOffset: ComputedRef<number>
  activeKey: Ref<TKey | null>
  /** Template ref of the sticky bar, so its own height can be measured. */
  stickyNavElement: Ref<HTMLElement | null>
  setSectionElement: (key: TKey, element: Element | ComponentPublicInstance | null) => void
  scrollToSection: (key: TKey) => void
}

export const useStickySectionNav = <TKey extends string>(
  options: StickySectionNavOptions<TKey>
): StickySectionNav<TKey> => {
  const fallbackHeaderHeight = options.fallbackHeaderHeight ?? FALLBACK_HEADER_HEIGHT

  // The section elements are only read inside event handlers, so they stay out of the reactivity.
  const sectionElements = new Map<TKey, HTMLElement>()

  const stickyNavElement = ref<HTMLElement | null>(null)
  const headerOffset = ref(fallbackHeaderHeight)
  const navHeight = ref(0)

  // A shallow ref is enough: the value is a plain key and the bar only needs the reassignment.
  const activeKey = shallowRef<TKey | null>(options.keys()[0] ?? null)

  const stickyOffset = computed<number>(() => headerOffset.value + navHeight.value)

  const setSectionElement = (
    key: TKey,
    element: Element | ComponentPublicInstance | null
  ): void => {
    // A section is unregistered by a re-render that registers it again, so the last known element is
    // kept for the whole life of the page.
    if (element instanceof HTMLElement) {
      sectionElements.set(key, element)
    }
  }

  const measureOffsets = (): void => {
    const siteHeader = document.getElementById(SITE_HEADER_ID)
    // The mobile menu is a sibling of the visible bar inside the header, so measure only the bar.
    const headerBar = siteHeader?.querySelector('nav')
    const headerHeight = headerBar?.getBoundingClientRect().height ?? 0

    headerOffset.value = Math.round(headerHeight > 0 ? headerHeight : fallbackHeaderHeight)
    navHeight.value = stickyNavElement.value
      ? Math.round(stickyNavElement.value.getBoundingClientRect().height)
      : 0
  }

  const updateActiveSection = (): void => {
    const threshold = stickyOffset.value + ACTIVE_SECTION_TOLERANCE
    const navigableKeys = options.keys()
    let nextActiveKey: TKey | null = navigableKeys[0] ?? null

    navigableKeys.forEach((key) => {
      const element = sectionElements.get(key)

      if (element && element.getBoundingClientRect().top <= threshold) {
        nextActiveKey = key
      }
    })

    // The last section is usually shorter than the viewport, so it never reaches the threshold on its
    // own; the bottom of the page selects it, because everything above it has been read by then.
    const isPageBottom =
      window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - BOTTOM_TOLERANCE

    if (isPageBottom) {
      nextActiveKey = navigableKeys[navigableKeys.length - 1] ?? nextActiveKey
    }

    if (nextActiveKey !== null && nextActiveKey !== activeKey.value) {
      activeKey.value = nextActiveKey
    }
  }

  let frameId: number | null = null

  const scheduleUpdate = (): void => {
    if (frameId !== null) {
      return
    }

    frameId = requestAnimationFrame(() => {
      frameId = null
      measureOffsets()
      updateActiveSection()
    })
  }

  const scrollToSection = (key: TKey): void => {
    // A section that is not rendered has nothing to scroll to.
    const element = sectionElements.get(key)

    if (!element) {
      return
    }

    const prefersReducedMotion = window.matchMedia(REDUCED_MOTION_QUERY).matches
    const top = window.scrollY + element.getBoundingClientRect().top - stickyOffset.value

    window.scrollTo({
      top: Math.max(top, 0),
      behavior: prefersReducedMotion ? 'auto' : 'smooth'
    })
    activeKey.value = key
  }

  const getKeyFromHash = (): TKey | null => {
    const hash = window.location.hash.replace('#', '')

    if (!hash.startsWith(options.hashPrefix)) {
      return null
    }

    const key = hash.slice(options.hashPrefix.length)

    // Only rendered sections can be scrolled to, a hidden one is not a valid target.
    return options.keys().some((candidate) => candidate === key) ? (key as TKey) : null
  }

  onMounted(async (): Promise<void> => {
    await nextTick()

    measureOffsets()
    updateActiveSection()

    const hashKey = getKeyFromHash()

    if (hashKey) {
      scrollToSection(hashKey)
    }

    window.addEventListener('scroll', scheduleUpdate, { passive: true })
    window.addEventListener('resize', scheduleUpdate)
  })

  onBeforeUnmount((): void => {
    window.removeEventListener('scroll', scheduleUpdate)
    window.removeEventListener('resize', scheduleUpdate)

    if (frameId !== null) {
      cancelAnimationFrame(frameId)
      frameId = null
    }
  })

  return {
    headerOffset,
    navHeight,
    stickyOffset,
    activeKey,
    stickyNavElement,
    setSectionElement,
    scrollToSection
  }
}
