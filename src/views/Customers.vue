<template>
  <section class="customers">
    <div class="header">
      <div>
        <h1>Kunden</h1>
        <p class="subline">Übersicht der Kunden.</p>
      </div>
      <NcButton type="primary" @click="openCreateModal">
        Neuer Kunde
      </NcButton>
    </div>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="content">
      <div class="filters">
        <div class="filter-group">
          <NcTextField
            label="Suche"
            placeholder="Name, Ansprechpartner, Ort, E-Mail…"
            :value.sync="query"
          />
        </div>
      </div>

      <NcEmptyContent
        v-if="filteredItems.length === 0"
        name="Noch keine Kunden"
        description="Lege deine ersten Kunden an."
      />

      <table v-else class="table">
        <thead>
          <tr>
            <th>Firma</th>
            <th>Ansprechpartner</th>
            <th>Ort</th>
            <th>E-Mail</th>
            <th class="actions">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in filteredItems" :key="item.id">
            <td class="name">{{ item.company }}</td>
            <td>{{ item.contactName || '–' }}</td>
            <td>{{ formatLocation(item) }}</td>
            <td>{{ item.email || '–' }}</td>
            <td class="actions">
              <NcButton
                type="tertiary-no-background"
                aria-label="Kunde bearbeiten"
                title="Bearbeiten"
                @click="editItem(item)"
              >
                <template #icon>
                  <Pencil :size="18" />
                </template>
              </NcButton>
              <NcButton
                type="tertiary-no-background"
                aria-label="Kunde löschen"
                title="Löschen"
                @click="removeItem(item)"
              >
                <template #icon>
                  <TrashCanOutline :size="18" />
                </template>
              </NcButton>
            </td>
          </tr>
        </tbody>
      </table>

      <p v-if="error" class="error">{{ error }}</p>
    </div>

    <NcModal v-if="showModal" size="normal" @close="closeModal">
      <div class="modal__content">
        <h2>{{ editingId ? 'Kunde bearbeiten' : 'Neuer Kunde' }}</h2>

        <div class="form-group">
          <NcTextField label="Firma *" :value.sync="form.company" />
          <p v-if="fieldErrors.company" class="field-error">{{ fieldErrors.company }}</p>
        </div>
        <div class="form-group">
          <NcTextField label="Ansprechpartner" :value.sync="form.contactName" />
        </div>
        <div class="form-group">
          <NcTextField label="Straße" :value.sync="form.street" />
        </div>
        <div class="form-group">
          <NcTextField label="Hausnummer" :value.sync="form.houseNumber" />
        </div>
        <div class="form-group">
          <NcTextField label="PLZ" :value.sync="form.zip" />
        </div>
        <div class="form-group">
          <NcTextField label="Stadt" :value.sync="form.city" />
        </div>
        <div class="form-group">
          <NcTextField label="E-Mail" :value.sync="form.email" />
          <p v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</p>
        </div>

        <div class="actions">
          <NcButton type="primary" :disabled="saving || !canSave" @click="save">
            {{ editingId ? 'Aktualisieren' : 'Anlegen' }}
          </NcButton>
          <NcButton type="secondary" @click="closeModal">Abbrechen</NcButton>
          <span v-if="saving" class="hint">Speichere…</span>
          <span v-if="saved" class="success">Gespeichert</span>
          <span v-if="error" class="error">{{ error }}</span>
        </div>
      </div>
    </NcModal>
  </section>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon, NcModal } from '@nextcloud/vue'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import {
  createCustomer,
  deleteCustomer,
  getCustomers,
  updateCustomer,
} from '../api/customers'

