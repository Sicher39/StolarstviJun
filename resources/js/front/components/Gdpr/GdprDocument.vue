<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  document: {
    title: string
    perex: string | null
    content: string
    version: string
    effective_from: string | null
    updated_at: string | null
  }
}

const props = defineProps<Props>()

const DATE_FORMATTER = new Intl.DateTimeFormat('cs-CZ', {
  day: 'numeric',
  month: 'numeric',
  year: 'numeric'
})

// Dates come from the server as YYYY-MM-DD; an unparsable value is simply not shown.
const formatDate = (value: string | null): string | null => {
  if (!value) {
    return null
  }

  const parsed = new Date(`${value}T00:00:00`)

  return Number.isNaN(parsed.getTime()) ? null : DATE_FORMATTER.format(parsed)
}

const effectiveFrom = computed<string | null>(() => formatDate(props.document.effective_from))
const updatedAt = computed<string | null>(() => formatDate(props.document.updated_at))
</script>

<template>
  <div class="flex w-full justify-center px-2 py-12 md:pb-20">
    <div class="block w-full 2xl:w-10/12 3xl:w-9/12">
      <div class="mx-auto block w-full lg:w-10/12 xl:w-8/12">
        <p
          v-if="document.perex"
          class="mb-10 text-accent"
        >
          {{ document.perex }}
        </p>

        <!-- The text is written in the administration panel and its variables are escaped on the
             server, so this HTML is the single source of the document body. -->
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div
          class="legal-document"
          v-html="document.content"
        />

        <div class="mt-12 font-main text-base text-accent">
          <p v-if="effectiveFrom">
            Účinnost od {{ effectiveFrom }}
          </p>
          <p>
            Verze {{ document.version }}<template v-if="updatedAt">, naposledy upraveno {{ updatedAt }}</template>.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
