import de from './locales/de'
import en from './locales/en'

const locales = { de, en }

export function getLocale() {
  const raw =
    (window.OC && typeof window.OC.getLanguage === 'function' && window.OC.getLanguage()) ||
    navigator.language ||
    'de'
  return String(raw).toLowerCase().startsWith('en') ? 'en' : 'de'
}

function getLocaleData() {
  return locales[getLocale()] || locales.de
}

function getByPath(obj, path) {
  const keys = String(path).split('.')
  let current = obj
  for (const key of keys) {
    if (!current || typeof current !== 'object' || !(key in current)) {
      return undefined
    }
    current = current[key]
  }
  return current
}

export function tKey(path, fallback = '') {
  const data = getLocaleData()
  const fromCurrent = getByPath(data.ui, path)
  if (fromCurrent !== undefined) {
    return String(fromCurrent)
  }

  const fromDefault = getByPath(locales.de.ui, path)
  if (fromDefault !== undefined) {
    return String(fromDefault)
  }
  return fallback || path
}
