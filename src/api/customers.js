import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/customers'

export const getCustomers = () => axios.get(generateUrl(base)).then((r) => r.data)

export const createCustomer = (payload) =>
  axios.post(generateUrl(base), payload).then((r) => r.data)

export const updateCustomer = (id, payload) =>
  axios.put(generateUrl(`${base}/${id}`), payload).then((r) => r.data)

export const deleteCustomer = (id) =>
  axios.delete(generateUrl(`${base}/${id}`)).then((r) => r.data)
