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

export type ReferenceCategory = {
    key: ReferenceCategoryKey
    icon: ReferenceCategoryIcon
    text: string
    cards: ReferenceCard[]
}

// Card content is placeholder material assembled from the photos that already ship with the site,
// the labels follow the reference icon bar (dveře / nábytek / kuchyně / ostatní).
const referencePlace = 'Babice nad Svitavou'

export const doorReferenceCards: ReferenceCard[] = [
    {
        id: 1,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/Doors01.webp', alt: 'Dveře – reference 1' },
            { url: '/img/bg/small/Doors02.webp', alt: 'Dveře – reference 2' },
            { url: '/img/bg/small/Doors03.webp', alt: 'Dveře – reference 3' }
        ]
    },
    {
        id: 2,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/headers/red-door.webp', alt: 'Dveře – reference 4' },
            { url: '/img/bg/headers/office-door.webp', alt: 'Dveře – reference 5' }
        ]
    },
    {
        id: 3,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/Doors02.webp', alt: 'Dveře – reference 6' },
            { url: '/img/bg/small/Doors03.webp', alt: 'Dveře – reference 7' }
        ]
    },
    {
        id: 4,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/headers/office-door.webp', alt: 'Dveře – reference 8' },
            { url: '/img/bg/headers/red-door.webp', alt: 'Dveře – reference 9' }
        ]
    },
    {
        id: 5,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/Doors03.webp', alt: 'Dveře – reference 10' },
            { url: '/img/bg/small/Doors01.webp', alt: 'Dveře – reference 11' }
        ]
    },
    {
        id: 6,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/Doors01.webp', alt: 'Dveře – reference 12' },
            { url: '/img/bg/small/Doors02.webp', alt: 'Dveře – reference 13' }
        ]
    },
    {
        id: 7,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/headers/red-door.webp', alt: 'Dveře – reference 14' },
            { url: '/img/bg/small/Doors03.webp', alt: 'Dveře – reference 15' }
        ]
    },
    {
        id: 8,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/headers/office-door.webp', alt: 'Dveře – reference 16' },
            { url: '/img/bg/small/Doors01.webp', alt: 'Dveře – reference 17' }
        ]
    },
    {
        id: 9,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/Doors02.webp', alt: 'Dveře – reference 18' },
            { url: '/img/bg/headers/red-door.webp', alt: 'Dveře – reference 19' }
        ]
    },
    {
        id: 10,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/Doors03.webp', alt: 'Dveře – reference 20' },
            { url: '/img/bg/headers/office-door.webp', alt: 'Dveře – reference 21' }
        ]
    },
    {
        id: 11,
        header: 'dveře',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/Doors01.webp', alt: 'Dveře – reference 22' },
            { url: '/img/bg/small/Doors02.webp', alt: 'Dveře – reference 23' }
        ]
    }
]

export const furnitureReferenceCards: ReferenceCard[] = [
    {
        id: 12,
        header: 'nábytek',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/custom-furniture.webp', alt: 'Nábytek – reference 1' }
        ]
    },
    {
        id: 13,
        header: 'nábytek',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/custom-furniture.webp', alt: 'Nábytek – reference 2' },
            { url: '/img/bg/headers/produce.webp', alt: 'Nábytek – reference 3' }
        ]
    },
    {
        id: 14,
        header: 'nábytek',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/custom-furniture.webp', alt: 'Nábytek – reference 4' },
            { url: '/img/bg/small/Doors03.webp', alt: 'Nábytek – reference 5' }
        ]
    }
]

export const kitchenReferenceCards: ReferenceCard[] = [
    {
        id: 15,
        header: 'kuchyně',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/custom-kitchen.webp', alt: 'Kuchyně – reference 1' }
        ]
    },
    {
        id: 16,
        header: 'kuchyně',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/custom-kitchen.webp', alt: 'Kuchyně – reference 2' },
            { url: '/img/bg/headers/produce.webp', alt: 'Kuchyně – reference 3' }
        ]
    },
    {
        id: 17,
        header: 'kuchyně',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/custom-kitchen.webp', alt: 'Kuchyně – reference 4' },
            { url: '/img/bg/small/Doors01.webp', alt: 'Kuchyně – reference 5' }
        ]
    }
]

export const otherReferenceCards: ReferenceCard[] = [
    {
        id: 18,
        header: 'ostatní',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/custom-staircase.webp', alt: 'Ostatní – reference 1' }
        ]
    },
    {
        id: 19,
        header: 'ostatní',
        note: referencePlace,
        images: [
            { url: '/img/bg/headers/produce.webp', alt: 'Ostatní – reference 2' },
            { url: '/img/bg/small/custom-staircase.webp', alt: 'Ostatní – reference 3' }
        ]
    },
    {
        id: 20,
        header: 'ostatní',
        note: referencePlace,
        images: [
            { url: '/img/bg/small/configurator-doors.webp', alt: 'Ostatní – reference 4' },
            { url: '/img/bg/small/custom-staircase.webp', alt: 'Ostatní – reference 5' }
        ]
    }
]

export const referenceCategories: ReferenceCategory[] = [
    {
        key: 'dvere',
        icon: 'door',
        text: 'dveře',
        cards: doorReferenceCards
    },
    {
        key: 'nabytok',
        icon: 'furniture',
        text: 'nábytek',
        cards: furnitureReferenceCards
    },
    {
        key: 'kuchyne',
        icon: 'kitchen',
        text: 'kuchyně',
        cards: kitchenReferenceCards
    },
    {
        key: 'ostatni',
        icon: 'other',
        text: 'ostatní',
        cards: otherReferenceCards
    }
]
