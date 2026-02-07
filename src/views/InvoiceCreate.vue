<template>
  <section class="invoice-create">
    <div class="header">
      <div>
        <h1>Neue Rechnung</h1>
        <p class="subline">Erstelle eine Rechnung mit Positionen und Steuerlogik.</p>
      </div>
      <NcButton type="secondary" @click="goBack">Zur Übersicht</NcButton>
    </div>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="content">
      <div v-if="!hasActiveFiscalYear" class="notice error">
        Es ist kein aktives Wirtschaftsjahr vorhanden. Bitte lege zuerst ein aktives
        Wirtschaftsjahr an, bevor du eine Rechnung erstellst.
      </div>

      <div class="section-header">
        <button class="toggle" type="button" @click="toggleGeneral">
          <span>Allgemeine Einstellungen</span>
          <span class="toggle__state">{{ showGeneral ? 'Ausblenden' : 'Einblenden' }}</span>
        </button>
      </div>

      <div v-if="showGeneral" class="general">
        <div v-if="offerOptions.length" class="form-group">
          <NcSelect
            id="invoiceOffer"
            v-model="selectedOfferId"
            :options="offerOptions"
            :reduce="(option) => option.value"
            :append-to-body="false"
            :clearable="true"
            input-label="Aus Angebot übernehmen"
            :label-outside="true"
            placeholder="Optional"
            @input="applyOffer"
          />
        </div>

        <div class="form-grid">
          <div class="form-group">
            <NcTextField
              label="Rechnungsnummer"
              value="Wird bei Speicherung vergeben"
              :disabled="true"
            />
          </div>
          <div class="form-group">
            <NcSelect
              id="invoiceCustomer"
              v-model="form.customerId"
              :options="customerOptions"
              :reduce="(option) => option.value"
              :append-to-body="false"
              :clearable="false"
              input-label="Kunde *"
              :label-outside="true"
              placeholder="Bitte auswählen"
            />
            <p v-if="fieldErrors.customerId" class="field-error">{{ fieldErrors.customerId }}</p>
          </div>
          <div class="form-group">
            <NcSelect
              id="invoiceCase"
              v-model="form.caseId"
              :options="caseOptions"
              :reduce="(option) => option.value"
              :append-to-body="false"
              :clearable="false"
              input-label="Vorgang *"
              :label-outside="true"
              placeholder="Bitte auswählen"
            />
            <p v-if="fieldErrors.caseId" class="field-error">{{ fieldErrors.caseId }}</p>
          </div>
          <div class="form-group">
            <NcTextField
              label="Ausstellungsdatum *"
              type="text"
              placeholder="YYYY-MM-DD"
              :value.sync="form.issueDate"
            />
            <p v-if="fieldErrors.issueDate" class="field-error">{{ fieldErrors.issueDate }}</p>
          </div>
          <div class="form-group">
            <NcTextField
              label="Fällig bis"
              type="text"
              placeholder="YYYY-MM-DD"
              :value.sync="form.dueDate"
            />
            <p v-if="fieldErrors.dueDate" class="field-error">{{ fieldErrors.dueDate }}</p>
          </div>
        </div>

        <div class="form-group">
          <NcTextArea label="Begrüßungstext" :value.sync="form.greetingText" />
        </div>
        <div class="form-group">
          <NcTextArea label="Zusatztext" :value.sync="form.extraText" />
        </div>
        <div class="form-group">
          <NcTextArea label="Footer-Text" :value.sync="form.footerText" />
        </div>
      </div>

      <hr class="divider" />

      <div class="positions">
        <div class="list-header">
          <h3>Positionen</h3>
          <NcButton type="secondary" @click="addItem">Position hinzufügen</NcButton>
        </div>

        <table class="table compact">
          <thead>
            <tr>
              <th>Typ</th>
              <th>Produkt</th>
              <th>Bezeichnung</th>
              <th>Menge</th>
              <th class="price">Einzelpreis</th>
              <th class="price">Gesamt</th>
              <th class="actions">Aktion</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in form.items" :key="item.key">
              <td>
                <NcSelect
                  v-model="item.positionType"
                  :options="positionTypeOptions"
                  :reduce="(option) => option.value"
                  :append-to-body="false"
                  :clearable="false"
                  input-label="Typ"
                  :label-outside="true"
                />
              </td>
              <td>
                <NcSelect
                  v-if="item.positionType === 'product'"
                  v-model="item.productId"
                  :options="productOptions"
                  :reduce="(option) => option.value"
                  :append-to-body="false"
                  :clearable="true"
                  input-label="Produkt"
                  :label-outside="true"
                  placeholder="Produkt"
                  @input="syncFromProduct(item)"
                />
                <span v-else>–</span>
              </td>
              <td>
                <NcTextField
                  v-if="item.positionType === 'custom'"
                  label="Bezeichnung"
                  placeholder="Bezeichnung"
                  :value.sync="item.name"
                />
                <span v-else>{{ item.name || '–' }}</span>
              </td>
              <td>
                <NcTextField
                  label="Menge *"
                  type="text"
                  placeholder="1"
                  :value.sync="item.quantity"
                />
              </td>
              <td class="price">
                <NcTextField
                  label="Einzelpreis *"
                  type="text"
                  placeholder="0.00"
                  :value.sync="item.unitPrice"
                />
              </td>
              <td class="price">{{ formatPrice(itemTotalCents(item)) }}</td>
              <td class="actions">
                <NcButton
                  type="tertiary-no-background"
                  aria-label="Position entfernen"
                  title="Entfernen"
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

      <div class="summary">
        <div>
          <p>Zwischensumme: {{ formatPrice(subtotalCents) }}</p>
          <p v-if="form.isSmallBusiness">
            {{ smallBusinessNote }}
          </p>
          <p v-else>
            Steuer ({{ formatTaxRate(form.taxRateBp) }}): {{ formatPrice(taxCents) }}
          </p>
        </div>
        <div class="total">
          Gesamt: {{ formatPrice(totalCents) }}
        </div>
      </div>

      <hr class="divider" />

      <div class="actions">
        <NcButton type="primary" :disabled="saving || !canSave || !hasActiveFiscalYear" @click="save">
          Rechnung anlegen
        </NcButton>
        <NcButton type="secondary" @click="goBack">Abbrechen</NcButton>
        <span v-if="saving" class="hint">Speichere…</span>
        <span v-if="saved" class="success">Gespeichert</span>
        <span v-if="error" class="error">{{ error }}</span>
      </div>
    </div>
  </section>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.mjs'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.mjs'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { createInvoice } from '../api/invoices'
