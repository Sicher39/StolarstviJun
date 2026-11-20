<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

import ConfiguratorDecorStep from '@/front/components/Configurator/ConfiguratorDecorStep.vue'
import ConfiguratorGlassStep from '@/front/components/Configurator/ConfiguratorGlassStep.vue'
import ConfiguratorInquiryForm from '@/front/components/Configurator/ConfiguratorInquiryForm.vue'
import ConfiguratorModelStep from '@/front/components/Configurator/ConfiguratorModelStep.vue'
import ConfiguratorRequestItemCard from '@/front/components/Configurator/ConfiguratorRequestItemCard.vue'
import ConfiguratorRequestItems from '@/front/components/Configurator/ConfiguratorRequestItems.vue'
import ConfiguratorRequestRecap from '@/front/components/Configurator/ConfiguratorRequestRecap.vue'
import ConfiguratorSceneMaterialStep from '@/front/components/Configurator/ConfiguratorSceneMaterialStep.vue'
import ConfiguratorSummaryCard from '@/front/components/Configurator/ConfiguratorSummaryCard.vue'
import ConfiguratorSurchargeStep from '@/front/components/Configurator/ConfiguratorSurchargeStep.vue'
import ConfiguratorVariantStep from '@/front/components/Configurator/ConfiguratorVariantStep.vue'
import DoorPreview from '@/front/components/Configurator/DoorPreview.vue'
import { composeDoorPreviewPng } from '@/front/components/Configurator/doorPreviewImage'
import type {
  ConfiguratorLimits,
  DecorOption,
  DoorModel,
  DoorVariant,
  GlassOption,
  InquiryItemDraft,
  InquiryItemPayload,
  PreviewScene,
  PreviewSceneMaterial,
  SurchargeOption
} from '@/front/components/Configurator/types'
import {
  defaultItemName,
  formatItemCount,
  formatPieceCount,
  formatPrice,
  glassOpacityFraction,
  priceWithVat as calculatePriceWithVat,
  sumPriceWithoutVat,
  sumQuantities,
  variantLabel
} from '@/front/components/Configurator/utils'
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

// Everything the visitor can build in the steps of the open configuration. It is captured as plain values, so
// editing a saved row can put the unfinished draft back exactly as it was.
interface ConfiguratorDraftState {
  selectedModelId: number | null
  selectedVariantId: number | null
  selectedDecorId: number | null
  selectedGlassId: number | null
  selectedWallId: number | null
  selectedFloorId: number | null
  selectedSurchargeIds: number[]
  activeStep: number
  currentItemName: string
  currentItemQuantity: number
  previewItemUid: string | null
}

// Shown when the browser cannot compose the illustration of a configured door. The inquiry is deliberately
// not sent in that case, so the visitor never receives a quote whose stored preview is missing by accident.
const PREVIEW_ERROR_MESSAGE = 'Náhled vybraných dveří se nepodařilo složit, proto se poptávka neodeslala. Zkuste to prosím znovu, případně použijte jiný prohlížeč.'

// The configuration the visitor is working on right now is always part of the request. It gets this fixed
// uid while it is being built and is copied with a fresh uid when it is added to the request.
const CURRENT_ITEM_UID = 'current-configuration'

// The site header is fixed by the layout and the logo shrinks once the page is scrolled, so every sticky
// element below it is offset by the measured height of the bar instead of a hard-coded number.
const SITE_HEADER_ID = 'site-header'
const FALLBACK_HEADER_HEIGHT = 120
// Breathing space between the header bar and the sticky columns, mirroring the padding the page starts with.
const STICKY_TOP_GAP = 16

const FALLBACK_LIMITS: ConfiguratorLimits = {
  max_items: 20,
  max_quantity_per_item: 100,
}

