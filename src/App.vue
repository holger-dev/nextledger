<template>
  <NcContent app-name="nextledger">
    <NcAppNavigation>
      <NcAppNavigationItem :to="{ name: 'cases' }" :name="casesNavName" />
      <NcAppNavigationItem :to="{ name: 'invoices' }" name="Rechnungen" />
      <NcAppNavigationItem :to="{ name: 'offers' }" name="Angebote" />
      <NcAppNavigationSpacer />
      <NcAppNavigationItem :to="{ name: 'products' }" name="Produkte/DL" />
      <NcAppNavigationItem :to="{ name: 'customers' }" name="Kunden" />
      <NcAppNavigationItem :to="{ name: 'fiscal-year' }" name="Wirtschaftsjahr" />

      <NcAppNavigationSpacer />
      <NcAppNavigationSettings name="Einstellungen">
        <NcAppNavigationItem :to="{ name: 'settings-company' }" name="Firma" />
        <NcAppNavigationItem :to="{ name: 'settings-texts' }" name="Texte" />
        <NcAppNavigationItem :to="{ name: 'settings-tax' }" name="Steuer" />
        <NcAppNavigationItem :to="{ name: 'settings-misc' }" name="Kontodaten" />
        <NcAppNavigationItem :to="{ name: 'settings-documents' }" name="Dokumente" />
        <NcAppNavigationItem :to="{ name: 'settings-email' }" name="E-Mailverhalten" />
      </NcAppNavigationSettings>

      <NcAppNavigationSpacer />
      <NcAppNavigationItem :to="{ name: 'settings-help' }" name="Hilfe" />
    </NcAppNavigation>

    <NcAppContent>
      <div class="nextledger-content">
        <router-view />
      </div>
    </NcAppContent>
  </NcContent>
</template>

<script>
import {
  NcContent,
  NcAppContent,
  NcAppNavigation,
  NcAppNavigationItem,
  NcAppNavigationSettings,
} from '@nextcloud/vue'
import NcAppNavigationSpacer from '@nextcloud/vue/dist/Components/NcAppNavigationSpacer.mjs'
import { getCompanies } from './api/settings'

export default {
  name: 'NextLedgerApp',
  components: {
    NcContent,
    NcAppContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSpacer,
    NcAppNavigationSettings,
  },
  data() {
    return {
      activeCompanyName: '',
    }
  },
  computed: {
    casesNavName() {
      return this.activeCompanyName
        ? `Vorgänge · ${this.activeCompanyName}`
        : 'Vorgänge'
    },
  },
  async created() {
    await this.loadCompanyContext()
    window.addEventListener('nextledger-company-changed', this.loadCompanyContext)
  },
  beforeDestroy() {
    window.removeEventListener('nextledger-company-changed', this.loadCompanyContext)
  },
  methods: {
    async loadCompanyContext() {
      try {
        const data = await getCompanies()
        const activeId = Number(data.activeCompanyId)
        const companies = Array.isArray(data.companies) ? data.companies : []
        const active = companies.find((entry) => Number(entry.id) === activeId)
        this.activeCompanyName = active?.name ? String(active.name) : ''
      } catch (e) {
        this.activeCompanyName = ''
      }
    },
  },
}
</script>

<style scoped>
.nextledger-content {
  padding: 24px;
}

:global(.app-nextledger.content) {
  margin: 0;
  margin-top: 0;
  width: 100%;
  height: 100%;
  border-radius: 0;
}

:global(.app-nextledger.content:not(.with-sidebar--full)) {
  position: initial;
}

:global(.nextledger-content .header > div:first-child) {
  padding-left: 28px;
}

:global(.nextledger-content .header h1) {
  font-size: 28px;
  line-height: 1.2;
}

:global(.nextledger-content > section > h1:first-child) {
  padding-left: 28px;
  font-size: 28px;
  line-height: 1.2;
  margin-bottom: 12px;
}

:global(.nextledger-content > section > h1:first-child + .subline) {
  padding-left: 28px;
}

:global(.nextledger-content .header .subline) {
  margin-left: 0;
}

.app-navigation-spacer {
  border-top: 1px solid var(--color-border, #e5e7eb);
  margin: 8px 12px;
  height: 0;
}
</style>
