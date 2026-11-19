<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

import ConfiguratorDecorStep from '@/front/components/Configurator/ConfiguratorDecorStep.vue'
import ConfiguratorGlassStep from '@/front/components/Configurator/ConfiguratorGlassStep.vue'
import ConfiguratorInquiryForm from '@/front/components/Configurator/ConfiguratorInquiryForm.vue'
import ConfiguratorModelStep from '@/front/components/Configurator/ConfiguratorModelStep.vue'
import ConfiguratorSceneMaterialStep from '@/front/components/Configurator/ConfiguratorSceneMaterialStep.vue'
import ConfiguratorSummaryCard from '@/front/components/Configurator/ConfiguratorSummaryCard.vue'
import ConfiguratorSurchargeStep from '@/front/components/Configurator/ConfiguratorSurchargeStep.vue'
import ConfiguratorVariantStep from '@/front/components/Configurator/ConfiguratorVariantStep.vue'
import DoorPreview from '@/front/components/Configurator/DoorPreview.vue'
import { composeDoorPreviewPng } from '@/front/components/Configurator/doorPreviewImage'
import type { DecorOption, DoorModel, DoorVariant, GlassOption, PreviewScene, PreviewSceneMaterial, SurchargeOption } from '@/front/components/Configurator/types'
import { formatPrice, priceWithVat as calculatePriceWithVat, variantLabel } from '@/front/components/Configurator/utils'
import FullSection from '@/front/components/Sections/FullSection.vue'
import MainLayout from '@/front/layouts/MainLayout.vue'

interface PageProps {
  flash?: {
    success?: string
  }
}

interface ConfiguratorStep {
  id: number
  label: string
}

// Shown when the browser cannot compose the illustration of the chosen door. The inquiry is deliberately
// not sent in that case, so the visitor never receives a quote whose stored preview is missing by accident.
const PREVIEW_ERROR_MESSAGE = 'Náhled vybraných dveří se nepodařilo složit, proto se poptávka neodeslala. Zkuste to prosím znovu, případně použijte jiný prohlížeč.'

const props = defineProps<{
  doorModels: DoorModel[]
  previewScene: PreviewScene | null
  vatRate: number
}>()

defineOptions({
  layout: MainLayout,
})

const page = usePage<PageProps>()

const initialModel = props.doorModels[0] ?? null

// The payload already resolves the model's effective defaults, but the client stays defensive: a stale or unknown
// id falls back to the first (lowest id) option published for the model.
const resolveInitialVariantId = (model: DoorModel | null): number | null => {
  if (!model) {
    return null
  }

  const initialVariantId = model.initial_variant_id

  if (initialVariantId !== null && model.variants.some((variant) => variant.id === initialVariantId)) {
    return initialVariantId
  }

  return model.variants[0]?.id ?? null
}

const resolveInitialDecorId = (model: DoorModel | null): number | null => {
  if (!model) {
    return null
  }

  const initialDecorId = model.initial_decor_id

  if (initialDecorId !== null && model.decors.some((decor) => decor.id === initialDecorId)) {
    return initialDecorId
  }

  return model.decors[0]?.id ?? null
}

const selectedModelId = ref<number | null>(initialModel?.id ?? null)
const selectedVariantId = ref<number | null>(resolveInitialVariantId(initialModel))
const selectedDecorId = ref<number | null>(resolveInitialDecorId(initialModel))
const selectedGlassId = ref<number | null>(null)
const selectedWallId = ref<number | null>(props.previewScene?.materials.find((material) => material.type === 'wall')?.id ?? null)
const selectedFloorId = ref<number | null>(props.previewScene?.materials.find((material) => material.type === 'floor')?.id ?? null)
const selectedSurchargeIds = ref<number[]>([])
const activeStep = ref(1)
// The preview is composed asynchronously right before submitting, so the button is blocked until it exists.
const isPreparingPreview = ref(false)
const previewError = ref('')

const inquiryForm = useForm({
  door_model_id: null as number | null,
  door_variant_id: null as number | null,
  decor_id: null as number | null,
  glass_type_id: null as number | null,
  surcharge_ids: [] as number[],
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  customer_message: '',
  // The acknowledgement of the privacy policy is required by the server and is stored as evidence of
  // the consent; it is deliberately not a marketing opt-in.
  gdpr_consent: false,
  // Filled right before submitting with the PNG of the chosen door; the server accepts it as an
  // illustration only and validates the selection and the price on its own.
  door_preview: null as File | null,
})

