<template>
  <section class="fiscal-year">
    <div class="page-header">
      <h1>Wirtschaftsjahr</h1>
      <NcButton v-if="standalone" type="secondary" @click="goToOverview">
        Übersicht
      </NcButton>
    </div>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="layout">
      <div v-if="!standalone" class="list">
        <div class="list-header">
          <h2>Übersicht</h2>
          <NcButton type="primary" @click="openCreateModal">
            Neues Wirtschaftsjahr
          </NcButton>
        </div>

        <div class="list-filters">
          <NcTextField
            label="Suche"
            placeholder="Name oder Zeitraum…"
            :value.sync="query"
          />
        </div>

        <NcEmptyContent
          v-if="filteredItems.length === 0"
          name="Noch kein Wirtschaftsjahr"
          description="Lege ein Wirtschaftsjahr an, um Einnahmen und Ausgaben zu verwalten."
        />

        <table v-else class="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Zeitraum</th>
              <th>Status</th>
              <th class="actions">Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in filteredItems" :key="item.id">
              <td class="name">
                <button
                  class="link"
                  type="button"
                  @click="openYearDetail(item)"
                >
                  {{ item.name }}
                </button>
              </td>
              <td>{{ formatRange(item.dateStart, item.dateEnd) }}</td>
              <td>
                <span v-if="item.isActive" class="status active">Aktiv</span>
                <span v-else class="status inactive">Inaktiv</span>
              </td>
              <td class="actions">
                <NcButton
                  type="tertiary"
                  @click="openYearDetail(item)"
                >
                  Öffnen
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  aria-label="Details anzeigen"
                  title="Details"
                  @click="openYearDetail(item)"
                >
                  <template #icon>
                    <FileDocumentOutline :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  aria-label="Wirtschaftsjahr bearbeiten"
                  title="Bearbeiten"
                  @click="editItem(item)"
                >
                  <template #icon>
                    <Pencil :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  aria-label="Wirtschaftsjahr löschen"
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
      </div>

      <div v-if="standalone && selectedYear" class="detail">
        <div class="detail-header">
          <div>
            <h2>{{ selectedYear.name }}</h2>
            <p class="subline">{{ formatRange(selectedYear.dateStart, selectedYear.dateEnd) }}</p>
          </div>
          <NcButton type="secondary" @click="downloadGubPdf">
            GÜB als PDF exportieren
          </NcButton>
        </div>

        <div class="gub-summary">
          <div>
            <p>Einnahmen: {{ formatMoney(incomeTotalCents) }}</p>
            <p>Ausgaben: {{ formatMoney(expenseTotalCents) }}</p>
          </div>
          <div class="profit">
            Gewinn/Überschuss: {{ formatMoney(profitCents) }}
          </div>
        </div>

        <div class="detail-grid">
          <div class="panel">
            <div class="panel-header">
              <h3>Einnahmen</h3>
              <NcButton type="secondary" @click="resetIncomeForm">
                Neue Einnahme
              </NcButton>
            </div>
            <NcEmptyContent
              v-if="incomes.length === 0"
              name="Keine Einnahmen"
              description="Rechnungen werden automatisch als Einnahmen erfasst."
            />
            <table v-else class="table compact">
              <thead>
                <tr>
                  <th>Beschreibung</th>
                  <th>Datum</th>
                  <th class="price">Betrag</th>
                  <th>Status</th>
                  <th class="actions">Aktionen</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="income in incomes" :key="income.id">
                  <td class="name">{{ income.name || income.description || 'Einnahme' }}</td>
                  <td>{{ formatDate(income.bookedAt) }}</td>
                  <td class="price">{{ formatMoney(income.amountCents) }}</td>
                  <td>{{ incomeStatusLabel(income.status) }}</td>
                  <td class="actions">
                    <NcButton
                      v-if="income.invoiceId"
                      type="tertiary-no-background"
                      aria-label="Einnahme als bezahlt markieren"
                      title="Als bezahlt markieren"
                      :disabled="income.status === 'paid'"
                      @click="markIncomePaid(income)"
                    >
                      <template #icon>
                        <CheckCircleOutline :size="18" />
                      </template>
                    </NcButton>
                    <NcButton
                      v-if="!income.invoiceId"
                      type="tertiary-no-background"
                      aria-label="Einnahme bearbeiten"
                      title="Bearbeiten"
                      @click="editIncome(income)"
                    >
                      <template #icon>
                        <Pencil :size="18" />
                      </template>
                    </NcButton>
                    <NcButton
                      v-if="!income.invoiceId"
                      type="tertiary-no-background"
                      aria-label="Einnahme löschen"
                      title="Löschen"
                      @click="removeIncome(income)"
                    >
                      <template #icon>
                        <TrashCanOutline :size="18" />
                      </template>
                    </NcButton>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="incomeError" class="error">{{ incomeError }}</p>
          </div>

          <div class="panel">
            <div class="panel-header">
              <h3>Ausgaben</h3>
              <NcButton type="secondary" @click="resetExpenseForm">
                Neue Ausgabe
              </NcButton>
            </div>

            <NcEmptyContent
              v-if="expenses.length === 0"
              name="Keine Ausgaben"
              description="Lege eine Ausgabe an."
            />
            <table v-else class="table compact">
              <thead>
                <tr>
                  <th>Ausgabe</th>
                  <th>Datum</th>
                  <th class="price">Betrag</th>
                  <th class="actions">Aktionen</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="expense in expenses" :key="expense.id">
                  <td class="name">{{ expense.name || 'Ausgabe' }}</td>
                  <td>{{ formatDate(expense.bookedAt) }}</td>
                  <td class="price">{{ formatMoney(expense.amountCents) }}</td>
                  <td class="actions">
                    <NcButton
                      type="tertiary-no-background"
                      aria-label="Ausgabe bearbeiten"
                      title="Bearbeiten"
                      @click="editExpense(expense)"
                    >
                      <template #icon>
                        <Pencil :size="18" />
                      </template>
                    </NcButton>
                    <NcButton
                      type="tertiary-no-background"
                      aria-label="Ausgabe löschen"
                      title="Löschen"
                      @click="removeExpense(expense)"
                    >
                      <template #icon>
                        <TrashCanOutline :size="18" />
                      </template>
                    </NcButton>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="expenseError" class="error">{{ expenseError }}</p>
          </div>
        </div>

      </div>

    </div>

    <NcModal v-if="showModal" size="normal" @close="closeModal">
      <div class="modal__content">
        <h2>{{ editingId ? 'Wirtschaftsjahr bearbeiten' : 'Neues Wirtschaftsjahr' }}</h2>

        <div class="form-group">
          <NcTextField label="Name *" :value.sync="form.name" />
          <p v-if="yearFieldErrors.name" class="field-error">{{ yearFieldErrors.name }}</p>
        </div>
        <div class="form-group">
          <NcDateTimePickerNative
            label="Datum von *"
            type="date"
            id="fiscal-year-date-start"
            :value="form.dateStart"
            @input="form.dateStart = $event"
          />
          <p v-if="yearFieldErrors.dateStart" class="field-error">{{ yearFieldErrors.dateStart }}</p>
        </div>
        <div class="form-group">
          <NcDateTimePickerNative
            label="Datum bis *"
            type="date"
            id="fiscal-year-date-end"
            :value="form.dateEnd"
            @input="form.dateEnd = $event"
          />
          <p v-if="yearFieldErrors.dateEnd" class="field-error">{{ yearFieldErrors.dateEnd }}</p>
        </div>
        <div class="form-group">
          <NcCheckboxRadioSwitch
            type="switch"
            :checked.sync="form.isActive"
          >
            Aktives Wirtschaftsjahr
          </NcCheckboxRadioSwitch>
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

    <NcModal v-if="showExpenseModal" size="normal" @close="closeExpenseModal">
      <div class="modal__content">
        <h2>{{ editingExpenseId ? 'Ausgabe bearbeiten' : 'Neue Ausgabe' }}</h2>
        <p class="subline" v-if="selectedYear">
          Wirtschaftsjahr: {{ selectedYear.name }}
        </p>

        <div class="form-stack">
          <div class="form-group">
            <NcTextField label="Name *" :value.sync="expenseForm.name" />
            <p v-if="expenseFieldErrors.name" class="field-error">{{ expenseFieldErrors.name }}</p>
          </div>
          <div class="form-group">
            <NcDateTimePickerNative
              label="Datum *"
              type="date"
              id="fiscal-year-expense-date"
              :value="expenseForm.bookedAt"
              @input="expenseForm.bookedAt = $event"
            />
            <p v-if="expenseFieldErrors.bookedAt" class="field-error">{{ expenseFieldErrors.bookedAt }}</p>
          </div>
          <div class="form-group">
            <NcTextField
              label="Betrag *"
              type="text"
              placeholder="0.00"
              :value.sync="expenseForm.amount"
            />
            <p v-if="expenseFieldErrors.amount" class="field-error">{{ expenseFieldErrors.amount }}</p>
          </div>
          <div class="form-group">
            <NcTextArea label="Beschreibung" :value.sync="expenseForm.description" />
          </div>
        </div>
        <div class="actions">
          <NcButton type="primary" :disabled="savingExpense || !canSaveExpense" @click="saveExpense">
            {{ editingExpenseId ? 'Aktualisieren' : 'Anlegen' }}
          </NcButton>
          <NcButton type="secondary" @click="closeExpenseModal">Abbrechen</NcButton>
          <span v-if="savingExpense" class="hint">Speichere…</span>
          <span v-if="savedExpense" class="success">Gespeichert</span>
          <span v-if="expenseError" class="error">{{ expenseError }}</span>
        </div>
      </div>
    </NcModal>

    <NcModal v-if="showIncomeModal" size="normal" @close="closeIncomeModal">
      <div class="modal__content">
        <h2>{{ editingIncomeId ? 'Einnahme bearbeiten' : 'Neue Einnahme' }}</h2>
        <p class="subline" v-if="selectedYear">
          Wirtschaftsjahr: {{ selectedYear.name }}
        </p>

        <div class="form-stack">
          <div class="form-group">
            <NcTextField label="Name *" :value.sync="incomeForm.name" />
            <p v-if="incomeFieldErrors.name" class="field-error">
              {{ incomeFieldErrors.name }}
            </p>
          </div>
          <div class="form-group">
            <NcDateTimePickerNative
              label="Datum *"
              type="date"
              id="fiscal-year-income-date"
              :value="incomeForm.bookedAt"
              @input="incomeForm.bookedAt = $event"
            />
            <p v-if="incomeFieldErrors.bookedAt" class="field-error">
              {{ incomeFieldErrors.bookedAt }}
            </p>
          </div>
          <div class="form-group">
            <NcTextField
              label="Betrag *"
              type="text"
              placeholder="0.00"
              :value.sync="incomeForm.amount"
            />
            <p v-if="incomeFieldErrors.amount" class="field-error">
              {{ incomeFieldErrors.amount }}
            </p>
          </div>
          <div class="form-group">
            <NcTextArea label="Beschreibung" :value.sync="incomeForm.description" />
          </div>
        </div>
        <div class="actions">
          <NcButton type="primary" :disabled="savingIncome || !canSaveIncome" @click="saveIncome">
            {{ editingIncomeId ? 'Aktualisieren' : 'Anlegen' }}
          </NcButton>
          <NcButton type="secondary" @click="closeIncomeModal">Abbrechen</NcButton>
          <span v-if="savingIncome" class="hint">Speichere…</span>
          <span v-if="savedIncome" class="success">Gespeichert</span>
          <span v-if="incomeError" class="error">{{ incomeError }}</span>
        </div>
      </div>
    </NcModal>
  </section>
