import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/invoices'
const itemBase = '/apps/nextledger/api/invoice-items'

export const getInvoiceItems = (invoiceId) =>
  axios.get(generateUrl(`${base}/${invoiceId}/items`)).then((r) => r.data)

export const createInvoiceItem = (invoiceId, payload) =>
  axios.post(generateUrl(`${base}/${invoiceId}/items`), payload).then((r) => r.data)

export const updateInvoiceItem = (id, payload) =>
  axios.put(generateUrl(`${itemBase}/${id}`), payload).then((r) => r.data)

export const deleteInvoiceItem = (id) =>
  axios.delete(generateUrl(`${itemBase}/${id}`)).then((r) => r.data)