export default {
  name: 'Customers',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcModal,
    NcTextField,
    Pencil,
    TrashCanOutline,
  },
  data() {
    return {
      loading: true,
      saving: false,
      saved: false,
      error: '',
      items: [],
      query: '',
      editingId: null,
      showModal: false,
      form: {
        company: '',
        contactName: '',
        street: '',
        houseNumber: '',
        zip: '',
        city: '',
        email: '',
      },
      fieldErrors: {},
    }
  },
  computed: {
    canSave() {
      return this.form.company.trim() !== ''
    },
    filteredItems() {
      const query = this.query.trim().toLowerCase()
      if (!query) {
        return this.items
      }
      return this.items.filter((item) => {
        const haystack = [
          item.company,
          item.contactName,
          item.street,
          item.houseNumber,
          item.zip,
          item.city,
          item.email,
        ]
          .filter(Boolean)
          .join(' ')
          .toLowerCase()
        return haystack.includes(query)
      })
    },
  },
  async mounted() {
    await this.load()
  },
  methods: {
    isValidEmail(value) {
      if (!value) {
        return true
      }
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
    },
    async load() {
      this.loading = true
      this.error = ''
      try {
        const data = await getCustomers()
        this.items = Array.isArray(data) ? data : []
      } catch (e) {
        this.error = 'Kunden konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    resetForm() {
      this.editingId = null
      this.form = {
        company: '',
        contactName: '',
        street: '',
        houseNumber: '',
        zip: '',
        city: '',
        email: '',
      }
      this.fieldErrors = {}
      this.error = ''
    },
    openCreateModal() {
      this.resetForm()
      this.showModal = true
    },
    closeModal() {
      this.showModal = false
      this.resetForm()
    },
    editItem(item) {
      this.editingId = item.id
      this.form = {
        company: item.company || '',
        contactName: item.contactName || '',
        street: item.street || '',
        houseNumber: item.houseNumber || '',
        zip: item.zip || '',
        city: item.city || '',
        email: item.email || '',
      }
      this.fieldErrors = {}
      this.error = ''
      this.showModal = true
    },
    clearFieldErrors() {
      this.fieldErrors = {}
    },
    async save() {
      this.clearFieldErrors()
      if (!this.canSave) {
        this.fieldErrors = { company: 'Bitte eine Firma angeben.' }
        return
      }
      if (!this.isValidEmail(this.form.email.trim())) {
        this.fieldErrors = { email: 'Bitte eine gültige E-Mail-Adresse angeben.' }
        return
      }

      this.saving = true
      this.saved = false
      this.error = ''

      const payload = {
        company: this.form.company.trim(),
        contactName: this.form.contactName.trim(),
        street: this.form.street.trim(),
        houseNumber: this.form.houseNumber.trim(),
        zip: this.form.zip.trim(),
        city: this.form.city.trim(),
        email: this.form.email.trim(),
      }

      try {
        if (this.editingId) {
          const saved = await updateCustomer(this.editingId, payload)
          this.items = this.items.map((item) =>
            item.id === this.editingId ? saved : item
          )
        } else {
          const saved = await createCustomer(payload)
          this.items = [...this.items, saved]
          this.resetForm()
        }

        this.saved = true
        window.setTimeout(() => {
          this.saved = false
        }, 2000)
        this.closeModal()
      } catch (e) {
        this.error = 'Speichern fehlgeschlagen.'
      } finally {
        this.saving = false
      }
    },
    async removeItem(item) {
      if (!window.confirm('Kunde wirklich löschen?')) {
        return
      }
      this.saving = true
      this.error = ''
      try {
        await deleteCustomer(item.id)
        this.items = this.items.filter((entry) => entry.id !== item.id)
        if (this.editingId === item.id) {
          this.resetForm()
        }
        if (this.showModal) {
          this.closeModal()
        }
      } catch (e) {
        this.error = 'Löschen fehlgeschlagen.'
      } finally {
        this.saving = false
      }
    },
    formatLocation(item) {
      const parts = [item.zip, item.city].filter(Boolean)
      return parts.length > 0 ? parts.join(' ') : '–'
    },
  },
}
</script>

<style scoped>
.customers {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}

.subline {
  margin: 4px 0 0;
  color: var(--color-text-lighter, #6b7280);
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 16px;
  align-items: flex-end;
  background: var(--color-background-dark, #f3f4f6);
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 12px;
}

.filter-group {
  min-width: 200px;
  flex: 1 1 220px;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th,
.table td {
  padding: 10px 8px;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
  vertical-align: top;
}

.table th.actions,
.table td.actions {
  text-align: right;
  white-space: nowrap;
}

.table td.actions > * {
  margin-left: 8px;
}

.table td.name {
  font-weight: 600;
}

.actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
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

.modal__content {
  margin: calc(var(--default-grid-baseline) * 4);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: calc(var(--default-grid-baseline) * 2) 0;
}

@media (max-width: 900px) {
  .header {
    flex-direction: column;
    align-items: flex-start;
  }
  .filter-group {
    flex: 1 1 100%;
  }
}
</style>
