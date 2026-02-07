<template>
  <NcAppContent>
    <template #list>
      <NcAppNavigation>
        <NcAppNavigationItem :to="{ name: 'cases' }" name="Vorgänge" />
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
          <NcAppNavigationItem :to="{ name: 'settings-misc' }" name="Weiteres" />
        </NcAppNavigationSettings>

        <NcAppNavigationSpacer />
        <NcAppNavigationItem :to="{ name: 'settings-help' }" name="Hilfe" />
      </NcAppNavigation>
    </template>

    <template #default>
      <div class="nextledger-content">
        <router-view />
      </div>
    </template>
  </NcAppContent>
</template>

<script>
import {
  NcAppContent,
  NcAppNavigation,
  NcAppNavigationItem,
  NcAppNavigationSettings,
} from '@nextcloud/vue'
import NcAppNavigationSpacer from '@nextcloud/vue/dist/Components/NcAppNavigationSpacer.mjs'

export default {
  name: 'NextLedgerApp',
  components: {
    NcAppContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSpacer,
    NcAppNavigationSettings,
  },
}
</script>

<style scoped>
.nextledger-content {
  padding: 24px;
}

.app-navigation-spacer {
  border-top: 1px solid var(--color-border, #e5e7eb);
  margin: 8px 12px;
  height: 0;
}
</style>
