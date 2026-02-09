import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/fiscal-years'

export const getIncomes = (fiscalYearId) =>
  axios.get(generateUrl(`${base}/${fiscalYearId}/incomes`)).then((r) => r.data)

export const createIncome = (fiscalYearId, payload) =>
  axios.post(generateUrl(`${base}/${fiscalYearId}/incomes`), payload).then((r) => r.data)

export const updateIncome = (id, payload) =>
  axios.put(generateUrl(`/apps/nextledger/api/incomes/${id}`), payload).then((r) => r.data)

export const deleteIncome = (id) =>
  axios.delete(generateUrl(`/apps/nextledger/api/incomes/${id}`)).then((r) => r.data)
