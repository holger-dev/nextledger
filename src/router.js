import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'

import Cases from './views/Cases.vue'
import Customers from './views/Customers.vue'
import Invoices from './views/Invoices.vue'
import InvoiceCreate from './views/InvoiceCreate.vue'
import InvoiceEdit from './views/InvoiceEdit.vue'
import Offers from './views/Offers.vue'
import OfferCreate from './views/OfferCreate.vue'
import OfferEdit from './views/OfferEdit.vue'
import Products from './views/Products.vue'
import FiscalYear from './views/FiscalYear.vue'
import CaseDetail from './views/CaseDetail.vue'
import SettingsCompany from './views/SettingsCompany.vue'
import SettingsTexts from './views/SettingsTexts.vue'
import SettingsTax from './views/SettingsTax.vue'
import SettingsMisc from './views/SettingsMisc.vue'
import SettingsEmailBehavior from './views/SettingsEmailBehavior.vue'
import SettingsHelp from './views/SettingsHelp.vue'

Vue.use(Router)

export default new Router({
  mode: 'history',
  base: generateUrl('/apps/nextledger'),
  routes: [
    { path: '/', redirect: { name: 'cases' } },
    { path: '/cases', name: 'cases', component: Cases },
    { path: '/cases/:id', name: 'case-detail', component: CaseDetail },
    { path: '/customers', name: 'customers', component: Customers },
    { path: '/invoices', name: 'invoices', component: Invoices },
    { path: '/invoices/new', name: 'invoices-new', component: InvoiceCreate },
    { path: '/invoices/:id/edit', name: 'invoices-edit', component: InvoiceEdit },
    { path: '/offers', name: 'offers', component: Offers },
    { path: '/offers/new', name: 'offers-new', component: OfferCreate },
    { path: '/offers/:id/edit', name: 'offers-edit', component: OfferEdit },
    { path: '/products', name: 'products', component: Products },
    { path: '/fiscal-year', name: 'fiscal-year', component: FiscalYear },
    { path: '/settings/company', name: 'settings-company', component: SettingsCompany },
    { path: '/settings/texts', name: 'settings-texts', component: SettingsTexts },
    { path: '/settings/tax', name: 'settings-tax', component: SettingsTax },
    { path: '/settings/misc', name: 'settings-misc', component: SettingsMisc },
    { path: '/settings/email', name: 'settings-email', component: SettingsEmailBehavior },
    { path: '/settings/help', name: 'settings-help', component: SettingsHelp },
    { path: '*', redirect: '/' },
  ],
})
