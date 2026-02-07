import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/products'

export const getProducts = () => axios.get(generateUrl(base)).then((r) => r.data)

export const createProduct = (payload) =>
  axios.post(generateUrl(base), payload).then((r) => r.data)

export const updateProduct = (id, payload) =>
  axios.put(generateUrl(`${base}/${id}`), payload).then((r) => r.data)

export const deleteProduct = (id) =>
  axios.delete(generateUrl(`${base}/${id}`)).then((r) => r.data)
