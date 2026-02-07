import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/invoices'

export const getInvoices = (filters = {}) => {
  const params = {}
  if (filters.caseId) {
    params.caseId = filters.caseId
  }
  if (filters.customerId) {
    params.customerId = filters.customerId
  }
  return axios.get(generateUrl(base), { params }).then((r) => r.data)
}

export const getInvoice = (id) =>
  axios.get(generateUrl(`${base}/${id}`)).then((r) => r.data)

export const createInvoice = (payload) =>
  axios.post(generateUrl(base), payload).then((r) => r.data)

export const updateInvoice = (id, payload) =>
  axios.put(generateUrl(`${base}/${id}`), payload).then((r) => r.data)

export const deleteInvoice = (id) =>
  axios.delete(generateUrl(`${base}/${id}`)).then((r) => r.data)

export const getInvoicePdfUrl = (id) =>
  generateUrl(`${base}/${id}/pdf`)
