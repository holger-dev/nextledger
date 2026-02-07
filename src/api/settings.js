import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/settings'

const get = (path) => axios.get(generateUrl(`${base}/${path}`)).then((r) => r.data)
const put = (path, payload) => axios.put(generateUrl(`${base}/${path}`), payload).then((r) => r.data)

export const getCompany = () => get('company')
export const saveCompany = (payload) => put('company', payload)

export const getTexts = () => get('texts')
export const saveTexts = (payload) => put('texts', payload)

export const getTax = () => get('tax')
export const saveTax = (payload) => put('tax', payload)

export const getMisc = () => get('misc')
export const saveMisc = (payload) => put('misc', payload)
