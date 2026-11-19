<script setup lang="ts">
import MainLayout from "@/front/layouts/MainLayout.vue";
import SecondHeaderSection from "@/front/components/Sections/SecondHeaderSection.vue";
import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";

defineOptions({
  layout: MainLayout
})

type Contact = {
  company_name: string
  street: string
  city: string
  contact_person: string
  email: string
  phone: string
  registration_number: string
  vat_number: string
  data_box: string
  bank_account: string
  registry_note: string
  google_maps_url: string
  waze_url: string
  seznam_maps_url: string
}

type MapLink = {
  title: string
  url: string
}

const props = defineProps<{
  contact: Contact
}>()

const addressLines = computed<string[]>(() => [
  props.contact.street,
  props.contact.city
])

const mapLinks = computed<MapLink[]>(() => [
  {
    title: 'Google Maps',
    url: props.contact.google_maps_url
  },
  {
    title: 'Waze',
    url: props.contact.waze_url
  },
  {
    title: 'Seznam Mapy',
    url: props.contact.seznam_maps_url
  }
])

const contactPhoneHref = computed<string>(() => props.contact.phone.replace(/\s+/g, ''))

const companyRegistrationLines = computed<string[]>(() => [
  `IČ: ${props.contact.registration_number}`,
  `DIČ: ${props.contact.vat_number}`,
  `Datová schránka: ${props.contact.data_box}`,
  `Číslo účtu: ${props.contact.bank_account}`
])

// The lower section reveals only two blocks: the photo keeps its own reveal and the whole registration block
// (IČ / DIČ / data box / bank account plus the registry note) arrives as one single element, so no line is animated
// separately.
const REVEAL_OFFSET_Y = 60
const REVEAL_DURATION = 1.4

const registrationSectionElement = ref<HTMLElement | null>(null)
let registrationRevealContext: gsap.Context | null = null

onMounted(async (): Promise<void> => {
  await nextTick()

  const rootElement = registrationSectionElement.value

  if (!rootElement) {
    return
  }

  gsap.registerPlugin(ScrollTrigger)

  registrationRevealContext = gsap.context(() => {
    const scopedSelector = gsap.utils.selector(rootElement)
    const revealElements = scopedSelector<HTMLElement>('.revealContact')

    if (!revealElements.length) {
      return
    }

    gsap.set(revealElements, { y: REVEAL_OFFSET_Y, opacity: 0 })

    ScrollTrigger.batch(revealElements, {
      start: 'top 85%',
      once: true,
      onEnter: (batch): void => {
        gsap.to(batch, {
          y: 0,
          opacity: 1,
          duration: REVEAL_DURATION,
          ease: 'power2.out'
        })
      }
    })
  }, rootElement)
})

onBeforeUnmount((): void => {
  registrationRevealContext?.revert()
  registrationRevealContext = null
})
</script>

<template>

    <SecondHeaderSection
        img="contact"
        group-details-reveal
    >
        <template v-slot:header>
            Kontakt
        </template>
        <template v-slot:title>
            {{ props.contact.company_name }}
        </template>

        <template v-slot:details>
            <div class="block mt-10">
                <p
                    v-for="line in addressLines"
                    :key="line"
                    class="leading-tight"
                >
                    {{ line }}
                </p>
            </div>

            <!-- The row is offset by one former link line, so the first link starts where the second (Waze) link used to. -->
            <div class="flex flex-wrap items-baseline gap-x-8 gap-y-3 mt-[4.25rem]">
                <a
                    v-for="mapLink in mapLinks"
                    :key="mapLink.title"
                    :href="mapLink.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="w-fit font-main text-lg uppercase text-accent underline decoration-1 decoration-accent underline-offset-4 hover:text-primary hover:decoration-primary"
                >
                    {{ mapLink.title }}
                </a>
            </div>

            <div class="block mt-24 lg:mt-40">
                <p class="leading-tight">{{ props.contact.contact_person }}</p>
                <p class="leading-tight">
                    <a
                        :href="`mailto:${props.contact.email}`"
                        class="underline-offset-4 decoration-accent hover:underline"
                    >
                        {{ props.contact.email }}
                    </a>
                </p>
                <p class="leading-tight">
                    <a
                        :href="`tel:${contactPhoneHref}`"
                        class="underline-offset-4 decoration-accent hover:underline"
                    >
                        {{ props.contact.phone }}
                    </a>
                </p>
            </div>
        </template>

    </SecondHeaderSection>

    <!--
      This block is intentionally rendered outside FullSection: the photo has to start at the very left edge of
      the screen (no padded dark strip in front of it) while staying narrower than half of the viewport. The
      registration data is vertically centered next to the photo, so it sits around the middle of the image.
    -->
    <section
        ref="registrationSectionElement"
        class="w-full mb-48 mt-24 lg:mt-[100px] xl:mt-[135px] 2xl:mt-[150px]"
    >
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,40vw)_minmax(0,1fr)] items-center gap-y-12">
            <img
                src="/img/bg/small/contact-small.webp"
                class="block w-full lg:w-[40vw] revealContact"
                alt="Provozovna Stolařství Jun a synové s.r.o. v Zastávce u Brna"
            >

            <!-- One shared reveal target: the four registration lines and the registry note always arrive together. -->
            <div class="block px-2 lg:px-10 xl:pl-20 revealContact">
                <p
                    v-for="line in companyRegistrationLines"
                    :key="line"
                    class="leading-tight"
                >
                    {{ line }}
                </p>
                <p class="leading-tight mt-8 lg:mt-10">
                    {{ props.contact.registry_note }}
                </p>
            </div>
        </div>
    </section>

</template>
