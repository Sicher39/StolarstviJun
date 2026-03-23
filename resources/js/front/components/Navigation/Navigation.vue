<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import NavigationItem from './NavigationItem.vue'
import { Link } from '@inertiajs/vue3'
import ButtonLabel from '@/front/components/Buttons/ButtonLabel.vue'
import { navLinks } from '@/front/components/Navigation/NavLinks'

const open = ref(false)
const menuOpen = () => {
  open.value = !open.value
}

type SearchProduct = {
  id: number
  name: string
  url: string | null
}

type SearchLabelCategory = {
  name: string
  slug: string
  url: string
}

type SearchLabel = {
  id: number
  name: string
  url: string
  categories: SearchLabelCategory[]
}

type SearchResults = {
  products: SearchProduct[]
  products_total: number
  labels: SearchLabel[]
}

const searchOpen = ref(false)
const searchQuery = ref('')
const searchInput = ref<HTMLInputElement | null>(null)
const results = ref<SearchResults>({ products: [], products_total: 0, labels: [] })
const isSearching = ref(false)
let searchTimer: number | null = null

const hasResults = computed(() => {
  return results.value.products.length > 0 || results.value.labels.length > 0
})

const clearResults = () => {
  results.value = { products: [], products_total: 0, labels: [] }
}

const closeSearch = () => {
  searchOpen.value = false
  searchQuery.value = ''
  clearResults()
}

const toggleSearch = async () => {
  searchOpen.value = !searchOpen.value
  if (searchOpen.value) {
    await nextTick()
    searchInput.value?.focus()
  } else {
    closeSearch()
  }
}

const routeHelper = (...args: unknown[]) => route(...args)

const fetchResults = async () => {
  const query = searchQuery.value.trim()
  if (query.length < 2) {
    clearResults()
    return
  }

  isSearching.value = true
  try {
    const response = await fetch(routeHelper('front.search', { q: query }), {
      headers: { Accept: 'application/json' }
    })
    if (!response.ok) {
      clearResults()
      return
    }

    results.value = await response.json()
  } catch {
    clearResults()
  } finally {
    isSearching.value = false
  }
}

const scheduleSearch = () => {
  if (searchTimer) {
    window.clearTimeout(searchTimer)
  }
  searchTimer = window.setTimeout(fetchResults, 300)
}

const scrolledFromTop = ref(false)
const handleScroll = () => {
  scrolledFromTop.value = window.scrollY >= 50
}
const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    closeSearch()
  }
}

onMounted(() => {
  handleScroll()
  window.addEventListener('scroll', handleScroll)
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  if (searchTimer) {
    window.clearTimeout(searchTimer)
  }
  window.removeEventListener('scroll', handleScroll)
  window.removeEventListener('keydown', handleKeydown)
})

</script>