const hasDoorModels = computed((): boolean => props.doorModels.length > 0)

const wallMaterials = computed((): PreviewSceneMaterial[] => {
  return props.previewScene?.materials.filter((material) => material.type === 'wall') ?? []
})

const floorMaterials = computed((): PreviewSceneMaterial[] => {
  return props.previewScene?.materials.filter((material) => material.type === 'floor') ?? []
})

const hasPreviewSceneMaterials = computed((): boolean => wallMaterials.value.length > 0 || floorMaterials.value.length > 0)

const selectedWall = computed((): PreviewSceneMaterial | null => {
  return wallMaterials.value.find((material) => material.id === selectedWallId.value) ?? null
})

const selectedFloor = computed((): PreviewSceneMaterial | null => {
  return floorMaterials.value.find((material) => material.id === selectedFloorId.value) ?? null
})

const selectedModel = computed((): DoorModel | null => {
  return props.doorModels.find((model) => model.id === selectedModelId.value) ?? null
})

const selectedVariant = computed((): DoorVariant | null => {
  return selectedModel.value?.variants.find((variant) => variant.id === Number(selectedVariantId.value)) ?? null
})

const selectedDecor = computed((): DecorOption | null => {
  return selectedModel.value?.decors.find((decor) => decor.id === Number(selectedDecorId.value)) ?? null
})

const selectedDecorImageUrl = computed((): string | null => selectedDecor.value?.texture_image_url ?? null)

const canRenderDecorLayer = computed((): boolean => {
  return Boolean(selectedDecorImageUrl.value && selectedVariant.value?.door_mask_url)
})

const glassAvailableForVariant = computed((): boolean => selectedVariant.value?.has_glass ?? false)

const availableGlasses = computed((): GlassOption[] => {
  return selectedModel.value && glassAvailableForVariant.value ? selectedModel.value.glasses : []
})

const selectedGlass = computed((): GlassOption | null => {
  return availableGlasses.value.find((glass) => glass.id === Number(selectedGlassId.value)) ?? null
})

const previewSvgIdBase = computed((): string => {
  return `door-preview-${selectedVariant.value?.id ?? 'none'}-${selectedDecor.value?.id ?? 'none'}-${selectedGlass.value?.id ?? 'none'}`
})

const doorMaskId = computed((): string => `${previewSvgIdBase.value}-door-mask`)
const selectedGlassImageUrl = computed((): string | null => selectedGlass.value?.texture_image_url ?? null)
const glassLayerImageUrl = computed((): string | null => selectedGlassImageUrl.value)
const useBlurredInterior = computed((): boolean => {
  return selectedGlass.value !== null && selectedGlass.value.code !== 'cire-sklo'
})

const selectedGlassOpacity = computed((): number => {
  const opacity = selectedGlass.value?.opacity

  return typeof opacity === 'number' && !Number.isNaN(opacity)
    ? Math.min(Math.max(opacity / 100, 0), 1)
    : 1
})

const selectedSurcharges = computed((): SurchargeOption[] => {
  return selectedModel.value?.surcharges.filter((surcharge) => selectedSurchargeIds.value.includes(surcharge.id)) ?? []
})

const firstStepId = computed((): number => 1)
const sceneStepId = computed((): number | null => hasPreviewSceneMaterials.value ? 1 : null)
const modelStepId = computed((): number => hasPreviewSceneMaterials.value ? 2 : 1)
const variantStepId = computed((): number => modelStepId.value + 1)
const decorStepId = computed((): number => variantStepId.value + 1)
const glassStepId = computed((): number | null => glassAvailableForVariant.value ? decorStepId.value + 1 : null)
const surchargeStepId = computed((): number | null => {
  if ((selectedModel.value?.surcharges.length ?? 0) === 0) {
    return null
  }

  return (glassStepId.value ?? decorStepId.value) + 1
})
const inquiryStepId = computed((): number => (surchargeStepId.value ?? glassStepId.value ?? decorStepId.value) + 1)

