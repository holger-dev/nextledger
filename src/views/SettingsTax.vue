<template>
  <section class="settings">
    <h1>Steuer</h1>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="form">
      <NcCheckboxRadioSwitch
        type="switch"
        :checked="form.isSmallBusiness"
        @update:checked="form.isSmallBusiness = $event"
      >
        Kleinunternehmerregelung anwenden
      </NcCheckboxRadioSwitch>

      <NcTextField
        label="Standard-USt-Satz (%) *"
        type="text"
        placeholder="z. B. 19"
        :disabled="form.isSmallBusiness"
        :value.sync="form.vatRatePercent"
      />
      <p v-if="fieldErrors.vatRatePercent" class="field-error">{{ fieldErrors.vatRatePercent }}</p>

      <NcTextArea
        label="Hinweistext Kleinunternehmer"
        :disabled="!form.isSmallBusiness"
        :value.sync="form.smallBusinessNote"
      />

      <div class="actions">
        <NcButton type="primary" :disabled="saving" @click="save">
          Speichern
        </NcButton>
        <span v-if="saving" class="hint">Speichere…</span>
        <span v-if="saved" class="success">Gespeichert</span>
        <span v-if="error" class="error">{{ error }}</span>
      </div>
    </div>
  </section>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcLoadingIcon } from '@nextcloud/vue'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.mjs'
import { getTax, saveTax } from '../api/settings'

export default {
  name: 'SettingsTax',
  components: {
    NcButton,
    NcCheckboxRadioSwitch,
    NcLoadingIcon,
    NcTextArea,
    NcTextField,
  },
  data() {
    return {
      loading: true,
      saving: false,
      saved: false,
      error: '',
      form: {
        vatRatePercent: '19',
        isSmallBusiness: false,
        smallBusinessNote: '',
      },
      fieldErrors: {},
    }
  },
  async mounted() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      this.error = ''
      try {
        const data = await getTax()
        const vatRateBp = Number(data.vatRateBp || 0)
        this.form.vatRatePercent = vatRateBp ? String(Math.round(vatRateBp / 100)) : this.form.vatRatePercent
        const parseBool = (value) => {
          if (value === true || value === false) {
            return value
          }
          if (value === 1 || value === '1') {
            return true
          }
          if (value === 0 || value === '0') {
            return false
          }
          if (typeof value === 'string') {
            return ['true', 'yes', 'on'].includes(value.toLowerCase())
          }
          return Boolean(value)
        }
        this.form.isSmallBusiness = parseBool(data.isSmallBusiness)
        this.form.smallBusinessNote = data.smallBusinessNote || ''
      } catch (e) {
        this.error = 'Daten konnten nicht geladen werden.'
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
        if (!this.form.isSmallBusiness) {
          const rate = Number(this.form.vatRatePercent)
          if (Number.isNaN(rate) || rate < 0) {
            this.fieldErrors = { vatRatePercent: 'Bitte einen gültigen Steuersatz angeben.' }
            this.saving = false
            return
          }
        }
        const payload = {
          vatRateBp: Math.round(Number(this.form.vatRatePercent || 0) * 100),
          isSmallBusiness: Boolean(this.form.isSmallBusiness),
          smallBusinessNote: this.form.smallBusinessNote,
        }
        const data = await saveTax(payload)
        const vatRateBp = Number(data.vatRateBp || payload.vatRateBp)
        this.form.vatRatePercent = vatRateBp ? String(Math.round(vatRateBp / 100)) : this.form.vatRatePercent
        const parseBool = (value) => {
          if (value === true || value === false) {
            return value
          }
          if (value === 1 || value === '1') {
            return true
          }
          if (value === 0 || value === '0') {
            return false
          }
          if (typeof value === 'string') {
            return ['true', 'yes', 'on'].includes(value.toLowerCase())
          }
          return Boolean(value)
        }
        this.form.isSmallBusiness = parseBool(data.isSmallBusiness)
        this.form.smallBusinessNote = data.smallBusinessNote || ''
        this.saved = true
        window.setTimeout(() => {
          this.saved = false
        }, 2000)
      } catch (e) {
        this.error = 'Speichern fehlgeschlagen.'
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
