import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/offers'

export const getOffers = (filters = {}) => {
  const params = {}
  if (filters.caseId) {
    params.caseId = filters.caseId
  }
  if (filters.customerId) {
    params.customerId = filters.customerId
  }
  return axios.get(generateUrl(base), { params }).then((r) => r.data)
}

export const getOffer = (id) =>
  axios.get(generateUrl(`${base}/${id}`)).then((r) => r.data)

export const createOffer = (payload) =>
  axios.post(generateUrl(base), payload).then((r) => r.data)

export const updateOffer = (id, payload) =>
  axios.put(generateUrl(`${base}/${id}`), payload).then((r) => r.data)

export const deleteOffer = (id) =>
  axios.delete(generateUrl(`${base}/${id}`)).then((r) => r.data)

export const getOfferPdfUrl = (id) =>
  generateUrl(`${base}/${id}/pdf`)

export const sendOfferEmail = (id, payload) =>
  axios.post(generateUrl(`${base}/${id}/send-email`), payload).then((r) => r.data)
