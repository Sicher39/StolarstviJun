export type NavLink = {
  title: string
  link: string
  route: string
}

export const navLinks: NavLink[] = [
  {
    title: 'Úvod',
    link: '/',
    route: 'front.index'
  },
  {
    title: 'Dveře',
    link: '/dvere',
    route: 'front.doors'
  },
  {
    title: 'Zakázková výroba',
    link: '/zakazkova-vyroba',
    route: 'front.customProduction'
  },
  {
    title: 'Reference',
    link: '/reference',
    route: 'front.references'
  },
  {
    title: 'Katalog',
    link: '/katalog',
    route: 'front.catalog'
  },
  {
    title: 'Kontakt',
    link: '/kontakt',
    route: 'front.contact'
  }
]