const props = defineProps<{
  doorModels: DoorModel[]
  previewScene: PreviewScene | null
  vatRate: number
  // Mirrors of the server limits; optional so a cached page payload without them still works.
  limits?: ConfiguratorLimits
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

const limits = computed((): ConfiguratorLimits => props.limits ?? FALLBACK_LIMITS)
const maxItems = computed((): number => limits.value.max_items)
const maxQuantityPerItem = computed((): number => limits.value.max_quantity_per_item)

const roundPrice = (value: number): number => Math.round(value * 100) / 100

const clampQuantity = (value: number): number => {
  if (!Number.isFinite(value)) {
    return 1
  }

  return Math.min(Math.max(Math.round(value), 1), maxQuantityPerItem.value)
}

const selectedModelId = ref<number | null>(initialModel?.id ?? null)
const selectedVariantId = ref<number | null>(resolveInitialVariantId(initialModel))
const selectedDecorId = ref<number | null>(resolveInitialDecorId(initialModel))
const selectedGlassId = ref<number | null>(null)
const selectedWallId = ref<number | null>(props.previewScene?.materials.find((material) => material.type === 'wall')?.id ?? null)
const selectedFloorId = ref<number | null>(props.previewScene?.materials.find((material) => material.type === 'floor')?.id ?? null)
const selectedSurchargeIds = ref<number[]>([])
const activeStep = ref(1)
// Configurations that are already added to the request. The one being built right now is kept separately,
// because it has to stay reactive to the steps of the configurator.
const addedItems = ref<InquiryItemDraft[]>([])
const currentItemName = ref('')
const currentItemQuantity = ref(1)
// The item reopened through „Upravit“: its configuration becomes the open one and saving writes it back
// into the same row instead of adding a second entry.
const editingItem = ref<{ uid: string; index: number; item: InquiryItemDraft } | null>(null)
// The unfinished configuration the visitor had open right before „Upravit“ replaced it with a saved row. It is
// put back untouched when that edit is saved or cancelled, so editing never costs a draft.
const draftBeforeEdit = ref<ConfiguratorDraftState | null>(null)
// The item whose door is drawn in the large preview. `null` means the open configuration is drawn.
const previewItemUid = ref<string | null>(null)
// The preview is composed asynchronously right before submitting, so the button is blocked until it exists.
const isPreparingPreview = ref(false)
const previewError = ref('')
// Measured height of the fixed site header, kept up to date while the page scrolls (the logo shrinks).
const headerOffset = ref(FALLBACK_HEADER_HEIGHT)
const stickyTop = computed((): number => headerOffset.value + STICKY_TOP_GAP)

const inquiryForm = useForm({
  // Filled right before submitting, one entry per item of the request. The server prices every item from
  // the database and only stores the PNG as an illustration.
  items: [] as InquiryItemPayload[],
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  customer_message: '',
  // The acknowledgement of the privacy policy is required by the server and is stored as evidence of
  // the consent; it is deliberately not a marketing opt-in.
  gdpr_consent: false,
})

let itemUidCounter = 0

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

const selectedGlassImageUrl = computed((): string | null => selectedGlass.value?.texture_image_url ?? null)
const glassLayerImageUrl = computed((): string | null => selectedGlassImageUrl.value)
const useBlurredInterior = computed((): boolean => {
  return selectedGlass.value !== null && selectedGlass.value.code !== 'cire-sklo'
})

const selectedGlassOpacity = computed((): number => glassOpacityFraction(selectedGlass.value))

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

// Walking through the configurator returns the big preview to the open configuration: a door of the request
// is only shown while its card stays selected.
const goToStep = (stepId: number): void => {
  previewItemUid.value = null
  activeStep.value = stepId
}

// The price of one piece is the documented server formula, computed from the published catalog values only.
const currentUnitPriceWithoutVat = computed((): number => {
  const model = selectedModel.value

  if (!model) {
    return 0
  }

  const surchargeTotal = selectedSurcharges.value.reduce((sum, surcharge) => sum + surcharge.price_without_vat, 0)

  return roundPrice(
    model.base_price_without_vat
      + (selectedVariant.value?.price_modifier ?? 0)
      + (selectedDecor.value?.price_modifier ?? 0)
      + (selectedGlass.value?.price_modifier ?? 0)
      + surchargeTotal
  )
})

// The configuration being built right now, expressed as the item it will become. It is re-derived from the
// steps on every change, so the recap below always equals what would be sent.
const currentItemDraft = computed((): InquiryItemDraft | null => {
  const model = selectedModel.value
  const variant = selectedVariant.value

  if (!model || !variant) {
    return null
  }

  return {
    uid: CURRENT_ITEM_UID,
    name: currentItemName.value.trim() || defaultItemName(model.name, variant.code),
    quantity: clampQuantity(currentItemQuantity.value),
    door_model_id: model.id,
    door_variant_id: variant.id,
    decor_id: selectedDecor.value?.id ?? null,
    glass_type_id: glassAvailableForVariant.value ? selectedGlass.value?.id ?? null : null,
    surcharge_ids: [...selectedSurchargeIds.value],
    model_name: model.name,
    variant_label: variantLabel(variant),
    variant,
    decor: selectedDecor.value,
    glass: selectedGlass.value,
    surcharges: selectedSurcharges.value,
    unit_price_without_vat: currentUnitPriceWithoutVat.value,
  }
})

// Every item of the request: the added ones first, the configuration being built last.
const requestItems = computed((): InquiryItemDraft[] => {
  return currentItemDraft.value ? [...addedItems.value, currentItemDraft.value] : [...addedItems.value]
})

const requestPriceWithoutVat = computed((): number => sumPriceWithoutVat(requestItems.value))
const requestPriceWithVat = computed((): number => calculatePriceWithVat(requestPriceWithoutVat.value, props.vatRate))
const requestTotalDoors = computed((): number => sumQuantities(requestItems.value))

const flashSuccess = computed((): string => page.props.flash?.success ?? '')
const canSubmitInquiry = computed((): boolean => requestItems.value.length > 0)

// The configuration being built right now always occupies one row of the request. Adding it freezes it and
// opens a new (empty) draft, so the button needs room for two rows; saving an edited item keeps the count,
// which is why it only needs its own row.
const canCommitCurrentItem = computed((): boolean => {
  if (currentItemDraft.value === null) {
    return false
  }

  if (editingItem.value !== null) {
    return addedItems.value.length + 1 <= maxItems.value
  }

  return addedItems.value.length + 2 <= maxItems.value
})

const nextStep = (): void => {
  const currentIndex = steps.value.findIndex((step) => step.id === activeStep.value)
  const next = steps.value[currentIndex + 1]

  if (next) {
    goToStep(next.id)
  }
}

const previousStep = (): void => {
  const currentIndex = steps.value.findIndex((step) => step.id === activeStep.value)
  const previous = steps.value[currentIndex - 1]

  if (previous) {
    goToStep(previous.id)
  }
}

// Model-scoped selections cannot survive a model change, so they fall back to the model's effective defaults.
const applyModelDefaults = (model: DoorModel | null): void => {
  selectedVariantId.value = resolveInitialVariantId(model)
  selectedDecorId.value = resolveInitialDecorId(model)
  selectedSurchargeIds.value = []
  selectedGlassId.value = model?.glasses[0] && model.variants.some((variant) => variant.has_glass) ? model.glasses[0].id : null
}

// The watcher of the model resets the selections that belong to it. Restoring an edited item sets the model
// and its selections in one go, so it must not be followed by that reset.
let isRestoringItemSelection = false

// Starting a new configuration returns the visitor to the first step with a clean, valid selection.
const startNewConfiguration = (): void => {
  const model = props.doorModels[0] ?? null

  editingItem.value = null
  previewItemUid.value = null
  selectedModelId.value = model?.id ?? null
  selectedVariantId.value = resolveInitialVariantId(model)
  selectedDecorId.value = resolveInitialDecorId(model)
  selectedGlassId.value = null
  selectedSurchargeIds.value = []
  currentItemName.value = ''
  currentItemQuantity.value = 1
  activeStep.value = firstStepId.value
}

// Picking an option only changes the selection and the preview. The visitor advances exclusively via „Pokračovat“.
// Changing the configuration also returns the large preview to the open configuration: it always draws what
// the visitor is about to send.
const selectModel = (modelId: number | null): void => {
  previewItemUid.value = null
  selectedModelId.value = modelId
}

const selectVariant = (variantId: number | null): void => {
  previewItemUid.value = null
  selectedVariantId.value = variantId
}

const selectDecor = (decorId: number | null): void => {
  previewItemUid.value = null
  selectedDecorId.value = decorId
}

const selectGlass = (glassId: number | null): void => {
  previewItemUid.value = null
  selectedGlassId.value = glassId
}

// The materials only change the room of the preview, so a selected item may stay in it.
const selectWall = (materialId: number | null): void => {
  selectedWallId.value = materialId
}

const selectFloor = (materialId: number | null): void => {
  selectedFloorId.value = materialId
}

const toggleSurcharge = (surchargeId: number): void => {
  previewItemUid.value = null
  selectedSurchargeIds.value = selectedSurchargeIds.value.includes(surchargeId)
    ? selectedSurchargeIds.value.filter((id) => id !== surchargeId)
    : [...selectedSurchargeIds.value, surchargeId]
}

// Committing locks the open configuration into the request and returns the visitor to the beginning, where
// the next door can be configured. Its own name and quantity are kept, so two variants of the same model
// stay apart. When an existing item was reopened, the same entry is updated instead of a second one added and
// the draft that was open before the edit comes back, so saving it never costs that draft.
const commitCurrentItem = (): void => {
  const draft = currentItemDraft.value

  if (!draft || !canCommitCurrentItem.value) {
    return
  }

  const editing = editingItem.value

  if (editing) {
    const items = [...addedItems.value]
    items.splice(Math.min(editing.index, items.length), 0, { ...draft, uid: editing.uid })
    addedItems.value = items
    finishItemEdit()

    return
  }

  itemUidCounter += 1
  addedItems.value = [...addedItems.value, { ...draft, uid: `item-${itemUidCounter}` }]

  startNewConfiguration()
}

// Restoring a stored item into the steps of the configurator. The selections are set after the model, and the
// reset of the model watcher is suppressed until the next render, otherwise it would overwrite exactly the
// variant, decor, glass and surcharges that are being restored.
const applyItemSelection = (item: InquiryItemDraft): void => {
  isRestoringItemSelection = true

  selectedModelId.value = item.door_model_id
  selectedVariantId.value = item.door_variant_id
  selectedDecorId.value = item.decor_id
  selectedGlassId.value = item.glass_type_id
  selectedSurchargeIds.value = [...item.surcharge_ids]
  currentItemName.value = item.name
  currentItemQuantity.value = clampQuantity(item.quantity)
  activeStep.value = firstStepId.value

  void nextTick(() => {
    isRestoringItemSelection = false
  })
}

// The open configuration as plain values. Editing a saved row takes the steps over, so an unfinished draft has
// to be captured first in order to survive the edit.
const captureDraftState = (): ConfiguratorDraftState => {
  return {
    selectedModelId: selectedModelId.value,
    selectedVariantId: selectedVariantId.value,
    selectedDecorId: selectedDecorId.value,
    selectedGlassId: selectedGlassId.value,
    selectedWallId: selectedWallId.value,
    selectedFloorId: selectedFloorId.value,
    selectedSurchargeIds: [...selectedSurchargeIds.value],
    activeStep: activeStep.value,
    currentItemName: currentItemName.value,
    currentItemQuantity: currentItemQuantity.value,
    previewItemUid: previewItemUid.value
  }
}

// Comparable form of a draft: the order of the surcharge ids and the surrounding whitespace of the name are not
// a choice of the visitor, so they must not make an untouched configuration look unfinished.
const draftStateSignature = (state: ConfiguratorDraftState): string => {
  return JSON.stringify({
    ...state,
    selectedSurchargeIds: [...state.selectedSurchargeIds].sort((left, right) => left - right),
    currentItemName: state.currentItemName.trim()
  })
}

// Only a configuration the visitor actually changed is worth keeping: the untouched one equals the clean start
// that saving or cancelling an edit produces anyway, so nothing has to be restored for it.
const captureUnfinishedDraft = (): ConfiguratorDraftState | null => {
  const state = captureDraftState()

  return draftStateSignature(state) === pristineDraftSignature ? null : state
}

// Putting a draft back is the same one-shot restore as reopening a saved item: the model watcher has to stay
// out of it, otherwise it would reset the variant, decor, glass and surcharges that are being restored.
const restoreDraftState = (state: ConfiguratorDraftState): void => {
  isRestoringItemSelection = true

  selectedModelId.value = state.selectedModelId
  selectedVariantId.value = state.selectedVariantId
  selectedDecorId.value = state.selectedDecorId
  selectedGlassId.value = state.selectedGlassId
  selectedWallId.value = state.selectedWallId
  selectedFloorId.value = state.selectedFloorId
  selectedSurchargeIds.value = [...state.selectedSurchargeIds]
  currentItemName.value = state.currentItemName
  currentItemQuantity.value = state.currentItemQuantity
  previewItemUid.value = state.previewItemUid
  activeStep.value = state.activeStep

  void nextTick(() => {
    isRestoringItemSelection = false
  })
}

// Putting a pending edit back into the request at its original position and untouched. It runs before another
// row is opened and when the edit is cancelled, so a row is never in the list twice.
const restoreEditedItem = (): void => {
  const editing = editingItem.value

  if (editing === null) {
    return
  }

  const items = [...addedItems.value]
  items.splice(Math.min(editing.index, items.length), 0, editing.item)
  addedItems.value = items
  editingItem.value = null
}

// „Upravit“ reopens the exact configuration of an item: its selection, its name and its quantity become the
// open configuration again. The row leaves the list while it is edited, so it can neither be sent nor counted
// twice; saving writes it back to its position and cancelling restores it untouched. The draft that was open
// before the edit is captured first, so the edit never costs it.
const editItem = (uid: string): void => {
  // A pending edit is put back first, so clicking around never loses a configuration. Its draft snapshot stays
  // untouched, because the open steps currently hold that edit's copy and not the draft of the visitor.
  const hasPendingEdit = editingItem.value !== null

  restoreEditedItem()

  const index = addedItems.value.findIndex((item) => item.uid === uid)

  if (index === -1) {
    return
  }

  const item = addedItems.value[index]

  if (!hasPendingEdit) {
    draftBeforeEdit.value = captureUnfinishedDraft()
  }

  addedItems.value = addedItems.value.filter((entry) => entry.uid !== uid)
  previewItemUid.value = null
  editingItem.value = { uid: item.uid, index, item }
  applyItemSelection(item)
}

// Saving or cancelling an edit returns the configurator to the draft that was open before „Upravit“; when the
// visitor had not changed anything, the steps simply start over as they did before.
const finishItemEdit = (): void => {
  const draft = draftBeforeEdit.value

  draftBeforeEdit.value = null
  editingItem.value = null

  if (draft === null) {
    startNewConfiguration()

    return
  }

  restoreDraftState(draft)
}

const cancelItemEdit = (): void => {
  if (editingItem.value === null) {
    return
  }

  restoreEditedItem()
  finishItemEdit()
}

const updateItemQuantity = (uid: string, quantity: number): void => {
  if (uid === CURRENT_ITEM_UID) {
    currentItemQuantity.value = clampQuantity(quantity)

    return
  }

  addedItems.value = addedItems.value.map((item) => (item.uid === uid ? { ...item, quantity: clampQuantity(quantity) } : item))
}

const removeItem = (uid: string): void => {
  addedItems.value = addedItems.value.filter((item) => item.uid !== uid)

  if (previewItemUid.value === uid) {
    previewItemUid.value = null
  }
}

// Clicking a card of the request shows that door in the room preview. The open configuration is the fallback
// of the preview, so clicking its own row (or the selected row again) only clears the selection.
const selectPreviewItem = (uid: string): void => {
  previewItemUid.value = previewItemUid.value === uid || uid === CURRENT_ITEM_UID ? null : uid
}

const submitInquiry = async (): Promise<void> => {
  if (!canSubmitInquiry.value || isPreparingPreview.value) {
    return
  }

  previewError.value = ''
  isPreparingPreview.value = true

  const payload: InquiryItemPayload[] = []

  try {
    // The illustration of every item is composed right before the request, so it always matches the stored
    // selection of that item.
    for (const item of requestItems.value) {
      const preview = await composeDoorPreviewPng({
        variant: item.variant,
        decorTextureUrl: item.decor?.texture_image_url ?? null,
        glassTextureUrl: item.glass?.texture_image_url ?? null,
        glassOpacity: glassOpacityFraction(item.glass)
      })

      payload.push({
        name: item.name,
        quantity: item.quantity,
        door_model_id: item.door_model_id,
        door_variant_id: item.door_variant_id,
        surcharge_ids: [...item.surcharge_ids],
        // An unselected option and a missing illustration are left out of the multipart body instead of being
        // sent as null, so the endpoint sees an absent (and therefore nullable) field.
        ...(item.decor_id !== null ? { decor_id: item.decor_id } : {}),
        ...(item.glass_type_id !== null ? { glass_type_id: item.glass_type_id } : {}),
        ...(preview !== null ? { door_preview: preview } : {})
      })
    }
  } catch {
    previewError.value = PREVIEW_ERROR_MESSAGE

    return
  } finally {
    isPreparingPreview.value = false
  }

  inquiryForm.transform((data) => ({
    ...data,
    items: payload
  })).post(route('front.configurator.inquiry.store'), {
    // Nested files are appended as `items[0][door_preview]`, which is exactly the shape the endpoint validates.
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      inquiryForm.reset('customer_name', 'customer_email', 'customer_phone', 'customer_message', 'gdpr_consent')
      inquiryForm.clearErrors()
      // A sent request is empty again, so the open configuration is the only row and nothing of the sent
      // items (an edit in progress, its stored draft or a selected preview) may survive.
      addedItems.value = []
      editingItem.value = null
      draftBeforeEdit.value = null
      previewItemUid.value = null
    },
    onFinish: () => {
      // A sent file must never be attached to the next inquiry in the same session.
      inquiryForm.items = []
    },
  })
}