</template>

<script>
import {
  NcButton,
  NcCheckboxRadioSwitch,
  NcEmptyContent,
  NcLoadingIcon,
  NcModal,
} from '@nextcloud/vue'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.mjs'
import NcDateTimePickerNative from '@nextcloud/vue/dist/Components/NcDateTimePickerNative.mjs'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import {
  createFiscalYear,
  deleteFiscalYear,
  getFiscalYears,
  getGubPdfUrl,
  updateFiscalYear,
} from '../api/fiscalYears'
import { createIncome, deleteIncome, getIncomes, updateIncome } from '../api/incomes'
import { createExpense, deleteExpense, getExpenses, updateExpense } from '../api/expenses'
import { getInvoice, updateInvoice } from '../api/invoices'

const toTimestamp = (value) => {
  if (!value) {
    return null
  }
  if (value instanceof Date) {
    const time = value.getTime()
    if (Number.isNaN(time)) {
      return null
    }
    return Math.floor(time / 1000)
  }
  if (typeof value === 'string') {
    const date = new Date(`${value}T00:00:00`)
    const time = date.getTime()
    if (Number.isNaN(time)) {
      return null
    }
    return Math.floor(time / 1000)
  }
  return null
}

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

const inputFromCents = (value) => {
  if (value === null || value === undefined) {
    return ''
  }
  return (Number(value) / 100).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const toDateFromTimestamp = (value) => {
  if (!value) {
    return null
  }
  const date = new Date(Number(value) * 1000)
  if (Number.isNaN(date.getTime())) {
    return null
  }
  return date
}

const todayDate = () => {
  const now = new Date()
  return new Date(now.getFullYear(), now.getMonth(), now.getDate())
}

export default {
  name: 'FiscalYear',
  props: {
    standalone: {
      type: Boolean,
      default: false,
    },
    focusYearId: {
      type: [String, Number],
      default: null,
    },
  },
  components: {
    NcButton,
    NcCheckboxRadioSwitch,
    NcEmptyContent,
    NcLoadingIcon,
    NcModal,
    NcDateTimePickerNative,
    NcTextArea,
    NcTextField,
    CheckCircleOutline,
    FileDocumentOutline,
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
      selectedYearId: null,
      incomes: [],
      incomeError: '',
      savingIncome: false,
      savedIncome: false,
      editingIncomeId: null,
      showIncomeModal: false,
      expenses: [],
      expenseError: '',
      savingExpense: false,
      savedExpense: false,
      editingExpenseId: null,
      showExpenseModal: false,
      editingId: null,
      showModal: false,
      form: {
        name: '',
        dateStart: null,
        dateEnd: null,
        isActive: false,
      },
      yearFieldErrors: {},
      expenseForm: {
        name: '',
        description: '',
        bookedAt: null,
        amount: '',
      },
      expenseFieldErrors: {},
      incomeForm: {
        name: '',
        description: '',
        bookedAt: null,
        amount: '',
      },
      incomeFieldErrors: {},
    }
  },
  computed: {
    canSave() {
      return (
        this.form.name.trim() !== '' &&
        this.form.dateStart instanceof Date &&
        this.form.dateEnd instanceof Date
      )
    },
    filteredItems() {
      const query = this.query.trim().toLowerCase()
      if (!query) {
        return this.items
      }
      return this.items.filter((item) => {
        const range = this.formatRange(item.dateStart, item.dateEnd)
        const haystack = [item.name, range].filter(Boolean).join(' ').toLowerCase()
        return haystack.includes(query)
      })
    },
    selectedYear() {
      return this.items.find((item) => item.id === this.selectedYearId) || null
    },
    incomeTotalCents() {
      return this.incomes.reduce((sum, income) => sum + (income.amountCents || 0), 0)
    },
    expenseTotalCents() {
      return this.expenses.reduce((sum, expense) => sum + (expense.amountCents || 0), 0)
    },
    profitCents() {
      return this.incomeTotalCents - this.expenseTotalCents
    },
    canSaveExpense() {
      return (
        this.expenseForm.name.trim() !== '' &&
        this.expenseForm.bookedAt instanceof Date &&
        this.expenseForm.amount !== ''
      )
    },
    canSaveIncome() {
      return (
        this.incomeForm.name.trim() !== '' &&
        this.incomeForm.bookedAt instanceof Date &&
        this.incomeForm.amount !== ''
      )
    },
  },
  watch: {
    focusYearId() {
      if (!this.standalone || !this.focusYearId) {
        return
      }
      this.selectedYearId = Number(this.focusYearId)
      if (this.selectedYearId) {
        this.loadYearData(this.selectedYearId)
      }
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
        const data = await getFiscalYears()
        this.items = Array.isArray(data) ? data : []
        if (this.standalone && this.focusYearId) {
          this.selectedYearId = Number(this.focusYearId)
        } else if (!this.selectedYearId) {
          const active = this.items.find((item) => item.isActive)
          if (active) {
            this.selectedYearId = active.id
          } else if (this.items.length > 0) {
            this.selectedYearId = this.items[0].id
          }
        }
        if (this.standalone && this.selectedYearId) {
          await this.loadYearData(this.selectedYearId)
        }
      } catch (e) {
        this.error = 'Wirtschaftsjahre konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    resetForm() {
      this.editingId = null
      this.form = {
        name: '',
        dateStart: null,
        dateEnd: null,
        isActive: false,
      }
      this.yearFieldErrors = {}
      this.error = ''
    },
    openYearDetail(item) {
      if (!item?.id) {
        return
      }
      this.$router.push({ name: 'fiscal-year-detail', params: { id: item.id } })
    },
    goToOverview() {
      this.$router.push({ name: 'fiscal-year' })
    },
    async loadYearData(yearId) {
      await Promise.all([this.loadIncomes(yearId), this.loadExpenses(yearId)])
    },
    async loadIncomes(yearId) {
      this.incomeError = ''
      try {
        const data = await getIncomes(yearId)
        this.incomes = Array.isArray(data) ? data : []
      } catch (e) {
        this.incomeError = 'Einnahmen konnten nicht geladen werden.'
      }
    },
    async loadExpenses(yearId) {
      this.expenseError = ''
      try {
        const data = await getExpenses(yearId)
        this.expenses = Array.isArray(data) ? data : []
      } catch (e) {
        this.expenseError = 'Ausgaben konnten nicht geladen werden.'
      }
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
        dateStart: toDateFromTimestamp(item.dateStart),
        dateEnd: toDateFromTimestamp(item.dateEnd),
        isActive: Boolean(item.isActive),
      }
      this.error = ''
      this.showModal = true
    },
    resetExpenseForm() {
      this.editingExpenseId = null
      this.showExpenseModal = true
      this.expenseForm = {
        name: '',
        description: '',
        bookedAt: todayDate(),
        amount: '',
      }
      this.expenseFieldErrors = {}
      this.expenseError = ''
      this.savedExpense = false
    },
    resetIncomeForm() {
      this.editingIncomeId = null
      this.showIncomeModal = true
      this.incomeForm = {
        name: '',
        description: '',
        bookedAt: todayDate(),
        amount: '',
      }
      this.incomeFieldErrors = {}
      this.incomeError = ''
      this.savedIncome = false
    },
    closeExpenseModal() {
      this.showExpenseModal = false
      this.editingExpenseId = null
      this.expenseForm = {
        name: '',
        description: '',
        bookedAt: null,
        amount: '',
      }
      this.expenseFieldErrors = {}
      this.expenseError = ''
    },
    closeIncomeModal() {
      this.showIncomeModal = false
      this.editingIncomeId = null
      this.incomeForm = {
        name: '',
        description: '',
        bookedAt: null,
        amount: '',
      }
      this.incomeFieldErrors = {}
      this.incomeError = ''
    },
    editExpense(expense) {
      this.editingExpenseId = expense.id
      this.showExpenseModal = true
      this.expenseForm = {
        name: expense.name || '',
        description: expense.description || '',
        bookedAt: toDateFromTimestamp(expense.bookedAt),
        amount: inputFromCents(expense.amountCents),
      }
      this.expenseFieldErrors = {}
      this.expenseError = ''
    },
    editIncome(income) {
      if (income.invoiceId) {
        return
      }
      this.editingIncomeId = income.id
      this.showIncomeModal = true
      this.incomeForm = {
        name: income.name || '',
        description: income.description || '',
        bookedAt: toDateFromTimestamp(income.bookedAt),
        amount: inputFromCents(income.amountCents),
      }
      this.incomeFieldErrors = {}
      this.incomeError = ''
    },
    async saveExpense() {
      if (!this.selectedYearId) {
        this.expenseError = 'Bitte ein Wirtschaftsjahr auswählen.'
        return
      }
      if (!this.canSaveExpense) {
        this.expenseFieldErrors = {}
        if (!this.expenseForm.name.trim()) {
          this.expenseFieldErrors.name = 'Bitte einen Namen angeben.'
        }
        if (!this.expenseForm.bookedAt) {
          this.expenseFieldErrors.bookedAt = 'Bitte ein Datum angeben.'
        }
        if (this.expenseForm.amount === '') {
          this.expenseFieldErrors.amount = 'Bitte einen Betrag angeben.'
        }
        return
      }

      const bookedAt = toTimestamp(this.expenseForm.bookedAt)
      if (bookedAt === null) {
        this.expenseFieldErrors = { bookedAt: 'Bitte ein gültiges Datum angeben.' }
        return
      }

      const amount = parseMoneyInput(this.expenseForm.amount)
      if (amount === null) {
        this.expenseFieldErrors = { amount: 'Bitte einen gültigen Betrag angeben.' }
        return
      }

      this.savingExpense = true
      this.savedExpense = false
      this.expenseError = ''

      const payload = {
        name: this.expenseForm.name.trim(),
        description: this.expenseForm.description.trim(),
        bookedAt,
        amountCents: Math.round(amount * 100),
      }

      try {
        if (this.editingExpenseId) {
          const saved = await updateExpense(this.editingExpenseId, payload)
          this.expenses = this.expenses.map((item) =>
            item.id === this.editingExpenseId ? saved : item
          )
        } else {
          const saved = await createExpense(this.selectedYearId, payload)
          this.expenses = [saved, ...this.expenses]
        }
        this.savedExpense = true
        window.setTimeout(() => {
          this.savedExpense = false
        }, 2000)
        this.closeExpenseModal()
      } catch (e) {
        this.expenseError = 'Ausgabe konnte nicht gespeichert werden.'
      } finally {
        this.savingExpense = false
      }
    },
    async saveIncome() {
      if (!this.selectedYearId) {
        this.incomeError = 'Bitte ein Wirtschaftsjahr auswählen.'
        return
      }
      if (!this.canSaveIncome) {
        this.incomeFieldErrors = {}
        if (!this.incomeForm.name.trim()) {
          this.incomeFieldErrors.name = 'Bitte einen Namen angeben.'
        }
        if (!this.incomeForm.bookedAt) {
          this.incomeFieldErrors.bookedAt = 'Bitte ein Datum angeben.'
        }
        if (this.incomeForm.amount === '') {
          this.incomeFieldErrors.amount = 'Bitte einen Betrag angeben.'
        }
        return
      }

      const bookedAt = toTimestamp(this.incomeForm.bookedAt)
      if (bookedAt === null) {
        this.incomeFieldErrors = { bookedAt: 'Bitte ein gültiges Datum angeben.' }
        return
      }

      const amount = parseMoneyInput(this.incomeForm.amount)
      if (amount === null) {
        this.incomeFieldErrors = { amount: 'Bitte einen gültigen Betrag angeben.' }
        return
      }

      this.savingIncome = true
      this.savedIncome = false
      this.incomeError = ''

      const payload = {
        name: this.incomeForm.name.trim(),
        description: this.incomeForm.description.trim(),
        bookedAt,
        amountCents: Math.round(amount * 100),
        status: 'paid',
      }

      try {
        if (this.editingIncomeId) {
          const saved = await updateIncome(this.editingIncomeId, payload)
          this.incomes = this.incomes.map((item) =>
            item.id === this.editingIncomeId ? saved : item
          )
        } else {
          const saved = await createIncome(this.selectedYearId, payload)
          this.incomes = [saved, ...this.incomes]
        }
        this.savedIncome = true
        window.setTimeout(() => {
          this.savedIncome = false
        }, 2000)
        this.closeIncomeModal()
      } catch (e) {
        this.incomeError = 'Einnahme konnte nicht gespeichert werden.'
      } finally {
        this.savingIncome = false
      }
    },
    async removeExpense(expense) {
      if (!window.confirm('Ausgabe wirklich löschen?')) {
        return
      }
      try {
        await deleteExpense(expense.id)
        this.expenses = this.expenses.filter((item) => item.id !== expense.id)
      } catch (e) {
        this.expenseError = 'Ausgabe konnte nicht gelöscht werden.'
      }
    },
    async removeIncome(income) {
      if (income.invoiceId) {
        return
      }
      if (!window.confirm('Einnahme wirklich löschen?')) {
        return
      }
      try {
        await deleteIncome(income.id)
        this.incomes = this.incomes.filter((item) => item.id !== income.id)
      } catch (e) {
        this.incomeError = 'Einnahme konnte nicht gelöscht werden.'
      }
    },
    async markIncomePaid(income) {
      if (!income.invoiceId) {
        return
      }
      try {
        const invoice = await getInvoice(income.invoiceId)
        if (!invoice) {
          return
        }
        const payload = {
          number: invoice.number || null,
          status: 'paid',
          caseId: invoice.caseId,
          customerId: invoice.customerId,
          invoiceType: invoice.invoiceType || 'standard',
          relatedOfferId: invoice.relatedOfferId || null,
          servicePeriodStart: invoice.servicePeriodStart || null,
          servicePeriodEnd: invoice.servicePeriodEnd || null,
          issueDate: invoice.issueDate,
          dueDate: invoice.dueDate,
          greetingText: invoice.greetingText || null,
          extraText: invoice.extraText || null,
          footerText: invoice.footerText || null,
          subtotalCents: invoice.subtotalCents,
          taxCents: invoice.taxCents,
          totalCents: invoice.totalCents,
          taxRateBp: invoice.taxRateBp,
          isSmallBusiness: invoice.isSmallBusiness,
        }
        await updateInvoice(income.invoiceId, payload)
        this.incomes = this.incomes.map((item) =>
          item.id === income.id ? { ...item, status: 'paid' } : item
        )
      } catch (e) {
        this.incomeError = 'Status konnte nicht aktualisiert werden.'
      }
    },
    async save() {
      this.yearFieldErrors = {}
      if (!this.canSave) {
        this.yearFieldErrors = {
          name: this.form.name.trim() ? '' : 'Bitte einen Namen angeben.',
          dateStart: this.form.dateStart ? '' : 'Bitte ein Startdatum angeben.',
          dateEnd: this.form.dateEnd ? '' : 'Bitte ein Enddatum angeben.',
        }
        return
      }

      const start = toTimestamp(this.form.dateStart)
      const end = toTimestamp(this.form.dateEnd)

      if (start === null || end === null) {
        this.error = 'Bitte gültige Datumswerte angeben.'
        return
      }

      if (start > end) {
        this.yearFieldErrors = {
          dateStart: 'Startdatum muss vor Enddatum liegen.',
          dateEnd: 'Enddatum muss nach Startdatum liegen.',
        }
        return
      }

      this.saving = true
      this.saved = false
      this.error = ''

      const payload = {
        name: this.form.name.trim(),
        dateStart: start,
        dateEnd: end,
        isActive: this.form.isActive,
      }

      try {
        if (this.editingId) {
          const saved = await updateFiscalYear(this.editingId, payload)
          this.items = this.items.map((item) =>
            item.id === this.editingId ? saved : item
          )
        } else {
          const saved = await createFiscalYear(payload)
          this.items = [...this.items, saved]
          this.resetForm()
        }

        if (payload.isActive) {
          await this.load()
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
      if (!window.confirm('Wirtschaftsjahr wirklich löschen?')) {
        return
      }
      this.saving = true
      this.error = ''
      try {
        await deleteFiscalYear(item.id)
        this.items = this.items.filter((entry) => entry.id !== item.id)
        if (this.editingId === item.id) {
          this.resetForm()
        }
        if (this.showModal) {
          this.closeModal()
        }
        if (this.selectedYearId === item.id) {
          this.selectedYearId = this.items[0]?.id || null
          if (this.standalone && this.selectedYearId) {
            await this.loadYearData(this.selectedYearId)
          } else {
            this.incomes = []
            this.expenses = []
          }
        }
      } catch (e) {
        this.error = 'Löschen fehlgeschlagen.'
      } finally {
        this.saving = false
      }
    },
    downloadGubPdf() {
      if (!this.selectedYearId) {
        return
      }
      const url = getGubPdfUrl(this.selectedYearId)
      window.open(url, '_blank')
    },
    formatRange(start, end) {
      if (!start || !end) {
        return '–'
      }
      const startDate = new Date(Number(start) * 1000)
      const endDate = new Date(Number(end) * 1000)
      if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
        return '–'
      }
      const startLabel = startDate.toLocaleDateString('de-DE')
      const endLabel = endDate.toLocaleDateString('de-DE')
      return `${startLabel} – ${endLabel}`
    },
    formatDate(value) {
      if (!value) {
        return '–'
      }
      const date = new Date(Number(value) * 1000)
      if (Number.isNaN(date.getTime())) {
        return '–'
      }
      return date.toLocaleDateString('de-DE')
    },
    formatMoney(value) {
      if (value === null || value === undefined) {
        return '–'
      }
      return `${(Number(value) / 100).toLocaleString('de-DE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })} €`
    },
    incomeStatusLabel(status) {
      if (status === 'paid') {
        return 'Bezahlt'
      }
      if (status === 'open') {
        return 'Offen'
      }
      return status || '–'
    },
  },
}
</script>

