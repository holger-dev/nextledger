import Vue from 'vue'
import App from './App.vue'
import router from './router'
import '@nextcloud/dialogs/style.css'

Vue.config.productionTip = false

new Vue({
  router,
  render: (h) => h(App),
}).$mount('#nextledger-app')
