<template>
  <section class="settings">
    <h1>Firma</h1>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="form">
      <div class="new-company-row">
        <NcTextField
          label="Neue Firma anlegen"
          :value.sync="newCompanyName"
          placeholder="z.B. Muster GmbH"
        />
        <NcButton type="secondary" :disabled="creating" @click="createNewCompany">
          Anlegen
        </NcButton>
      </div>

      <div class="company-list">
        <h2>Firmenübersicht</h2>
        <table class="table">
          <thead>
            <tr>
              <th>Firma</th>
              <th>Inhaber</th>
              <th>Status</th>
              <th class="actions-cell">Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in companies" :key="entry.id">
              <td class="name">{{ entry.name || `Firma #${entry.id}` }}</td>
              <td>{{ entry.ownerName || '—' }}</td>
              <td>
                <span v-if="isActiveCompany(entry.id)" class="status active">Aktiv</span>
                <span v-else class="status inactive">Inaktiv</span>
              </td>
              <td class="actions-cell">
                <NcButton
                  type="tertiary"
                  :disabled="switching || isActiveCompany(entry.id)"
                  @click="switchCompany(entry.id)"
                >
                  Wechseln
                </NcButton>
                <NcButton
                  type="tertiary"
                  :disabled="switching || companies.length <= 1 || isActiveCompany(entry.id)"
                  @click="deleteCompanyEntry(entry)"
                >
                  Löschen
                </NcButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

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

      <div class="form-actions">
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
import {
  activateCompany,
  createCompany,
  deleteCompany,
  getCompanies,
  getCompany,
  saveCompany,
} from '../api/settings'

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
      switching: false,
      creating: false,
      saving: false,
      saved: false,
      error: '',
      activeCompanyId: null,
      companies: [],
      newCompanyName: '',
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
        await this.refreshCompanies()
        await this.loadActiveCompany()
      } catch (e) {
        this.error = 'Daten konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    async refreshCompanies() {
      const data = await getCompanies()
      this.companies = Array.isArray(data.companies) ? data.companies : []
      this.activeCompanyId = Number(data.activeCompanyId)
    },
    async loadActiveCompany() {
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
    },
    isActiveCompany(id) {
      return Number(this.activeCompanyId) === Number(id)
    },
    async switchCompany(id) {
      const selectedId = Number(id)
      if (!selectedId || selectedId === this.activeCompanyId) {
        return
      }

      this.switching = true
      this.error = ''
      try {
        await activateCompany(selectedId)
        await this.refreshCompanies()
        await this.loadActiveCompany()
        this.emitCompanyChanged()
      } catch (e) {
        this.error = 'Firma konnte nicht gewechselt werden.'
      } finally {
        this.switching = false
      }
    },
    async createNewCompany() {
      this.creating = true
      this.error = ''
      try {
        const name = (this.newCompanyName || '').trim() || 'Neue Firma'
        await createCompany({ name })
        this.newCompanyName = ''
        await this.refreshCompanies()
        await this.loadActiveCompany()
        this.emitCompanyChanged()
      } catch (e) {
        this.error = 'Firma konnte nicht angelegt werden.'
      } finally {
        this.creating = false
      }
    },
    async deleteCompanyEntry(entry) {
      const companyId = Number(entry?.id)
      if (!companyId) {
        return
      }
      if (this.isActiveCompany(companyId)) {
        this.error = 'Aktive Firma kann nicht gelöscht werden.'
        return
      }
      if (!window.confirm(`Firma "${entry?.name || `#${companyId}`}" wirklich löschen?`)) {
        return
      }

      this.switching = true
      this.error = ''
      try {
        await deleteCompany(companyId)
        await this.refreshCompanies()
        await this.loadActiveCompany()
        this.emitCompanyChanged()
      } catch (e) {
        this.error = 'Firma konnte nicht gelöscht werden.'
      } finally {
        this.switching = false
      }
    },
    async save() {
      this.saving = true
      this.saved = false
      this.error = ''
      try {
        const data = await saveCompany(this.form)
        this.form = { ...this.form, ...data }
        await this.refreshCompanies()
        this.saved = true
        this.emitCompanyChanged()
        window.setTimeout(() => {
          this.saved = false
        }, 2000)
      } catch (e) {
        this.error = 'Speichern fehlgeschlagen.'
      } finally {
        this.saving = false
      }
    },
    emitCompanyChanged() {
      window.dispatchEvent(new CustomEvent('nextledger-company-changed'))
    },
  },
}
</script>

<style scoped>
.settings {
  max-width: 760px;
}

.form > * {
  margin-bottom: 16px;
}

.company-list {
  border: 1px solid var(--color-border, #d1d5db);
  border-radius: 12px;
  padding: 12px;
}

.company-list h2 {
  margin: 0 0 12px;
  font-size: 16px;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th,
.table td {
  border-bottom: 1px solid var(--color-border, #e5e7eb);
  padding: 10px 8px;
  text-align: left;
  vertical-align: middle;
}

.table th {
  font-size: 12px;
  color: var(--color-text-lighter, #6b7280);
}

.name {
  font-weight: 600;
}

.status {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
}

.status.active {
  background: rgba(45, 154, 79, 0.14);
  color: var(--color-success, #2d9a4f);
}

.status.inactive {
  background: rgba(107, 114, 128, 0.14);
  color: var(--color-text-lighter, #6b7280);
}

.actions-cell {
  display: flex;
  gap: 8px;
}

.new-company-row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  align-items: end;
}

.form-actions {
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