<template>
  <div class="block w-full">
    <nav
      class="flex w-full justify-center items-center  px-1 md:px-4 xl:px-0"
      :class="[scrolledFromTop ? 'bg-dark/50 backdrop-blur' : ' ']"
    >
      <div class="flex justify-between w-full px-5 lg:px-10">
        <Link href="/" class="flex justify-center cursor-pointer px-2 md:px-0 md:pr-2 ">
          <img
            :src="`/img/logo/stolarstvi-jun-temp.webp`"
            class="z-50 transition-all ease-out duration-700 my-1"
            :class="[scrolledFromTop ? 'w-[100px] smW:w-[100px]' : 'w-[100px] smW:w-[100px]']"
            alt="logo"
            aria-label="logo"
            width="150"
            height="150"
          />
        </Link>

        <div class="hidden lg:flex w-full justify-end items-center">
          <NavigationItem
            v-for="(link, index) in navLinks"
            :key="index"
            :link="link.link"
            :title="link.title"
            :route-link="link.route"
          />

          <button
            type="button"
            class="text-white hover:text-lightBrow transition"
            aria-label="Open search"
            aria-controls="search-dialog"
            :aria-expanded="searchOpen"
            @click="toggleSearch"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="size-6"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"
              />
            </svg>
          </button>
        </div>

        <div class="flex items-center lg:hidden text-white space-x-3 ">
          <button
            type="button"
            aria-label="Open search"
            aria-controls="search-dialog"
            :aria-expanded="searchOpen"
            @click="toggleSearch"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="w-8 h-8"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"
              />
            </svg>
          </button>
          <button
            type="button"
            class="text-white"
            aria-label="Toggle menu"
            aria-controls="mobile-menu"
            :aria-expanded="open"
            @click="menuOpen"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              aria-hidden="true"
              :class="[
                open ? 'animation ease-in duration-1000' : 'animation ease-in duration-1000',
                scrolledFromTop ? 'w-10 h-10' : 'w-10 h-10'
              ]"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                :d="open ? 'M6 18L18 6M6 6l12 12' : 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12'"
              />
            </svg>
          </button>
        </div>
      </div>
    </nav>

    <div
      v-if="searchOpen"
      class="fixed inset-0 z-50 flex items-start justify-center bg-dark/70 backdrop-blur overflow-y-auto"
      @click.self="closeSearch"
    >
      <div
        id="search-dialog"
        role="dialog"
        aria-modal="true"
        aria-label="Vyhledávání"
        class="w-full max-w-2xl mt-24 bg-dark/90 border border-dark/60 rounded-lg p-6 max-h-[80vh] overflow-y-auto"
      >
        <div class="flex items-center gap-3">
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            placeholder="Vyhledávání produktů"
            aria-label="Vyhledávání produktů"
            class="w-full bg-dark/70 border border-lightBrow text-white px-4 py-3 rounded-md focus:outline-none"
            @input="scheduleSearch"
          />
          <button type="button" class="text-white/70 hover:text-white" @click="closeSearch">
            Zavřít
          </button>
        </div>

        <div v-if="searchQuery.trim().length >= 2" class="mt-6">
          <p v-if="isSearching" class="text-white/70 text-sm">Hledám...</p>

          <div v-if="hasResults" class="space-y-6">
            <div v-if="results.products.length">
              <p class="text-white text-lg font-bold tracking-wide underline mb-2">Produkty</p>
              <div class="space-y-2">
                <Link
                  v-for="product in results.products"
                  :key="product.id"
                  :href="product.url ?? '#'"
                  class="block text-white hover:text-lightBrow transition"
                  @click="closeSearch"
                >
                  {{ product.name }}
                </Link>
              </div>
            </div>

            <div v-if="results.labels.length">
              <p class="text-white underline text-lg font-bold tracking-wide mb-2">Štítky</p>
              <div class="space-y-3">
                <div v-for="label in results.labels" :key="label.id">
                  <Link
                    :href="label.url"
                    class="text-white font-semibold hover:text-lightBrow transition"
                    @click="closeSearch"
                  >
                    {{ label.name }}
                  </Link>
                  <div v-if="label.categories.length" class="text-white/70 text-xs mt-1">
                    <span v-for="category in label.categories" :key="category.slug" class="mr-3">
                      <Link :href="category.url" class="hover:text-white" @click="closeSearch">
                        {{ category.name }}
                      </Link>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="results.products_total > results.products.length"
              class="text-white border-t border-white text-sm py-2"
            >
              <span>
                Zobrazeno {{ results.products.length }} z {{ results.products_total }} produktů.
              </span>
              <div class="block mt-5">
                <Link
                  :href="routeHelper('front.searchIndex', { q: searchQuery.trim() })"
                  class="transition"
                  @click="closeSearch"
                >
                  <ButtonLabel>zobrazit vše</ButtonLabel>
                </Link>
              </div>
            </div>
          </div>

          <p v-else-if="!isSearching" class="text-white/70 text-sm">Nic nenalezeno.</p>
        </div>
      </div>
    </div>

    <div
      id="mobile-menu"
      :aria-hidden="!open"
      class="flex justify-end bg-dark min-h-screen px-4 lg:hidden max-h-screen overflow-y-auto"
      :class="[
        open ? 'animation ease-in duration-1000 w-full' : 'hidden animation ease-in duration-1000'
      ]"
    >
      <div class="block">
        <div class="grid grid-cols-1 pt-5 gap-4">
          <NavigationItem
            v-for="(link, index) in navLinks"
            :key="index"
            :link="link.link"
            :route-link="link.route"
            :title="link.title"
            @click="menuOpen"
          />
        </div>
      </div>
    </div>
  </div>
</template>