<style scoped>
.fiscal-year {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.page-header h1 {
  margin: 0;
  padding-left: 28px;
}

.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 24px;
}

.list {
  background: var(--color-main-background, #fff);
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 12px;
  padding: 16px;
}

.detail {
  background: var(--color-main-background, #fff);
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.subline {
  margin: 4px 0 0;
  color: var(--color-text-lighter, #6b7280);
}

.gub-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 16px;
  background: var(--color-background-dark, #f3f4f6);
  border-radius: 8px;
}

.gub-summary .profit {
  font-weight: 600;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.panel {
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 12px;
  background: var(--color-main-background, #fff);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.form-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.list-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.list-filters {
  margin-bottom: 12px;
  max-width: 420px;
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.table th,
.table td {
  text-align: left;
  padding: 10px 8px;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
}

.table.compact th,
.table.compact td {
  padding: 8px 6px;
}

.table th.price,
.table td.price {
  text-align: right;
  white-space: nowrap;
}

.link {
  background: none;
  border: none;
  padding: 0;
  color: var(--color-primary, #1a73e8);
  cursor: pointer;
  font-weight: 600;
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

.status {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.01em;
  border: 1px solid var(--color-border, #e5e7eb);
  background: var(--color-background-dark, #f3f4f6);
  color: var(--color-text-maxcontrast, #111827);
}

.status.active {
  border-color: var(--color-success, #2d9a4f);
  background: color-mix(in srgb, var(--color-success, #2d9a4f) 18%, var(--color-main-background, #fff));
  color: var(--color-text-maxcontrast, #0b3d1f);
}

.status.inactive {
  border-color: var(--color-border, #e5e7eb);
  background: var(--color-background-dark, #f3f4f6);
  color: var(--color-text-lighter, #6b7280);
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

.form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px 16px;
}

.form-grid .form-group {
  margin: 0;
}

.form-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.form-stack .form-group {
  margin: 0;
}

@media (max-width: 900px) {
  .layout {
    grid-template-columns: 1fr;
  }

  .detail-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .gub-summary {
    flex-direction: column;
    align-items: flex-start;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
