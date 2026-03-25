import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/fiscal-years'

export const getFiscalYears = () => axios.get(generateUrl(base)).then((r) => r.data)

export const createFiscalYear = (payload) =>
  axios.post(generateUrl(base), payload).then((r) => r.data)

export const updateFiscalYear = (id, payload) =>
  axios.put(generateUrl(`${base}/${id}`), payload).then((r) => r.data)

export const deleteFiscalYear = (id) =>
  axios.delete(generateUrl(`${base}/${id}`)).then((r) => r.data)

export const getGubPdfUrl = (id, includeDetails = true) => {
  const url = generateUrl(`${base}/${id}/gub/pdf`)
  const query = new URLSearchParams({
    includeDetails: includeDetails ? '1' : '0',
  })

  return `${url}?${query.toString()}`
}
