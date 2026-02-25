import Vue from 'vue'
import App from './App.vue'
import router from './router'
import '@nextcloud/dialogs/style.css'
import { tKey } from './i18n'

Vue.config.productionTip = false
Vue.prototype.$tKey = tKey

new Vue({
  router,
  render: (h) => h(App),
}).$mount('#nextledger-app')