// The fixed site header is not part of the document flow and its logo shrinks once the page is scrolled, so the
// height of the bar is measured again on every scroll and resize (throttled to one animation frame).
let headerMeasureFrame: number | null = null

const measureHeaderOffset = (): void => {
  // The mobile menu is a sibling of the visible bar inside the header, so measure only the bar.
  const headerBar = document.getElementById(SITE_HEADER_ID)?.querySelector('nav')
  const height = headerBar?.getBoundingClientRect().height ?? 0

  headerOffset.value = Math.round(height > 0 ? height : FALLBACK_HEADER_HEIGHT)
}

const scheduleHeaderMeasure = (): void => {
  if (headerMeasureFrame !== null) {
    return
  }

  headerMeasureFrame = requestAnimationFrame(() => {
    headerMeasureFrame = null
    measureHeaderOffset()
  })
}

onMounted(() => {
  measureHeaderOffset()
  window.addEventListener('scroll', scheduleHeaderMeasure, { passive: true })
  window.addEventListener('resize', scheduleHeaderMeasure)
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', scheduleHeaderMeasure)
  window.removeEventListener('resize', scheduleHeaderMeasure)

  if (headerMeasureFrame !== null) {
    cancelAnimationFrame(headerMeasureFrame)
    headerMeasureFrame = null
  }
})