import { createInvoiceItem } from '../api/invoiceItems'
import { getCases } from '../api/cases'
import { getCustomers } from '../api/customers'
import { getOffers } from '../api/offers'
import { getOfferItems } from '../api/offerItems'
import { getProducts } from '../api/products'
import { getFiscalYears } from '../api/fiscalYears'
import { getMisc, getTax, getTexts } from '../api/settings'

const centsFromInput = (value) => {
  const parsed = Number(value)
  if (Number.isNaN(parsed)) {
    return null
  }
  return Math.round(parsed * 100)
}

const inputFromCents = (value) => {
  if (value === null || value === undefined) {
    return ''
  }
  return (Number(value) / 100).toFixed(2)
}

const fromDateInput = (value) => {
  if (!value) {
    return null
  }
  const date = new Date(`${value}T00:00:00`)
  return Math.floor(date.getTime() / 1000)
}

const todayInput = () => new Date().toISOString().slice(0, 10)

const addDaysInput = (dateInput, days) => {
  const date = new Date(`${dateInput}T00:00:00`)
  date.setDate(date.getDate() + days)
  return date.toISOString().slice(0, 10)
}

const createEmptyItem = () => ({
  key: `item-${Math.random().toString(36).slice(2)}`,
  positionType: 'product',
  productId: null,
  name: '',
  description: '',
  quantity: 1,
  unitPrice: '',
})

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

