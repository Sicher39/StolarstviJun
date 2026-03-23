<script setup lang="ts">
import { computed } from 'vue'
import FlexSection from '@/front/components/Sections/FlexSection.vue'
import DayItem from '@/front/components/OpeningHours/DayItem.vue'
import { usePage } from '@inertiajs/vue3'
import FooterMenuItem from '@/front/components/Footer/FooterMenuItem.vue'
import SocialIcon from '@/front/components/SocialIcons/SocialIcon.vue'
import FooterPersonalContact from '@/front/components/Contacts/FooterPersonalContact.vue'
import { navLinks } from '@/front/components/Navigation/NavLinks'

interface Props {
  build?: number
  company?: string
}

const props = withDefaults(defineProps<Props>(), {
  build: 2003,
  company: 'Jun a synové s.r.o.'
})

const { build, company } = props
const year = computed(() => new Date().getFullYear())

const page = usePage()
const openingHours = computed(() => {
  return page.props.openingHours as any[]
})

const socialIcon = computed(() => {
  return page.props.socialIcon as any[]
})

const footerContacts = computed(() => {
  return page.props.footerContacts as any[]
})
</script>

<template>
  <div class="block border-t  w-full">
    <div class="block w-full pb-10 pt-10">
      <FlexSection>
        <!-- Textový obsah nad obrázkem -->
        <div class="relative w-full z-10">
          <div
            class="grid grid-cols-1 smW:grid-cols-2 lg:grid-cols-3 gap-10 lg:gap-20 pb-10 md:pb-0"
          >
            <!--            main footer-->
            <div class="block order-3 lg:order-1">
              <div v-if="Array.isArray(footerContacts) && footerContacts.length" class="block">
                <p class="text-primary font-thin text-xl leading-tight">
                  Rychlý kontakt:
                </p>
                <div class="border-b border-primary w-20"></div>
                <FooterPersonalContact
                  v-for="(person, index) in footerContacts"
                  :key="index"
                  v-bind="person"
                />
              </div>

              <div v-if="Array.isArray(socialIcon) && socialIcon.length" class="block mt-10">
                <p class="text-primary font-thin text-xl leading-tight">
                  Sociální sítě:
                </p>
                <div class="border-b border-primary w-20"></div>
                <div
                  class="flex flex-wrap w-full justify-start py-3 gap-3"
                >
                  <SocialIcon v-for="(icon, i) in socialIcon" :key="i" v-bind="icon" />
                </div>
              </div>

              <div class="hidden lg:block mt-5">
                <p
                  class="font-main font-normal text-sm text-primary text-center lg:text-left"
                >
                  <span v-if="build === year">&copy; {{ year }} {{ company }}</span>
                  <span v-else>&copy; {{ build }}–{{ year }} {{ company }}</span>
                </p>
                <p
                  class="font-main font-normal text-primary text-center lg:text-left text-[16px] pb-5 mt-2"
                >
                  <a href="/ochrana-osobnich-udaju">
                    <span
                      class="text-primary hover:underline decoration-light underline-offset-2 text-[14px]"
                    >
                      <br />podmínky ochrany osobních údajů
                    </span>
                  </a>
                </p>
              </div>
            </div>

            <!-- menu items -->
            <div class="block h-full order-3 lg:order-2">
              <p class="text-primary font-thin text-xl leading-tight">Menu:</p>
              <div class="border-b text-primary  w-20"></div>
              <div class="grid grid-cols-1 w-full mt-5">
                <FooterMenuItem
                  v-for="(link, index) in navLinks"
                  :key="index"
                  :link="link.link"
                  :title="link.title"
                  :route-link="link.route"
                />
              </div>
            </div>

            <!-- Contact and opening hours -->
            <div class="block h-full order-1 lg:order-3">
              <div v-if="Array.isArray(openingHours) && openingHours.length" class="block w-full">
                <p class="text-primary font-thin text-xl leading-tight">
                  Provozní doba:
                </p>
                <div class="border-b text-primary w-20"></div>
                <div class="block pt-5">
                  <DayItem v-for="(item, i) in openingHours" :key="i" v-bind="item" footer />
                </div>
              </div>
            </div>

            <div class="block lg:hidden order-4">
              <p
                class="font-main font-normal text-sm lg:text-lg xl:text-xl text-primary text-left"
              >
                <span v-if="build === year">&copy; {{ year }} {{ company }}</span>
                <span v-else>&copy; {{ build }}–{{ year }} {{ company }}</span>
              </p>
              <p
                class="font-main font-normal text-primary text-left text-[16px] pb-5 mt-2"
              >
                <a href="/ochrana-osobnich-udaju">
                  <span
                    class="text-light hover:underline decoration-light underline-offset-2 text-[14px]"
                  >
                    <br />podmínky ochrany osobních údajů
                  </span>
                </a>
              </p>
            </div>
          </div>
        </div>
      </FlexSection>
    </div>
  </div>
</template>
