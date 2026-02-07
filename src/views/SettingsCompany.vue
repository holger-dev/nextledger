<template>
  <section class="settings">
    <h1>Firma</h1>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="form">
      <NcTextField label="Firma" :value.sync="form.name" />
      <NcTextField label="Firmeninhaber" :value.sync="form.ownerName" />
      <NcTextField label="Straße" :value.sync="form.street" />
      <NcTextField label="Hausnummer" :value.sync="form.houseNumber" />
      <NcTextField label="PLZ" :value.sync="form.zip" />
      <NcTextField label="Stadt" :value.sync="form.city" />
      <NcTextField label="E-Mail" :value.sync="form.email" />
      <NcTextField label="Telefon" :value.sync="form.phone" />
      <NcTextField label="USt-Id" :value.sync="form.vatId" />
      <NcTextField label="Steuernummer" :value.sync="form.taxId" />

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
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import { getCompany, saveCompany } from '../api/settings'

export default {
  name: 'SettingsCompany',
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
        name: '',
        ownerName: '',
        street: '',
        houseNumber: '',
        zip: '',
        city: '',
        email: '',
        phone: '',
        vatId: '',
        taxId: '',
      },
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
        const data = await getCompany()
        const safeString = (value) => (value === null || value === undefined ? '' : String(value))
        this.form = {
          ...this.form,
          name: safeString(data.name),
          ownerName: safeString(data.ownerName),
          street: safeString(data.street),
          houseNumber: safeString(data.houseNumber),
          zip: safeString(data.zip),
          city: safeString(data.city),
          email: safeString(data.email),
          phone: safeString(data.phone),
          vatId: safeString(data.vatId),
          taxId: safeString(data.taxId),
        }
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
        const data = await saveCompany(this.form)
        this.form = { ...this.form, ...data }
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
</style>
