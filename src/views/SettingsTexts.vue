<template>
  <section class="settings">
    <h1>{{ t('title') }}</h1>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="form">
      <div class="section">
        <div class="section__header">
          <h2>{{ t('pdfTextsTitle') }}</h2>
          <p class="hint">{{ t('pdfTextsHint') }}</p>
        </div>

        <div class="text-block">
          <p class="text-block__hint">{{ t('invoiceGreetingHint') }}</p>
          <NcTextArea :label="t('invoiceGreetingLabel')" :value.sync="form.invoiceGreeting" />
        </div>

        <div class="text-block">
          <p class="text-block__hint">{{ t('offerGreetingHint') }}</p>
          <NcTextArea :label="t('offerGreetingLabel')" :value.sync="form.offerGreeting" />
        </div>

        <div class="text-block">
          <p class="text-block__hint">{{ t('closingHint') }}</p>
          <NcTextArea :label="t('invoiceClosingLabel')" :value.sync="form.invoiceClosingText" />
        </div>

        <div class="text-block">
          <p class="text-block__hint">{{ t('closingHint') }}</p>
          <NcTextArea :label="t('offerClosingLabel')" :value.sync="form.offerClosingText" />
        </div>

        <div class="text-block">
          <p class="text-block__hint">{{ t('footerHint') }}</p>
          <NcTextArea :label="t('footerLabel')" :value.sync="form.footerText" />
        </div>
      </div>

      <div class="section">
        <div class="section__header">
          <h2>{{ t('emailTextsTitle') }}</h2>
          <p class="hint">{{ t('emailTextsHint') }}</p>
        </div>
        <div class="text-block">
          <p class="text-block__hint">
            {{ t('offerPlaceholdersHint') }}
          </p>
          <NcTextArea
            :label="t('offerEmailSubjectLabel')"
            :value.sync="form.offerEmailSubject"
            :placeholder="t('offerEmailSubjectPlaceholder')"
          />
        </div>

        <div class="text-block">
          <p class="text-block__hint">
            {{ t('offerPlaceholdersHint') }}
          </p>
          <NcTextArea
            :label="t('offerEmailBodyLabel')"
            :value.sync="form.offerEmailBody"
            :placeholder="t('offerEmailBodyPlaceholder')"
          />
        </div>

        <div class="text-block">
          <p class="text-block__hint">
            {{ t('invoicePlaceholdersHint') }}
          </p>
          <NcTextArea
            :label="t('invoiceEmailSubjectLabel')"
            :value.sync="form.invoiceEmailSubject"
            :placeholder="t('invoiceEmailSubjectPlaceholder')"
          />
        </div>

        <div class="text-block">
          <p class="text-block__hint">
            {{ t('invoicePlaceholdersHint') }}
          </p>
          <NcTextArea
            :label="t('invoiceEmailBodyLabel')"
            :value.sync="form.invoiceEmailBody"
            :placeholder="t('invoiceEmailBodyPlaceholder')"
          />
        </div>
      </div>

      <div class="actions">
        <NcButton type="primary" :disabled="saving" @click="save">
          {{ t('save') }}
        </NcButton>
        <span v-if="saving" class="hint">{{ t('saving') }}</span>
        <span v-if="saved" class="success">{{ t('saved') }}</span>
        <span v-if="error" class="error">{{ error }}</span>
      </div>
    </div>
  </section>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.mjs'
import { getTexts, saveTexts } from '../api/settings'

export default {
  name: 'SettingsTexts',
  components: {
    NcButton,
    NcLoadingIcon,
    NcTextArea,
  },
  data() {
    return {
      loading: true,
      saving: false,
      saved: false,
      error: '',
      form: {
        invoiceGreeting: '',
        offerGreeting: '',
        footerText: '',
        offerClosingText: '',
        invoiceClosingText: '',
        offerEmailSubject: '',
        offerEmailBody: '',
        invoiceEmailSubject: '',
        invoiceEmailBody: '',
      },
    }
  },
  async mounted() {
    await this.load()
  },
  methods: {
    t(key) {
      return this.$tKey(`settingsTexts.${key}`, key)
    },
    async load() {
      this.loading = true
      this.error = ''
      try {
        const data = await getTexts()
        const safeString = (value) => (value === null || value === undefined ? '' : String(value))
        this.form = {
          ...this.form,
          invoiceGreeting: safeString(data.invoiceGreeting),
          offerGreeting: safeString(data.offerGreeting),
          footerText: safeString(data.footerText),
          offerClosingText: safeString(data.offerClosingText),
          invoiceClosingText: safeString(data.invoiceClosingText),
          offerEmailSubject: safeString(data.offerEmailSubject),
          offerEmailBody: safeString(data.offerEmailBody),
          invoiceEmailSubject: safeString(data.invoiceEmailSubject),
          invoiceEmailBody: safeString(data.invoiceEmailBody),
        }
      } catch (e) {
        this.error = this.t('loadError')
      } finally {
        this.loading = false
      }
    },
    async save() {
      this.saving = true
      this.saved = false
      this.error = ''
      try {
        const data = await saveTexts(this.form)
        this.form = { ...this.form, ...data }
        this.saved = true
        window.setTimeout(() => {
          this.saved = false
        }, 2000)
      } catch (e) {
        this.error = this.t('saveError')
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

<style scoped>
.settings {
  max-width: 860px;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section {
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 12px;
  padding: 16px;
  background: var(--color-main-background, #fff);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section__header h2 {
  margin: 0 0 4px;
  font-size: 18px;
}

.section__header .hint {
  margin: 0;
}

.text-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.text-block__hint {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  display: block;
  opacity: 0.9;
  color: var(--color-text-lighter, #6b7280);
}

.actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hint {
  color: var(--color-text-lighter, #6b7280);
}

.success {
  color: var(--color-success, #2d9a4f);
}

.error {
  color: var(--color-error, #b91c1c);
}
</style>