export default {
  name: 'InvoiceCreate',
  components: {
    NcButton,
    NcLoadingIcon,
    NcSelect,
    NcTextArea,
    NcTextField,
    TrashCanOutline,
  },
  data() {
    return {
      loading: true,
      saving: false,
      saved: false,
      error: '',
      customers: [],
      cases: [],
      products: [],
      offers: [],
      fiscalYears: [],
      texts: null,
      tax: null,
      misc: null,
      showGeneral: false,
      selectedOfferId: null,
      form: {
        customerId: null,
        caseId: null,
        issueDate: '',
        dueDate: '',
        greetingText: '',
        extraText: '',
        footerText: '',
        taxRateBp: null,
        isSmallBusiness: false,
        items: [],
      },
      fieldErrors: {},
    }
  },
  computed: {
    customerOptions() {
      return this.customers.map((customer) => ({
        label: customer.company || 'Unbenannt',
        value: customer.id,
      }))
    },
    caseOptions() {
      return this.cases
        .filter((item) => {
          if (!this.form.customerId) {
            return true
          }
          return item.customerId === this.form.customerId
        })
        .map((item) => ({
          label: item.caseNumber
            ? `${item.caseNumber} – ${item.name || 'Unbenannt'}`
            : item.name || 'Unbenannt',
          value: item.id,
        }))
    },
    offerOptions() {
      return this.offers.map((offer) => ({
        label: `${offer.number || 'Angebot'} • ${this.formatDate(offer.issueDate)}`,
        value: offer.id,
      }))
    },
    productOptions() {
      return this.products.map((product) => ({
        label: product.name,
        value: product.id,
      }))
    },
    positionTypeOptions() {
      return [
        { label: 'Produkt/DL', value: 'product' },
        { label: 'Freie Position', value: 'custom' },
      ]
    },
    subtotalCents() {
      return this.form.items.reduce((sum, item) => sum + this.itemTotalCents(item), 0)
    },
    taxCents() {
      if (this.form.isSmallBusiness) {
        return 0
      }
      const rate = Number(this.form.taxRateBp || 0)
      return Math.round((this.subtotalCents * rate) / 10000)
    },
    smallBusinessNote() {
      return this.tax?.smallBusinessNote || 'Kleinunternehmerregelung'
    },
    totalCents() {
      return this.subtotalCents + this.taxCents
    },
    hasActiveFiscalYear() {
      return this.fiscalYears.some((year) => !!year.isActive)
    },
    canSave() {
      if (!this.form.customerId || !this.form.caseId) {
        return false
      }
      const validItems = this.form.items.filter((item) => {
        const quantity = Number(item.quantity)
        const unitPrice = centsFromInput(item.unitPrice)
        if (!quantity || quantity <= 0 || unitPrice === null) {
          return false
        }
        if (item.positionType === 'product') {
          return !!item.productId
        }
        return (item.name || '').trim() !== ''
      })
      return validItems.length > 0
    },
  },
  async mounted() {
    await this.load()
    this.applyRoutePrefill()
  },
  watch: {
    async '$route'() {
      await this.load()
      this.applyRoutePrefill()
    },
    'form.caseId'(value) {
      if (!value) {
        this.offers = []
        this.selectedOfferId = null
        return
      }
      this.loadOffers(value)
      if (!this.form.customerId) {
        const item = this.cases.find((entry) => entry.id === value)
        if (item) {
          this.form.customerId = item.customerId
        }
      }
    },
  },
  methods: {
    async load() {
      this.loading = true
      this.error = ''
      try {
        const [customers, cases, products, texts, tax, misc, fiscalYears] = await Promise.all([
          getCustomers(),
          getCases(),
          getProducts(),
          getTexts(),
          getTax(),
          getMisc(),
          getFiscalYears(),
        ])
        this.customers = Array.isArray(customers) ? customers : []
        this.cases = Array.isArray(cases) ? cases : []
        this.products = Array.isArray(products) ? products : []
        this.texts = texts || {}
        this.misc = misc || {}
        this.fiscalYears = Array.isArray(fiscalYears) ? fiscalYears : []
        this.tax = {
          ...tax,
          isSmallBusiness: parseBool(tax?.isSmallBusiness),
        }
        this.resetForm()
      } catch (e) {
        this.error = 'Daten konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    applyRoutePrefill() {
      const { caseId, customerId } = this.$route.query || {}
      if (customerId) {
        this.form.customerId = Number(customerId)
      }
      if (caseId) {
        this.form.caseId = Number(caseId)
      }
    },
    resetForm() {
      const issueDate = todayInput()
      const paymentTerms = Number(this.misc?.paymentTermsDays || 0)
      this.form = {
        customerId: null,
        caseId: null,
        issueDate,
        dueDate: paymentTerms > 0 ? addDaysInput(issueDate, paymentTerms) : '',
        greetingText: this.texts?.invoiceGreeting || '',
        extraText: '',
        footerText: this.texts?.footerText || '',
        taxRateBp: this.tax?.vatRateBp ?? 0,
        isSmallBusiness: this.tax?.isSmallBusiness ?? false,
        items: [createEmptyItem()],
      }
      this.selectedOfferId = null
      this.showGeneral = false
      this.error = ''
      this.saved = false
      this.fieldErrors = {}
    },
    toggleGeneral() {
      this.showGeneral = !this.showGeneral
    },
    async loadOffers(caseId) {
      try {
        const data = await getOffers({ caseId })
        this.offers = Array.isArray(data) ? data : []
      } catch (e) {
        this.offers = []
      }
    },
    async applyOffer(offerId) {
      if (!offerId) {
        return
      }
      const offer = this.offers.find((entry) => entry.id === offerId)
      if (offer) {
        this.form.customerId = offer.customerId
        this.form.caseId = offer.caseId
      }
      try {
        const items = await getOfferItems(offerId)
        const mapped = (Array.isArray(items) ? items : []).map((item) => ({
          key: `item-${Math.random().toString(36).slice(2)}`,
          positionType: item.positionType || 'custom',
          productId: item.productId || null,
          name: item.name || '',
          description: item.description || '',
          quantity: item.quantity || 1,
          unitPrice: inputFromCents(item.unitPriceCents),
        }))
        this.form.items = mapped.length ? mapped : [createEmptyItem()]
      } catch (e) {
        this.form.items = [createEmptyItem()]
      }
    },
    addItem() {
      this.form.items.push(createEmptyItem())
    },
    removeItem(item) {
      if (!window.confirm('Position wirklich entfernen?')) {
        return
      }
      this.form.items = this.form.items.filter((entry) => entry.key !== item.key)
      if (this.form.items.length === 0) {
        this.form.items.push(createEmptyItem())
      }
    },
    syncFromProduct(item) {
      const product = this.products.find((entry) => entry.id === item.productId)
      if (!product) {
        item.name = ''
        item.description = ''
        item.unitPrice = ''
        return
      }
      item.name = product.name
      item.description = product.description || ''
      item.unitPrice = inputFromCents(product.unitPriceCents)
    },
    itemTotalCents(item) {
      const quantity = Number(item.quantity)
      if (!quantity || quantity <= 0) {
        return 0
      }
      const unitPrice = centsFromInput(item.unitPrice)
      if (unitPrice === null) {
        return 0
      }
      return quantity * unitPrice
    },
    formatPrice(value) {
      if (value === null || value === undefined) {
        return '–'
      }
      return `${(Number(value) / 100).toFixed(2)} €`
    },
    formatTaxRate(value) {
      if (value === null || value === undefined) {
        return '0 %'
      }
      return `${(Number(value) / 100).toFixed(2)} %`
    },
    formatDate(value) {
      if (!value) {
        return '–'
      }
      const date = new Date(value * 1000)
      return date.toLocaleDateString('de-DE')
    },
    isValidDateInput(value) {
      if (!value) {
        return false
      }
      if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false
      }
      const date = new Date(`${value}T00:00:00`)
      return !Number.isNaN(date.getTime())
    },
    async save() {
      this.fieldErrors = {}
      if (!this.form.caseId) {
        this.fieldErrors = { caseId: 'Bitte einen Vorgang auswählen.' }
        return
      }
      if (!this.form.customerId) {
        this.fieldErrors = { customerId: 'Bitte einen Kunden auswählen.' }
        return
      }
      if (!this.isValidDateInput(this.form.issueDate)) {
        this.fieldErrors = { issueDate: 'Bitte ein gültiges Ausstellungsdatum angeben.' }
        return
      }
      if (this.form.dueDate && !this.isValidDateInput(this.form.dueDate)) {
        this.fieldErrors = { dueDate: 'Bitte ein gültiges Fälligkeitsdatum angeben.' }
        return
      }
      this.saving = true
      this.error = ''
      this.saved = false
      try {
        const taxSettings = await getTax()
        const currentIsSmallBusiness = parseBool(taxSettings?.isSmallBusiness)
        const currentTaxRateBp = Number(taxSettings?.vatRateBp || 0)

        this.form.isSmallBusiness = currentIsSmallBusiness
        this.form.taxRateBp = currentTaxRateBp

        const subtotalCents = this.subtotalCents
        const taxCents = currentIsSmallBusiness
          ? 0
          : Math.round((subtotalCents * currentTaxRateBp) / 10000)
        const totalCents = subtotalCents + taxCents

        const payload = {
          caseId: this.form.caseId,
          customerId: this.form.customerId,
          issueDate: fromDateInput(this.form.issueDate),
          dueDate: fromDateInput(this.form.dueDate),
          greetingText: this.form.greetingText || null,
          extraText: this.form.extraText || null,
          footerText: this.form.footerText || null,
          subtotalCents,
          taxCents,
          totalCents,
          taxRateBp: currentTaxRateBp,
          isSmallBusiness: currentIsSmallBusiness,
          status: 'open',
        }
        const invoice = await createInvoice(payload)

        const items = this.form.items
          .map((item) => {
            const quantity = Number(item.quantity)
            const unitPriceCents = centsFromInput(item.unitPrice)
            if (!quantity || quantity <= 0 || unitPriceCents === null) {
              return null
            }
            if (item.positionType === 'product' && !item.productId) {
              return null
            }
            if (item.positionType === 'custom' && !(item.name || '').trim()) {
              return null
            }

            return {
              productId: item.positionType === 'product' ? item.productId : null,
              positionType: item.positionType,
              name: item.name || null,
              description: item.description || null,
              quantity,
              unitPriceCents,
              totalCents: this.itemTotalCents(item),
            }
          })
          .filter(Boolean)

        await Promise.all(items.map((item) => createInvoiceItem(invoice.id, item)))
        this.saved = true
        this.$router.push({
          name: 'cases',
          query: {
            caseId: this.form.caseId,
            customerId: this.form.customerId,
            expand: '1',
          },
        })
      } catch (e) {
        const message = e?.response?.data?.message
        this.error = message || 'Rechnung konnte nicht gespeichert werden.'
      } finally {
        this.saving = false
      }
    },
    goBack() {
      this.$router.push({ name: 'invoices' })
    },
  },
}
</script>

