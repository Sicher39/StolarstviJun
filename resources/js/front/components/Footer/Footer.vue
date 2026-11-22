<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import FooterMenuItem from '@/front/components/Footer/FooterMenuItem.vue'
import { navLinks, type NavLink } from '@/front/components/Navigation/NavLinks'
import { useCookieConsent } from '@/front/composables/useCookieConsent'

/**
 * The footer of the public site.
 *
 * The layout mirrors the reference design: one row of five columns (logo with the claim, the site
 * menu, the legal links, the contact details and the company registration) and a separated copyright
 * line below. Every fact comes from the contact record the server shares with all pages, so the
 * footer never repeats a copied address, phone number or registration datum.
 */

interface FooterContact {
  company_name: string
  street: string
  city: string
  email: string
  phone: string
  registration_number: string
  vat_number: string
  data_box: string
  registry_note: string
}

interface Props {
  build?: number
  company?: string
}

const props = withDefaults(defineProps<Props>(), {
  build: 2003,
  company: ''
})

const page = usePage()

const contact = computed<FooterContact | null>(
  () => (page.props.contact as FooterContact | undefined) ?? null
)

const companyName = computed<string>(
  () => props.company || contact.value?.company_name || 'Stolařství Jun a synové s.r.o.'
)

const year = computed<number>(() => new Date().getFullYear())

const copyrightYears = computed<string>(() =>
  props.build === year.value ? `${year.value}` : `${props.build}–${year.value}`
)

const addressLines = computed<string[]>(() => {
  const current = contact.value

  if (!current) {
    return []
  }

  return [current.street, current.city].filter((line) => line !== '')
})

const phoneHref = computed<string>(() => (contact.value?.phone ?? '').replace(/\s+/g, ''))

// Only the facts the contact record really holds are published: an empty value in the administration
// removes the line instead of rendering an empty label.
const registrationLines = computed<string[]>(() => {
  const current = contact.value

  if (!current) {
    return []
  }

  const lines: Array<[string, string]> = [
    ['IČ', current.registration_number],
    ['DIČ', current.vat_number],
    ['Datová schránka', current.data_box]
  ]

  return lines
    .filter(([, value]) => value !== '')
    .map(([label, value]) => `${label}: ${value}`)
})

// All items come from the shared navigation definition, so the footer never drifts from the header.
// The header shows the configurator as a standalone button, so the footer adds its own plain link and
// places it right after the contact item, i.e. as the last footer menu entry.
const footerMenuLinks = computed<NavLink[]>(() => {
  const links: NavLink[] = [...navLinks]
  const configuratorLink: NavLink = {
    title: 'Konfigurátor',
    link: '/konfigurator',
    route: 'front.configurator'
  }
  const contactIndex = links.findIndex((link) => link.route === 'front.contact')

  links.splice(contactIndex === -1 ? links.length : contactIndex + 1, 0, configuratorLink)

  return links
})

const legalLinks: NavLink[] = [
  {
    title: 'Zásady ochrany osobních údajů',
    link: '/ochrana-osobnich-udaju',
    route: 'front.gdpr'
  },
  {
    title: 'Zásady používání cookies',
    link: '/zasady-cookies',
    route: 'front.cookies'
  }
]

const { config, requiresCookieConsent, loadConfig, openPreferences } = useCookieConsent()

const cookieSettingsLabel = computed<string>(
  () => config.value?.settings.footerLinkLabel ?? 'Nastavení cookies'
)

onMounted(() => {
  // Whether the settings link belongs in the footer depends on the configuration, so the footer loads it
  // on mount as well; the composable shares a single request with the cookie dialog.
  void loadConfig()
})

// The dialog owns the state of the visitor's choice, so the footer only asks it to open.
const openCookieSettings = (): void => {
  void openPreferences()
}
</script>

<template>
  <footer class="w-full border-t border-accent/40 bg-dark">
    <div class="content-shell pb-8 pt-14 lg:pb-10 lg:pt-20">
      <div class="grid grid-cols-1 gap-10 xs:grid-cols-2 lg:grid-cols-4 lg:gap-8 xl:grid-cols-5">
        <!-- Logo with the claim -->
        <div class="col-span-1 xs:col-span-2 lg:col-span-4 xl:col-span-1">
          <Link href="/" class="inline-flex w-fit">
            <img
              src="/img/logo/COL-stolarstvi-jun.svg"
              class="h-auto w-[150px]"
              alt="Stolařství Jun a synové"
              width="150"
              height="88"
            >
          </Link>

          <p class="mt-6 max-w-sm text-sm leading-relaxed text-primary/80">
            Každý kus dřeva má svůj příběh, my mu dáme tvar podle vašich představ.
          </p>
        </div>

        <!-- Site menu (intentionally without a heading) -->
        <nav aria-label="Menu v zápatí">
          <ul class="space-y-1">
            <li v-for="link in footerMenuLinks" :key="link.route">
              <FooterMenuItem :link="link.link" :title="link.title" :route-link="link.route" />
            </li>
          </ul>
        </nav>

        <!-- Legal information -->
        <nav aria-label="Právní informace">
          <ul class="space-y-1">
            <li v-for="link in legalLinks" :key="link.route">
              <FooterMenuItem :link="link.link" :title="link.title" :route-link="link.route" />
            </li>
            <!-- A site with technical cookies only has nothing to configure, so the entry stays hidden. -->
            <li v-if="requiresCookieConsent">
              <button
                type="button"
                class="inline-flex items-center py-1 font-main text-sm font-thin text-primary underline-offset-4 transition hover:text-accent hover:underline hover:decoration-accent"
                @click="openCookieSettings"
              >
                {{ cookieSettingsLabel }}
              </button>
            </li>
          </ul>
        </nav>

        <!-- Contact details -->
        <address class="not-italic">
          <p v-for="line in addressLines" :key="line" class="text-sm text-primary/80">
            {{ line }}
          </p>

          <p v-if="contact" class="mt-3 text-sm">
            <a
              :href="`mailto:${contact.email}`"
              class="text-primary/80 underline-offset-4 transition hover:text-accent hover:underline hover:decoration-accent"
            >
              {{ contact.email }}
            </a>
          </p>

          <p v-if="contact" class="mt-1 text-sm">
            <a
              :href="`tel:${phoneHref}`"
              class="text-primary/80 underline-offset-4 transition hover:text-accent hover:underline hover:decoration-accent"
            >
              {{ contact.phone }}
            </a>
          </p>
        </address>

        <!-- Company registration -->
        <div>
          <p v-for="line in registrationLines" :key="line" class="text-sm text-primary/80">
            {{ line }}
          </p>

          <p v-if="contact?.registry_note" class="mt-3 text-sm text-primary/60">
            {{ contact.registry_note }}
          </p>
        </div>
      </div>

      <div class="mt-12 border-t border-primary/15 pt-6">
        <p class="text-sm text-primary/70">
          © {{ copyrightYears }} {{ companyName }}
        </p>
      </div>
    </div>
  </footer>
</template>
