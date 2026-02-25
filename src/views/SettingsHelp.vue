<template>
  <section class="settings-help">
    <h1>{{ t('title') }}</h1>

    <div class="card">
      <h2>{{ t('setupTitle') }}</h2>
      <ol>
        <li>{{ t('setupStep1') }}</li>
        <li>{{ t('setupStep2') }}</li>
        <li>{{ t('setupStep3') }}</li>
        <li>{{ t('setupStep4') }}</li>
        <li>{{ t('setupStep5') }}</li>
      </ol>
      <div class="setup-links">
        <NcButton type="secondary" :to="{ name: 'fiscal-year' }">
          {{ t('fiscalYearLink') }}
        </NcButton>
        <NcButton type="secondary" :to="{ name: 'settings-company' }">
          {{ t('companyDataLink') }}
        </NcButton>
        <NcButton type="secondary" :to="{ name: 'customers' }">
          {{ t('customersLink') }}
        </NcButton>
        <NcButton type="secondary" :to="{ name: 'products' }">
          {{ t('productsLink') }}
        </NcButton>
        <NcButton type="secondary" :to="{ name: 'cases' }">
          {{ t('casesLink') }}
        </NcButton>
      </div>
    </div>

    <div class="card">
      <h2>{{ t('quickGuideTitle') }}</h2>
      <ol>
        <li>{{ t('quickGuideStep1') }}</li>
        <li>{{ t('quickGuideStep2') }}</li>
        <li>{{ t('quickGuideStep3') }}</li>
        <li>{{ t('quickGuideStep4') }}</li>
        <li>{{ t('quickGuideStep5') }}</li>
        <li>{{ t('quickGuideStep6') }}</li>
      </ol>
    </div>

    <div class="card">
      <h2>{{ t('termsTitle') }}</h2>
      <ul>
        <li>{{ t('termCustomer') }}</li>
        <li>{{ t('termCase') }}</li>
        <li>{{ t('termPosition') }}</li>
        <li>{{ t('termOffer') }}</li>
        <li>{{ t('termInvoice') }}</li>
      </ul>
    </div>

    <div class="card">
      <h2>{{ t('offersInvoicesTitle') }}</h2>
      <ul>
        <li>{{ t('offersInvoicesPoint1') }}</li>
        <li>{{ t('offersInvoicesPoint2') }}</li>
        <li>{{ t('offersInvoicesPoint3') }}</li>
        <li>{{ t('offersInvoicesPoint4') }}</li>
      </ul>
    </div>

    <div class="card">
      <h2>{{ t('advanceTitle') }}</h2>
      <p>
        {{ t('advanceIntro') }}
      </p>
      <ul>
        <li>{{ t('advancePoint1') }}</li>
        <li>{{ t('advancePoint2') }}</li>
        <li>{{ t('advancePoint3') }}</li>
        <li>{{ t('advancePoint4') }}</li>
      </ul>
    </div>

    <div class="card">
      <h2>{{ t('emailTitle') }}</h2>
      <ul>
        <li>{{ t('emailPoint1') }}</li>
        <li>{{ t('emailPoint2') }}</li>
        <li>{{ t('emailPoint3') }}</li>
        <li>{{ t('emailPoint4') }}</li>
      </ul>
    </div>

    <div class="card">
      <h2>{{ t('fiscalYearTitle') }}</h2>
      <ul>
        <li>{{ t('fiscalYearPoint1') }}</li>
        <li>{{ t('fiscalYearPoint2') }}</li>
        <li>{{ t('fiscalYearPoint3') }}</li>
        <li>{{ t('fiscalYearPoint4') }}</li>
        <li>{{ t('fiscalYearPoint5') }}</li>
      </ul>
    </div>

    <div class="card">
      <h2>{{ t('textsTemplatesTitle') }}</h2>
      <ul>
        <li>{{ t('textsTemplatesPoint1') }}</li>
        <li>{{ t('textsTemplatesPoint2') }}</li>
        <li>{{ t('textsTemplatesPoint3') }}</li>
      </ul>
    </div>

    <div class="card">
      <h2>{{ t('troubleshootingTitle') }}</h2>
      <ul>
        <li>{{ t('troubleshootingPoint1') }}</li>
        <li>{{ t('troubleshootingPoint2') }}</li>
        <li>{{ t('troubleshootingPoint3') }}</li>
      </ul>
    </div>

    <div class="card">
      <h2>{{ t('supportTitle') }}</h2>
      <p>{{ t('supportText') }} <strong>holger@heidkamp.dev</strong></p>
    </div>
  </section>
</template>

<script>
import { NcButton } from '@nextcloud/vue'

export default {
  name: 'SettingsHelp',
  components: {
    NcButton,
  },
  methods: {
    t(key) {
      return this.$tKey(`settingsHelp.${key}`, key)
    },
  },
}
</script>

<style scoped>
.settings-help {
  max-width: 860px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card {
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 12px;
  padding: 16px;
  background: var(--color-main-background, #fff);
}

.card h2 {
  margin: 0 0 12px;
  font-size: 18px;
}

.card p {
  margin: 0 0 12px;
}

.setup-links {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

ol,
ul {
  margin: 0;
  padding-left: 20px;
}

li {
  margin: 6px 0;
}
</style>
