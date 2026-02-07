import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api'

export const getCaseElements = (caseId) =>
  axios.get(generateUrl(`${base}/cases/${caseId}/elements`)).then((r) => r.data)

export const createCaseElement = (caseId, payload) =>
  axios
    .post(generateUrl(`${base}/cases/${caseId}/elements`), payload)
    .then((r) => r.data)

export const updateCaseElement = (id, payload) =>
  axios.put(generateUrl(`${base}/case-elements/${id}`), payload).then((r) => r.data)

export const deleteCaseElement = (id) =>
  axios
    .delete(generateUrl(`${base}/case-elements/${id}`))
    .then((r) => r.data)
