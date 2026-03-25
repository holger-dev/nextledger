const STORAGE_KEY = 'nextledger-active-currency'
const DEFAULT_CURRENCY = 'EUR'

export const normalizeCurrencyCode = (value) => {
  const normalized = String(value || '').trim().toUpperCase()
  return normalized || DEFAULT_CURRENCY
}

export const setActiveCurrencyCode = (value) => {
  const code = normalizeCurrencyCode(value)
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(STORAGE_KEY, code)
  }
  return code
}

export const getActiveCurrencyCode = () => {
  if (typeof window === 'undefined') {
    return DEFAULT_CURRENCY
  }
  return normalizeCurrencyCode(window.localStorage.getItem(STORAGE_KEY))
}

export const formatCurrencyCents = (value, currencyCode = getActiveCurrencyCode()) => {
  if (value === null || value === undefined || value === '') {
    return '–'
  }
  const amount = Number(value)
  if (Number.isNaN(amount)) {
    return String(value)
  }
  try {
    return new Intl.NumberFormat('de-DE', {
      style: 'currency',
      currency: normalizeCurrencyCode(currencyCode),
      currencyDisplay: 'narrowSymbol',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount / 100)
  } catch (e) {
    return `${(amount / 100).toLocaleString('de-DE', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} ${normalizeCurrencyCode(currencyCode)}`
  }
}
