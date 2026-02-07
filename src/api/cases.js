import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/cases'

export const getCases = (customerId = null) => {
  const params = {}
  if (customerId !== null && customerId !== undefined && customerId !== '') {
    params.customerId = customerId
  }
  return axios.get(generateUrl(base), { params }).then((r) => r.data)
}

export const createCase = (payload) =>
  axios.post(generateUrl(base), payload).then((r) => r.data)

export const updateCase = (id, payload) =>
  axios.put(generateUrl(`${base}/${id}`), payload).then((r) => r.data)

export const deleteCase = (id) =>
  axios.delete(generateUrl(`${base}/${id}`)).then((r) => r.data)
