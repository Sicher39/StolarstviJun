export type ReferenceCategoryKey = 'dvere' | 'nabytok' | 'kuchyne' | 'ostatni'

// Icon file names available in public/img/icons.
export type ReferenceCategoryIcon = 'door' | 'furniture' | 'kitchen' | 'other'

export type ReferenceImage = {
    url: string
    alt?: string
}

export type ReferenceCard = {
    id: number
    header: string
    note: string
    images: ReferenceImage[]
}

// Labels and icons are the only reference data kept in the frontend - the cards themselves always
// come from the server, grouped by the keys below.
export type ReferenceCategoryMeta = {
    key: ReferenceCategoryKey
    icon: ReferenceCategoryIcon
    text: string
}

export type ReferenceCategory = ReferenceCategoryMeta & {
    cards: ReferenceCard[]
}

export const referenceCategoryMeta: ReferenceCategoryMeta[] = [
    {
        key: 'dvere',
        icon: 'door',
        text: 'dveře'
    },
    {
        key: 'nabytok',
        icon: 'furniture',
        text: 'nábytek'
    },
    {
        key: 'kuchyne',
        icon: 'kitchen',
        text: 'kuchyně'
    },
    {
        key: 'ostatni',
        icon: 'other',
        text: 'ostatní'
    }
]

/**
 * Merges the server supplied cards into the fixed category metadata, so every category keeps the same
 * position and icon even when it currently has no published references.
 */
export const buildReferenceCategories = (
    cardsByCategory: Partial<Record<ReferenceCategoryKey, ReferenceCard[]>> | null | undefined
): ReferenceCategory[] => {
    return referenceCategoryMeta.map((meta) => ({
        ...meta,
        cards: cardsByCategory?.[meta.key] ?? []
    }))
}
