import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/offers'

export const getOfferItems = (offerId) =>
  axios.get(generateUrl(`${base}/${offerId}/items`)).then((r) => r.data)

export const createOfferItem = (offerId, payload) =>
  axios.post(generateUrl(`${base}/${offerId}/items`), payload).then((r) => r.data)

export const updateOfferItem = (id, payload) =>
  axios.put(generateUrl(`/apps/nextledger/api/offer-items/${id}`), payload).then((r) => r.data)

export const deleteOfferItem = (id) =>
  axios.delete(generateUrl(`/apps/nextledger/api/offer-items/${id}`)).then((r) => r.data)