<style scoped>
.invoice-create {
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

.table.compact th,
.table.compact td {
  padding: 8px 6px;
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

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: calc(var(--default-grid-baseline) * 2) 0;
}

.form-group :deep(.v-select) {
  flex: 1;
}

.positions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.toggle {
  display: flex;
  align-items: center;
  gap: 12px;
  background: none;
  border: none;
  padding: 0;
  color: var(--color-primary, #1a73e8);
  font: inherit;
  cursor: pointer;
}

.toggle__state {
  font-size: 0.9rem;
  color: var(--color-text-lighter, #6b7280);
}

.general {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.divider {
  border: none;
  border-top: 1px solid var(--color-border, #e5e7eb);
  margin: 16px 0;
}

.list-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 16px;
  background: var(--color-background-dark, #f3f4f6);
  border-radius: 8px;
}

.summary .total {
  font-weight: 600;
  font-size: 1.05rem;
}

.actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.notice {
  border-radius: 8px;
  padding: 12px 16px;
  background: var(--color-background-dark, #f3f4f6);
}

.notice.error {
  background: color-mix(in srgb, var(--color-error, #b91c1c) 12%, #ffffff);
  border: 1px solid color-mix(in srgb, var(--color-error, #b91c1c) 35%, #ffffff);
  color: var(--color-error, #b91c1c);
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

@media (max-width: 900px) {
  .header {
    flex-direction: column;
    align-items: flex-start;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .summary {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
