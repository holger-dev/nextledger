import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = '/apps/nextledger/api/fiscal-years'

export const getIncomes = (fiscalYearId) =>
  axios.get(generateUrl(`${base}/${fiscalYearId}/incomes`)).then((r) => r.data)