const steps = computed((): ConfiguratorStep[] => {
  const configuredSteps: ConfiguratorStep[] = []

  if (sceneStepId.value !== null) {
    configuredSteps.push({ id: sceneStepId.value, label: 'Prostředí' })
  }

  configuredSteps.push(
    { id: modelStepId.value, label: 'Model' },
    { id: variantStepId.value, label: 'Varianta' },
    { id: decorStepId.value, label: 'Dekor' },
  )

  if (glassStepId.value !== null) {
    configuredSteps.push({ id: glassStepId.value, label: 'Sklo' })
  }

  if (surchargeStepId.value !== null) {
    configuredSteps.push({ id: surchargeStepId.value, label: 'Doplňky' })
  }

  configuredSteps.push({ id: inquiryStepId.value, label: 'Poptávka' })

  return configuredSteps
})

const currentStepLabel = computed((): string => {
  return steps.value.find((step) => step.id === activeStep.value)?.label ?? 'Konfigurace'
})

const priceWithoutVat = computed((): number => {
  if (!selectedModel.value) {
    return 0
  }

  const surchargeTotal = selectedSurcharges.value.reduce((sum, surcharge) => sum + surcharge.price_without_vat, 0)

  return selectedModel.value.base_price_without_vat
    + (selectedVariant.value?.price_modifier ?? 0)
    + (selectedDecor.value?.price_modifier ?? 0)
    + (selectedGlass.value?.price_modifier ?? 0)
    + surchargeTotal
})

const priceWithVat = computed((): number => calculatePriceWithVat(priceWithoutVat.value, props.vatRate))
const flashSuccess = computed((): string => page.props.flash?.success ?? '')
const canSubmitInquiry = computed((): boolean => selectedModelId.value !== null && selectedVariantId.value !== null)

const nextStep = (): void => {
  const currentIndex = steps.value.findIndex((step) => step.id === activeStep.value)
  const next = steps.value[currentIndex + 1]

  if (next) {
    activeStep.value = next.id
  }
}

const previousStep = (): void => {
  const currentIndex = steps.value.findIndex((step) => step.id === activeStep.value)
  const previous = steps.value[currentIndex - 1]

  if (previous) {
    activeStep.value = previous.id
  }
}

// Model-scoped selections cannot survive a model change, so they fall back to the model's effective defaults.
const applyModelDefaults = (model: DoorModel | null): void => {
  selectedVariantId.value = resolveInitialVariantId(model)
  selectedDecorId.value = resolveInitialDecorId(model)
  selectedSurchargeIds.value = []
  selectedGlassId.value = model?.glasses[0] && model.variants.some((variant) => variant.has_glass) ? model.glasses[0].id : null
}

// Picking an option only changes the selection and the preview. The visitor advances exclusively via „Pokračovat“.
const selectModel = (modelId: number | null): void => {
  selectedModelId.value = modelId
}

const selectVariant = (variantId: number | null): void => {
  selectedVariantId.value = variantId
}

const selectDecor = (decorId: number | null): void => {
  selectedDecorId.value = decorId
}

const selectGlass = (glassId: number | null): void => {
  selectedGlassId.value = glassId
}

const selectWall = (materialId: number | null): void => {
  selectedWallId.value = materialId
}

const selectFloor = (materialId: number | null): void => {
  selectedFloorId.value = materialId
}

const toggleSurcharge = (surchargeId: number): void => {
  selectedSurchargeIds.value = selectedSurchargeIds.value.includes(surchargeId)
    ? selectedSurchargeIds.value.filter((id) => id !== surchargeId)
    : [...selectedSurchargeIds.value, surchargeId]
}

const submitInquiry = async (): Promise<void> => {
  if (!canSubmitInquiry.value || isPreparingPreview.value) {
    return
  }

  previewError.value = ''
  isPreparingPreview.value = true

  try {
    // The illustration is composed right before the request, so it always matches the current selection.
    inquiryForm.door_preview = await composeDoorPreviewPng({
      variant: selectedVariant.value,
      decorTextureUrl: selectedDecorImageUrl.value,
      glassTextureUrl: glassLayerImageUrl.value,
      glassOpacity: selectedGlassOpacity.value,
    })
  } catch {
    inquiryForm.door_preview = null
    previewError.value = PREVIEW_ERROR_MESSAGE

    return
  } finally {
    isPreparingPreview.value = false
  }

  inquiryForm.transform((data) => ({
    ...data,
    door_model_id: selectedModelId.value,
    door_variant_id: selectedVariantId.value,
    decor_id: selectedDecorId.value,
    glass_type_id: glassAvailableForVariant.value ? selectedGlassId.value : null,
    surcharge_ids: [...selectedSurchargeIds.value],
  })).post(route('front.configurator.inquiry.store'), {
    preserveScroll: true,
    onSuccess: () => {
      inquiryForm.reset('customer_name', 'customer_email', 'customer_phone', 'customer_message', 'gdpr_consent')
      inquiryForm.clearErrors()
    },
    onFinish: () => {
      // A sent file must never be attached to the next inquiry in the same session.
      inquiryForm.door_preview = null
    },
  })
}

