<template>
  <section class="products">
    <div class="header">
      <div>
        <h1>Produkte & Dienstleistungen</h1>
        <p class="subline">Übersicht der Produkte und Dienstleistungen.</p>
      </div>
      <NcButton type="primary" @click="openCreateModal">
        Neues Produkt/DL
      </NcButton>
    </div>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="content">
      <div class="filters">
        <div class="filter-group">
          <NcTextField
            label="Suche"
            placeholder="Name oder Beschreibung…"
            :value.sync="query"
          />
        </div>
      </div>

      <NcEmptyContent
        v-if="filteredItems.length === 0"
        name="Noch keine Produkte"
        description="Lege Produkte oder Dienstleistungen an."
      />

      <table v-else class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Beschreibung</th>
            <th class="price">Stückpreis</th>
            <th class="actions">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in filteredItems" :key="item.id">
            <td class="name">{{ item.name }}</td>
            <td class="description">{{ item.description || '–' }}</td>
            <td class="price">{{ formatPrice(item.unitPriceCents) }}</td>
            <td class="actions">
              <NcButton
                type="tertiary-no-background"
                aria-label="Produkt bearbeiten"
                title="Bearbeiten"
                @click="editItem(item)"
              >
                <template #icon>
                  <Pencil :size="18" />
                </template>
              </NcButton>
              <NcButton
                type="tertiary-no-background"
                aria-label="Produkt löschen"
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
        <h2>{{ editingId ? 'Produkt/DL bearbeiten' : 'Neues Produkt/DL' }}</h2>

        <div class="form-group">
          <NcTextField label="Name *" :value.sync="form.name" />
          <p v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</p>
        </div>
        <div class="form-group">
          <NcTextField label="Beschreibung" :value.sync="form.description" />
        </div>
        <div class="form-group">
          <NcTextField
            label="Stückpreis *"
            :value.sync="form.unitPrice"
            type="text"
            placeholder="0.00"
          />
          <p v-if="fieldErrors.unitPrice" class="field-error">{{ fieldErrors.unitPrice }}</p>
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
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import {
  createProduct,
  deleteProduct,
  getProducts,
  updateProduct,
} from '../api/products'

const parseMoneyInput = (value) => {
  if (value === null || value === undefined) {
    return null
  }
  const raw = String(value).trim().replace(/\s/g, '')
  if (!raw) {
    return null
  }
  const normalized = raw.includes(',')
    ? raw.replace(/\./g, '').replace(',', '.')
    : raw
  const parsed = Number(normalized)
  if (Number.isNaN(parsed)) {
    return null
  }
  return parsed
}

const centsFromInput = (value) => {
  const parsed = parseMoneyInput(value)
  if (parsed === null) {
    return null
  }
  return Math.round(parsed * 100)
}

const inputFromCents = (value) => {
  if (value === null || value === undefined) {
    return ''
  }
  return (Number(value) / 100).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

export default {
  name: 'Products',
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
        name: '',
        description: '',
        unitPrice: '',
      },
      fieldErrors: {},
    }
  },
  computed: {
    canSave() {
      const cents = centsFromInput(this.form.unitPrice)
      return this.form.name.trim() !== '' && cents !== null && cents >= 0
    },
    filteredItems() {
      const query = this.query.trim().toLowerCase()
      if (!query) {
        return this.items
      }
      return this.items.filter((item) => {
        const haystack = [item.name, item.description]
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
    async load() {
      this.loading = true
      this.error = ''
      try {
        const data = await getProducts()
        this.items = Array.isArray(data) ? data : []
      } catch (e) {
        this.error = 'Produkte konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    resetForm() {
      this.editingId = null
      this.form = {
        name: '',
        description: '',
        unitPrice: '',
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
        name: item.name,
        description: item.description || '',
        unitPrice: inputFromCents(item.unitPriceCents),
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
        const errors = {}
        if (!this.form.name.trim()) {
          errors.name = 'Bitte einen Namen angeben.'
        }
        if (centsFromInput(this.form.unitPrice) === null) {
          errors.unitPrice = 'Bitte einen gültigen Stückpreis angeben.'
        }
        this.fieldErrors = errors
        return
      }

      const cents = centsFromInput(this.form.unitPrice)
      if (cents === null || cents < 0) {
        this.fieldErrors = { unitPrice: 'Stückpreis muss eine Zahl >= 0 sein.' }
        return
      }

      this.saving = true
      this.saved = false
      this.error = ''

      const payload = {
        name: this.form.name.trim(),
        description: this.form.description.trim(),
        unitPriceCents: cents,
      }

      try {
        if (this.editingId) {
          const saved = await updateProduct(this.editingId, payload)
          this.items = this.items.map((item) =>
            item.id === this.editingId ? saved : item
          )
        } else {
          const saved = await createProduct(payload)
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
      if (!window.confirm('Produkt/DL wirklich löschen?')) {
        return
      }
      this.saving = true
      this.error = ''
      try {
        await deleteProduct(item.id)
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
    formatPrice(value) {
      if (value === null || value === undefined || value === '') {
        return '–'
      }
      const amount = Number(value)
      if (Number.isNaN(amount)) {
        return String(value)
      }
      return (amount / 100).toLocaleString('de-DE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    },
  },
}
</script>

<style scoped>
.products {
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

.table th.price,
.table td.price {
  text-align: right;
  white-space: nowrap;
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