watch(selectedModel, (model) => {
  if (isRestoringItemSelection) {
    return
  }

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

// The configuration the page started with, read after the watchers above resolved the initial glass. It tells an
// untouched draft (which needs no restoration) apart from one the visitor built.
const pristineDraftSignature = draftStateSignature(captureDraftState())

const selectedPreviewImageUrl = computed((): string | null => {
  return selectedVariant.value?.source_reference_url ?? selectedModel.value?.preview_image_url ?? null
})

// The item of the request that is drawn in the large preview. It falls back to the open configuration when no
// row is selected (or when the selected row disappeared, for example after submitting the inquiry).
const previewedItem = computed((): InquiryItemDraft | null => {
  if (previewItemUid.value === null) {
    return null
  }

  return requestItems.value.find((item) => item.uid === previewItemUid.value) ?? null
})

const isItemPreviewActive = computed((): boolean => previewedItem.value !== null)

const previewedModel = computed((): DoorModel | null => {
  const modelId = previewedItem.value?.door_model_id

  return modelId === undefined ? null : props.doorModels.find((model) => model.id === modelId) ?? null
})

// One set of values for the preview: the door of the selected item, or the door of the open configuration. The
// room (wall and floor) is shared, because the materials are chosen for the preview and are not part of an item.
const previewVariant = computed((): DoorVariant | null => previewedItem.value?.variant ?? selectedVariant.value)
const previewModelName = computed((): string | null => previewedItem.value?.model_name ?? selectedModel.value?.name ?? null)
const previewVariantLabel = computed((): string | null => {
  return previewedItem.value?.variant_label ?? (selectedVariant.value ? variantLabel(selectedVariant.value) : null)
})
const previewDecorImageUrl = computed((): string | null => {
  return previewedItem.value ? previewedItem.value.decor?.texture_image_url ?? null : selectedDecorImageUrl.value
})
const previewGlassImageUrl = computed((): string | null => {
  return previewedItem.value ? previewedItem.value.glass?.texture_image_url ?? null : glassLayerImageUrl.value
})
const previewGlassOpacity = computed((): number => {
  return previewedItem.value ? glassOpacityFraction(previewedItem.value.glass) : selectedGlassOpacity.value
})
// Mirrors the rule of the open configuration: every glass but the clear one is shown with a blurred interior.
const previewUseBlurredInterior = computed((): boolean => {
  const glass = previewedItem.value?.glass

  return glass === undefined ? useBlurredInterior.value : glass !== null && glass.code !== 'cire-sklo'
})
const previewCanRenderDecorLayer = computed((): boolean => {
  if (!previewedItem.value) {
    return canRenderDecorLayer.value
  }

  return Boolean(previewDecorImageUrl.value && previewVariant.value?.door_mask_url)
})
const previewFallbackImageUrl = computed((): string | null => {
  if (!previewedItem.value) {
    return selectedPreviewImageUrl.value
  }

  return previewVariant.value?.source_reference_url ?? previewedModel.value?.preview_image_url ?? null
})
// Every item draws its own masks, so a previewed item never borrows the ids of the open configuration.
const previewDoorMaskId = computed((): string => {
  if (previewedItem.value) {
    return `door-preview-${previewedItem.value.uid}`
  }

  return `${previewSvgIdBase.value}-door-mask`
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
          Orientační cena poptávky: <span class="text-accent">{{ formatPrice(requestPriceWithVat) }} s DPH</span>
          <span class="mt-1 block text-xs text-white/40">{{ formatPieceCount(requestTotalDoors) }} · {{ formatItemCount(requestItems.length) }}</span>
        </p>
      </header>
    </FullSection>

    <FullSection v-if="hasDoorModels">
      <div class="mt-8 grid gap-6 lg:grid-cols-12 lg:items-start">
        <!-- Both columns are sticky below the fixed site header, so they share the measured offset. -->
        <section
          class="border border-white/15 bg-white/5 p-5 lg:sticky lg:col-span-5 lg:p-6"
          :style="{ top: `${stickyTop}px` }"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-accent text-xs uppercase tracking-[0.25em]">Náhled</p>
              <h2 class="mt-2 text-3xl text-white">{{ previewModelName ?? 'Vyberte model' }}</h2>
            </div>
            <p class="text-right text-xs tracking-[0.18em] text-white/50">{{ previewVariantLabel ?? '—' }}</p>
          </div>

          <!-- A card of the request is selected: the preview shows its door in the room, not the open draft. -->
          <div
            v-if="isItemPreviewActive"
            class="mt-4 flex flex-wrap items-center justify-between gap-3 border border-accent/30 bg-accent/10 px-3 py-2"
          >
            <p class="text-accent text-[10px] uppercase tracking-[0.18em]">Položka: {{ previewedItem?.name }}</p>
            <button
              type="button"
              class="text-white/60 text-[10px] uppercase tracking-[0.18em] transition hover:text-white"
              @click="previewItemUid = null"
            >
              Zpět na konfiguraci
            </button>
          </div>

          <DoorPreview
            :model-name="previewModelName"
            :can-render-decor-layer="previewCanRenderDecorLayer"
            :selected-preview-image-url="previewFallbackImageUrl"
            :selected-decor-image-url="previewDecorImageUrl"
            :selected-variant="previewVariant"
            :preview-scene="previewScene"
            :selected-wall="selectedWall"
            :selected-floor="selectedFloor"
            :door-mask-id="previewDoorMaskId"
            :glass-layer-image-url="previewGlassImageUrl"
            :selected-glass-opacity="previewGlassOpacity"
            :use-blurred-interior="previewUseBlurredInterior"
          />

        </section>

        <section class="border border-white/15 bg-white/5 lg:col-span-7">
          <!--
            The menu of the steps stays right below the fixed site header, so the visitor can jump between the
            steps while the long content of the current one scrolls underneath it. It sticks inside this column,
            which spans the whole height of the configurator, so no shorter ancestor clips it. On narrow screens
            the buttons keep a single scrollable row instead of stacking three rows over the content.
          -->
          <div
            class="sticky z-20 border-b border-white/10 bg-dark/95 px-5 py-3 backdrop-blur lg:px-6"
            :style="{ top: `${stickyTop}px` }"
          >
            <div class="flex snap-x snap-mandatory gap-2 overflow-x-auto scrollbar-hide lg:grid lg:grid-cols-6 lg:overflow-visible">
              <button
                v-for="step in steps"
                :key="step.id"
                type="button"
                class="min-w-[7.5rem] shrink-0 snap-start border px-3 py-3 text-left transition lg:min-w-0"
                :class="activeStep === step.id ? 'border-accent bg-accent/10 text-white' : 'border-white/10 text-white/50 hover:border-white/35 hover:text-white'"
                @click="goToStep(step.id)"
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
              <p class="text-right text-sm text-white/55">{{ formatPrice(requestPriceWithVat) }} s DPH</p>
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
            <div v-else-if="activeStep === inquiryStepId" class="grid gap-5">
              <div class="grid gap-5 lg:grid-cols-2">
                <ConfiguratorSummaryCard
                  :selected-model-name="selectedModel?.name ?? null"
                  :selected-variant-label="selectedVariant ? variantLabel(selectedVariant) : null"
                  :selected-decor-name="selectedDecor?.name ?? null"
                  :selected-glass-name="selectedGlass?.name ?? null"
                  :selected-surcharge-names="selectedSurcharges.map((surcharge) => surcharge.name)"
                />
                <ConfiguratorRequestItemCard
                  :name="currentItemName"
                  :quantity="currentItemQuantity"
                  :max-quantity="maxQuantityPerItem"
                  :max-items="maxItems"
                  :can-add="currentItemDraft !== null"
                  :can-commit="canCommitCurrentItem"
                  :is-editing="editingItem !== null"
                  @update:name="currentItemName = $event"
                  @update:quantity="currentItemQuantity = $event"
                  @commit="commitCurrentItem"
                  @cancel-edit="cancelItemEdit"
                />
              </div>

              <ConfiguratorRequestItems
                :items="requestItems"
                :current-uid="CURRENT_ITEM_UID"
                :editing-uid="editingItem?.uid ?? null"
                :selected-uid="previewItemUid"
                :vat-rate="vatRate"
                :max-quantity="maxQuantityPerItem"
                @update:quantity="updateItemQuantity"
                @remove="removeItem"
                @edit="editItem"
                @select="selectPreviewItem"
              />

              <div class="grid gap-5 lg:grid-cols-2">
                <ConfiguratorRequestRecap :items="requestItems" :vat-rate="vatRate" />
                <ConfiguratorInquiryForm
                  :form="inquiryForm"
                  :flash-success="flashSuccess"
                  :can-submit-inquiry="canSubmitInquiry"
                  :is-preparing-preview="isPreparingPreview"
                  :preview-error="previewError"
                  @submit="submitInquiry"
                />
              </div>
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
