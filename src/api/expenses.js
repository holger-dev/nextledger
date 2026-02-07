import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/fiscal-years'
const itemBase = '/apps/nextledger/api/expenses'

export const getExpenses = (fiscalYearId) =>
  axios.get(generateUrl(`${base}/${fiscalYearId}/expenses`)).then((r) => r.data)

export const createExpense = (fiscalYearId, payload) =>
  axios.post(generateUrl(`${base}/${fiscalYearId}/expenses`), payload).then((r) => r.data)

export const updateExpense = (id, payload) =>
  axios.put(generateUrl(`${itemBase}/${id}`), payload).then((r) => r.data)

export const deleteExpense = (id) =>
  axios.delete(generateUrl(`${itemBase}/${id}`)).then((r) => r.data)