watch(selectedModel, (model) => {
  applyModelDefaults(model)
}, { immediate: true })

watch([selectedVariant, availableGlasses], ([variant, glasses]) => {
  if (!variant?.has_glass) {
    selectedGlassId.value = null
    return
  }

  if (!glasses.some((glass) => glass.id === Number(selectedGlassId.value))) {
    selectedGlassId.value = glasses[0]?.id ?? null
  }
}, { immediate: true })

watch(steps, (configuredSteps, previousSteps) => {
  if (configuredSteps.some((step) => step.id === activeStep.value)) {
    return
  }

  // A vanished step (for example glass after a variant without glass) must not push the visitor forward, so the
  // closest previous step is restored instead.
  const removedIndex = previousSteps.findIndex((step) => step.id === activeStep.value)
  const fallbackIndex = Math.min(Math.max(removedIndex - 1, 0), configuredSteps.length - 1)

  activeStep.value = configuredSteps[fallbackIndex]?.id ?? 1
})

const selectedPreviewImageUrl = computed((): string | null => {
  return selectedVariant.value?.source_reference_url ?? selectedModel.value?.preview_image_url ?? null
})
</script>

<template>
  <div class="pt-24 pb-16 lg:pt-28">
    <FullSection>
      <header class="flex flex-wrap items-end justify-between gap-5 border-b border-white/15 pb-7">
        <div>
          <p class="text-accent text-xs uppercase tracking-[0.3em]">Konfigurátor</p>
          <h1 class="mt-3 text-4xl leading-none text-white uppercase lg:text-6xl">Dveře na míru</h1>
        </div>
        <p class="text-right text-sm tracking-[0.16em] text-white/55">
          Orientační cena: <span class="text-accent">{{ formatPrice(priceWithVat) }} s DPH</span>
        </p>
      </header>
    </FullSection>

    <FullSection v-if="hasDoorModels">
      <div class="mt-8 grid gap-6 lg:grid-cols-12 lg:items-start">
        <section class="border border-white/15 bg-white/5 p-5 lg:col-span-5 lg:sticky lg:top-28 lg:p-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-accent text-xs uppercase tracking-[0.25em]">Náhled</p>
              <h2 class="mt-2 text-3xl text-white">{{ selectedModel?.name ?? 'Vyberte model' }}</h2>
            </div>
            <p class="text-right text-xs tracking-[0.18em] text-white/50">{{ selectedVariant ? variantLabel(selectedVariant) : '—' }}</p>
          </div>

          <DoorPreview
            :model-name="selectedModel?.name ?? null"
            :can-render-decor-layer="canRenderDecorLayer"
            :selected-preview-image-url="selectedPreviewImageUrl"
            :selected-decor-image-url="selectedDecorImageUrl"
            :selected-variant="selectedVariant"
            :preview-scene="previewScene"
            :selected-wall="selectedWall"
            :selected-floor="selectedFloor"
            :door-mask-id="doorMaskId"
            :glass-layer-image-url="glassLayerImageUrl"
            :selected-glass-opacity="selectedGlassOpacity"
            :use-blurred-interior="useBlurredInterior"
          />

        </section>

        <section class="border border-white/15 bg-white/5 lg:col-span-7">
          <div class="border-b border-white/10 px-5 py-4 lg:px-6">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
              <button
                v-for="step in steps"
                :key="step.id"
                type="button"
                class="border px-3 py-3 text-left transition"
                :class="activeStep === step.id ? 'border-accent bg-accent/10 text-white' : 'border-white/10 text-white/50 hover:border-white/35 hover:text-white'"
                @click="activeStep = step.id"
              >
                <span class="block text-[10px] uppercase tracking-[0.18em] text-accent">0{{ step.id }}</span>
                <span class="mt-1 block text-xs uppercase">{{ step.label }}</span>
              </button>
            </div>
          </div>

          <div class="p-5 lg:p-6">
            <div class="mb-5 flex items-center justify-between gap-4">
              <div>
                <p class="text-accent text-xs uppercase tracking-[0.25em]">Krok 0{{ activeStep }}</p>
                <h2 class="mt-2 text-3xl text-white uppercase">{{ currentStepLabel }}</h2>
              </div>
              <p class="text-right text-sm text-white/55">{{ formatPrice(priceWithVat) }} s DPH</p>
            </div>

            <div v-if="activeStep === sceneStepId" class="space-y-6">
              <p class="max-w-2xl text-sm leading-6 text-white/65">Vyberte materiály, ve kterých si chcete dveře prohlédnout. Změny se ihned projeví v náhledu.</p>
              <ConfiguratorSceneMaterialStep
                v-if="wallMaterials.length"
                title="Barva stěny"
                :materials="wallMaterials"
                :selected-material-id="selectedWallId"
                @update:selected-material-id="selectWall"
              />
              <ConfiguratorSceneMaterialStep
                v-if="floorMaterials.length"
                title="Podlaha"
                :materials="floorMaterials"
                :selected-material-id="selectedFloorId"
                @update:selected-material-id="selectFloor"
              />
            </div>
            <ConfiguratorModelStep
              v-else-if="activeStep === modelStepId"
              :door-models="doorModels"
              :selected-model-id="selectedModelId"
              :vat-rate="vatRate"
              @update:selected-model-id="selectModel"
            />
            <ConfiguratorVariantStep
              v-else-if="activeStep === variantStepId"
              :variants="selectedModel?.variants ?? []"
              :selected-variant-id="selectedVariantId"
              :vat-rate="vatRate"
              @update:selected-variant-id="selectVariant"
            />
            <ConfiguratorDecorStep
              v-else-if="activeStep === decorStepId"
              :decors="selectedModel?.decors ?? []"
              :selected-decor-id="selectedDecorId"
              :vat-rate="vatRate"
              @update:selected-decor-id="selectDecor"
            />
            <ConfiguratorGlassStep
              v-else-if="activeStep === glassStepId && glassAvailableForVariant"
              :glasses="availableGlasses"
              :selected-glass-id="selectedGlassId"
              :vat-rate="vatRate"
              @update:selected-glass-id="selectGlass"
            />
            <ConfiguratorSurchargeStep
              v-else-if="activeStep === surchargeStepId && (selectedModel?.surcharges.length ?? 0) > 0"
              :surcharges="selectedModel?.surcharges ?? []"
              :selected-surcharge-ids="selectedSurchargeIds"
              :vat-rate="vatRate"
              @toggle-surcharge="toggleSurcharge"
            />
            <div v-else-if="activeStep === inquiryStepId" class="grid gap-5 lg:grid-cols-2">
              <ConfiguratorSummaryCard
                :selected-model-name="selectedModel?.name ?? null"
                :selected-variant-label="selectedVariant ? variantLabel(selectedVariant) : null"
                :selected-decor-name="selectedDecor?.name ?? null"
                :selected-glass-name="selectedGlass?.name ?? null"
                :selected-surcharge-names="selectedSurcharges.map((surcharge) => surcharge.name)"
                :price-without-vat="priceWithoutVat"
                :price-with-vat="priceWithVat"
                :vat-rate="vatRate"
              />
              <ConfiguratorInquiryForm
                :form="inquiryForm"
                :flash-success="flashSuccess"
                :can-submit-inquiry="canSubmitInquiry"
                :is-preparing-preview="isPreparingPreview"
                :preview-error="previewError"
                @submit="submitInquiry"
              />
            </div>

            <div v-if="activeStep !== inquiryStepId" class="mt-5 flex items-center justify-between gap-4 border-t border-white/10 pt-5">
              <button
                type="button"
                class="text-xs uppercase tracking-[0.18em] text-white/55 transition hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                :disabled="activeStep === firstStepId"
                @click="previousStep"
              >
                Zpět
              </button>
              <button
                type="button"
                class="border border-accent px-5 py-3 text-xs uppercase tracking-[0.18em] text-accent transition hover:bg-accent hover:text-dark"
                @click="nextStep"
              >
                Pokračovat
              </button>
            </div>
          </div>
        </section>
      </div>
    </FullSection>

    <FullSection v-else>
      <div class="mt-8 border border-white/15 bg-white/5 p-8 text-center">
        <p class="text-accent text-xs uppercase tracking-[0.25em]">Konfigurátor je připraven</p>
        <h2 class="mt-4 text-3xl text-white uppercase">Zatím nejsou dostupné žádné modely</h2>
      </div>
    </FullSection>
  </div>
</template>
