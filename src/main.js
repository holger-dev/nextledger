import Vue from 'vue'
import App from './App.vue'
import router from './router'
import '@nextcloud/dialogs/style.css'
import { tKey } from './i18n'
import { formatCurrencyCents, getActiveCurrencyCode, setActiveCurrencyCode } from './utils/currency'

Vue.config.productionTip = false
Vue.prototype.$tKey = tKey
Vue.prototype.$formatCurrencyCents = formatCurrencyCents
Vue.prototype.$getActiveCurrencyCode = getActiveCurrencyCode
setActiveCurrencyCode(getActiveCurrencyCode())

new Vue({
  router,
  render: (h) => h(App),
}).$mount('#nextledger-app')
