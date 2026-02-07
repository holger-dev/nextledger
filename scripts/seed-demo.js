/* eslint-disable no-console */
const axios = require('axios')

const BASE_URL = process.env.NEXTLEDGER_BASE_URL || 'http://localhost:8080/apps/nextledger/api'
const USERNAME = process.env.NEXTCLOUD_USER
const PASSWORD = process.env.NEXTCLOUD_APP_PASSWORD
const TAX_RATE_BP = Number(process.env.NEXTLEDGER_TAX_RATE_BP || 1900)

if (!USERNAME || !PASSWORD) {
  console.error('Missing credentials. Set NEXTCLOUD_USER and NEXTCLOUD_APP_PASSWORD.')
  process.exit(1)
}

const client = axios.create({
  baseURL: BASE_URL,
  auth: {
    username: USERNAME,
    password: PASSWORD,
  },
  headers: {
    'OCS-APIREQUEST': 'true',
  },
})

const toUnix = (date) => Math.floor(date.getTime() / 1000)

const addDays = (base, days) => {
  const date = new Date(base)
  date.setDate(date.getDate() + days)
  return date
}

const ensureActiveFiscalYear = async () => {
  const years = await client.get('/fiscal-years').then((r) => r.data)
  const active = (Array.isArray(years) ? years : []).find((year) => !!year.isActive)
  if (active) {
    return active
  }

  const now = new Date()
  const year = now.getFullYear()
  const start = new Date(year, 0, 1)
  const end = new Date(year, 11, 31, 23, 59, 59)
  const payload = {
    name: `Wirtschaftsjahr ${year}`,
    dateStart: toUnix(start),
    dateEnd: toUnix(end),
    isActive: true,
  }
  return client.post('/fiscal-years', payload).then((r) => r.data)
}

const calcTotals = (items, taxRateBp) => {
  const subtotal = items.reduce((sum, item) => sum + item.quantity * item.unitPriceCents, 0)
  const tax = Math.round((subtotal * taxRateBp) / 10000)
  return {
    subtotalCents: subtotal,
    taxCents: tax,
    totalCents: subtotal + tax,
  }
}

const itemSets = [
  [
    { name: 'Website-Konzept', description: 'Zielgruppen, Struktur, Inhalte', quantity: 1, unitPriceCents: 120000 },
    { name: 'UI/UX Design', description: 'Figma Designsystem & Prototyp', quantity: 2, unitPriceCents: 85000 },
  ],
  [
    { name: 'Frontend-Entwicklung', description: 'Vue Komponenten & Layout', quantity: 3, unitPriceCents: 95000 },
    { name: 'Backend-Integration', description: 'API & Auth Anbindung', quantity: 2, unitPriceCents: 110000 },
  ],
  [
    { name: 'App-MVP', description: 'iOS + Android', quantity: 1, unitPriceCents: 280000 },
    { name: 'Test & QA', description: 'Geräte-Testing', quantity: 2, unitPriceCents: 65000 },
  ],
  [
    { name: 'Performance-Optimierung', description: 'Core Web Vitals', quantity: 1, unitPriceCents: 90000 },
    { name: 'Analytics Setup', description: 'Tracking & Events', quantity: 1, unitPriceCents: 45000 },
  ],
]

const customers = [
  {
    company: 'ByteWerk IT GmbH',
    contactName: 'Julia Neumann',
    street: 'Kantstraße',
    houseNumber: '12',
    zip: '10623',
    city: 'Berlin',
    email: 'julia.neumann@bytewerk.de',
    caseName: 'Relaunch Webplattform',
  },
  {
    company: 'CloudNexus Studio',
    contactName: 'Jonas Weber',
    street: 'Bahnhofstraße',
    houseNumber: '8',
    zip: '80335',
    city: 'München',
    email: 'jonas.weber@cloudnexus.io',
    caseName: 'App-Entwicklung MVP',
  },
  {
    company: 'PixelForge Labs',
    contactName: 'Mira Hoffmann',
    street: 'Rheinufer',
    houseNumber: '45',
    zip: '50668',
    city: 'Köln',
    email: 'mira@pixelforge.dev',
    caseName: 'Kundenportal',
  },
  {
    company: 'NextWave Solutions',
    contactName: 'Tim Berger',
    street: 'Marktstraße',
    houseNumber: '21',
    zip: '30159',
    city: 'Hannover',
    email: 'tim.berger@nextwave.app',
    caseName: 'SaaS Onboarding',
  },
  {
    company: 'AppWorx Collective',
    contactName: 'Lea König',
    street: 'Altmarkt',
    houseNumber: '3',
    zip: '01067',
    city: 'Dresden',
    email: 'lea.koenig@appworx.io',
    caseName: 'Mobile Redesign',
  },
]

