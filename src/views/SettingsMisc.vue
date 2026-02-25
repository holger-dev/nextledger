<template>
  <section class="settings">
    <h1>{{ t('title') }}</h1>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="form">
      <NcTextField
        :label="t('paymentTermsDays')"
        type="text"
        :placeholder="t('paymentTermsPlaceholder')"
        :value.sync="form.paymentTermsDays"
      />
      <p v-if="fieldErrors.paymentTermsDays" class="field-error">{{ fieldErrors.paymentTermsDays }}</p>
      <NcTextField :label="t('bankName')" :value.sync="form.bankName" />
      <NcTextField :label="t('iban')" :value.sync="form.iban" />
      <NcTextField :label="t('bic')" :value.sync="form.bic" />
      <NcTextField :label="t('accountHolder')" :value.sync="form.accountHolder" />

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
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import { getMisc, saveMisc } from '../api/settings'

export default {
  name: 'SettingsMisc',
  components: {
    NcButton,
    NcLoadingIcon,
    NcTextField,
  },
  data() {
    return {
      loading: true,
      saving: false,
      saved: false,
      error: '',
      form: {
        paymentTermsDays: '14',
        bankName: '',
        iban: '',
        bic: '',
        accountHolder: '',
      },
      fieldErrors: {},
    }
  },
  async mounted() {
    await this.load()
  },
  methods: {
    t(key) {
      return this.$tKey(`settingsMisc.${key}`, key)
    },
    async load() {
      this.loading = true
      this.error = ''
      try {
        const data = await getMisc()
        const safeString = (value) => (value === null || value === undefined ? '' : String(value))
        this.form = {
          ...this.form,
          paymentTermsDays: safeString(data.paymentTermsDays ?? this.form.paymentTermsDays),
          bankName: safeString(data.bankName),
          iban: safeString(data.iban),
          bic: safeString(data.bic),
          accountHolder: safeString(data.accountHolder),
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
        this.fieldErrors = {}
        const days = Number(this.form.paymentTermsDays || 0)
        if (!Number.isInteger(days) || days < 0) {
          this.fieldErrors = { paymentTermsDays: this.t('paymentTermsError') }
          this.saving = false
          return
        }
        const payload = {
          ...this.form,
          paymentTermsDays: days,
        }
        const data = await saveMisc(payload)
        this.form = { ...this.form, ...data }
        if (data.paymentTermsDays !== undefined && data.paymentTermsDays !== null) {
          this.form.paymentTermsDays = String(data.paymentTermsDays)
        }
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
  max-width: 640px;
}

.form > * {
  margin-bottom: 16px;
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

.field-error {
  color: var(--color-error, #b91c1c);
  font-size: 12px;
}
</style>