const createCustomer = (payload) => client.post('/customers', payload).then((r) => r.data)
const createCase = (payload) => client.post('/cases', payload).then((r) => r.data)
const createOffer = (payload) => client.post('/offers', payload).then((r) => r.data)
const createOfferItem = (offerId, payload) =>
  client.post(`/offers/${offerId}/items`, payload).then((r) => r.data)
const createInvoice = (payload) => client.post('/invoices', payload).then((r) => r.data)
const createInvoiceItem = (invoiceId, payload) =>
  client.post(`/invoices/${invoiceId}/items`, payload).then((r) => r.data)

const createOfferWithItems = async ({ caseId, customerId, issueDate, validUntil, items, status }) => {
  const totals = calcTotals(items, TAX_RATE_BP)
  const offer = await createOffer({
    caseId,
    customerId,
    issueDate,
    validUntil,
    status,
    taxRateBp: TAX_RATE_BP,
    isSmallBusiness: false,
    ...totals,
  })
  await Promise.all(
    items.map((item) =>
      createOfferItem(offer.id, {
        positionType: 'custom',
        name: item.name,
        description: item.description,
        quantity: item.quantity,
        unitPriceCents: item.unitPriceCents,
        totalCents: item.quantity * item.unitPriceCents,
      })
    )
  )
  return offer
}

const createInvoiceWithItems = async ({ caseId, customerId, issueDate, dueDate, items, status }) => {
  const totals = calcTotals(items, TAX_RATE_BP)
  const invoice = await createInvoice({
    caseId,
    customerId,
    issueDate,
    dueDate,
    status,
    taxRateBp: TAX_RATE_BP,
    isSmallBusiness: false,
    ...totals,
  })
  await Promise.all(
    items.map((item) =>
      createInvoiceItem(invoice.id, {
        positionType: 'custom',
        name: item.name,
        description: item.description,
        quantity: item.quantity,
        unitPriceCents: item.unitPriceCents,
        totalCents: item.quantity * item.unitPriceCents,
      })
    )
  )
  return invoice
}

const main = async () => {
  console.log(`Seeding demo data via ${BASE_URL}`)
  await ensureActiveFiscalYear()

  const now = new Date()
  const issueBase = addDays(now, -30)

  for (let i = 0; i < customers.length; i += 1) {
    const customerInput = customers[i]
    const customer = await createCustomer(customerInput)
    const caseItem = await createCase({
      customerId: customer.id,
      name: customerInput.caseName,
      description: 'IT-Projekt (Webentwicklung & App-Entwicklung)',
    })

    const offerDates = [addDays(issueBase, i * 2), addDays(issueBase, i * 2 + 3)]
    const invoiceDates = [addDays(issueBase, i * 2 + 8), addDays(issueBase, i * 2 + 12)]

    await createOfferWithItems({
      caseId: caseItem.id,
      customerId: customer.id,
      issueDate: toUnix(offerDates[0]),
      validUntil: toUnix(addDays(offerDates[0], 21)),
      items: itemSets[i % itemSets.length],
      status: 'sent',
    })

    await createOfferWithItems({
      caseId: caseItem.id,
      customerId: customer.id,
      issueDate: toUnix(offerDates[1]),
      validUntil: toUnix(addDays(offerDates[1], 21)),
      items: itemSets[(i + 1) % itemSets.length],
      status: 'draft',
    })

    await createInvoiceWithItems({
      caseId: caseItem.id,
      customerId: customer.id,
      issueDate: toUnix(invoiceDates[0]),
      dueDate: toUnix(addDays(invoiceDates[0], 14)),
      items: itemSets[(i + 2) % itemSets.length],
      status: 'open',
    })

    await createInvoiceWithItems({
      caseId: caseItem.id,
      customerId: customer.id,
      issueDate: toUnix(invoiceDates[1]),
      dueDate: toUnix(addDays(invoiceDates[1], 14)),
      items: itemSets[(i + 3) % itemSets.length],
      status: 'open',
    })

    console.log(`Created demo data for ${customer.company}`)
  }

  console.log('Demo seed completed.')
}

main().catch((error) => {
  const message = error?.response?.data?.message || error.message
  console.error('Demo seed failed:', message)
  process.exit(1)
})
