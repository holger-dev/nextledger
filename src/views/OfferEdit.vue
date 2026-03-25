<template>
  <section class="offer-edit">
    <div class="header">
      <div>
        <h1>{{ t('titleEdit') }}</h1>
        <p class="subline">{{ t('sublineEdit') }}</p>
      </div>
      <NcButton type="secondary" @click="goBack">{{ t('backToOverview') }}</NcButton>
    </div>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="content">
      <div class="section-header">
        <button class="toggle" type="button" @click="toggleGeneral">
          <span>{{ t('generalSettings') }}</span>
          <span class="toggle__state">{{ showGeneral ? t('hide') : t('show') }}</span>
        </button>
      </div>

      <div v-if="showGeneral" class="general">
        <div class="form-grid">
          <div class="form-group">
          <NcTextField
            :label="t('offerNumber')"
            :value.sync="form.number"
            :disabled="true"
          />
          </div>
          <div class="form-group">
          <NcSelect
            id="offerCustomer"
            v-model="form.customerId"
            :options="customerOptions"
            :reduce="(option) => option.value"
            :append-to-body="false"
            :clearable="false"
            :input-label="t('customerRequired')"
            :label-outside="true"
            :placeholder="t('selectPlease')"
          />
          <p v-if="fieldErrors.customerId" class="field-error">{{ fieldErrors.customerId }}</p>
          </div>
          <div class="form-group">
          <NcSelect
            id="offerCase"
            v-model="form.caseId"
            :options="caseOptions"
            :reduce="(option) => option.value"
            :append-to-body="false"
            :clearable="true"
            :input-label="t('caseRequired')"
            :label-outside="true"
            :placeholder="t('optional')"
          />
          <p v-if="fieldErrors.caseId" class="field-error">{{ fieldErrors.caseId }}</p>
          </div>
          <div class="form-group">
          <NcSelect
            id="offerStatus"
            v-model="form.status"
            :options="statusOptions"
            :reduce="(option) => option.value"
            :append-to-body="false"
            :clearable="false"
            :input-label="t('status')"
            :label-outside="true"
            :placeholder="t('status')"
          />
          <p class="field-hint">{{ t('statusHint') }}</p>
          </div>
          <div class="form-group">
          <NcTextField
            :label="t('issueDateRequired')"
            type="text"
            placeholder="YYYY-MM-DD"
            :value.sync="form.issueDate"
          />
          <p v-if="fieldErrors.issueDate" class="field-error">{{ fieldErrors.issueDate }}</p>
          </div>
          <div class="form-group">
          <NcTextField
            :label="t('validUntil')"
            type="text"
            placeholder="YYYY-MM-DD"
            :value.sync="form.validUntil"
          />
          <p v-if="fieldErrors.validUntil" class="field-error">{{ fieldErrors.validUntil }}</p>
          </div>
          <div class="form-group tax-toggle">
            <NcCheckboxRadioSwitch
              type="switch"
              :checked="form.isSmallBusiness"
              @update:checked="form.isSmallBusiness = $event"
            >
              {{ t('smallBusinessSwitch') }}
            </NcCheckboxRadioSwitch>
          </div>
          <div class="form-group">
            <NcTextField
              :label="t('vatRate')"
              type="text"
              :placeholder="t('vatRatePlaceholder')"
              :disabled="form.isSmallBusiness"
              :value.sync="form.taxRatePercent"
            />
            <p v-if="fieldErrors.taxRatePercent" class="field-error">{{ fieldErrors.taxRatePercent }}</p>
            <p class="field-hint">{{ t('vatRateHint') }}</p>
          </div>
        </div>

        <div class="form-group">
          <NcTextArea :label="t('greetingText')" :value.sync="form.greetingText" />
        </div>
        <div class="form-group">
          <NcTextArea :label="t('extraText')" :value.sync="form.extraText" />
        </div>
        <div class="form-group">
          <NcTextArea :label="t('footerText')" :value.sync="form.footerText" />
        </div>
      </div>

      <div class="positions">
        <div class="list-header">
          <h3>{{ t('positions') }}</h3>
          <NcButton type="secondary" @click="addItem">{{ t('addPosition') }}</NcButton>
        </div>

        <table class="table compact">
          <thead>
            <tr>
              <th class="col-type">{{ t('type') }}</th>
              <th class="col-product">{{ t('product') }}</th>
              <th>{{ t('name') }}</th>
              <th>{{ t('description') }}</th>
              <th class="col-qty">{{ t('quantity') }}</th>
              <th class="price">{{ t('unitPrice') }}</th>
              <th class="price">{{ t('total') }}</th>
              <th class="actions">{{ t('action') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in form.items" :key="item.key">
              <td class="col-type">
                <NcSelect
                  v-model="item.positionType"
                  :options="positionTypeOptions"
                  :reduce="(option) => option.value"
                  :append-to-body="false"
                  :clearable="false"
                  :input-label="t('type')"
                  :label-outside="true"
                />
              </td>
              <td class="col-product">
                <NcSelect
                  v-if="item.positionType === 'product'"
                  v-model="item.productId"
                  :options="productOptions"
                  :reduce="(option) => option.value"
                    :append-to-body="false"
                    :clearable="true"
                    :input-label="t('product')"
                    :label-outside="true"
                    :placeholder="t('product')"
                    @input="syncFromProduct(item)"
                  />
                <span v-else>–</span>
              </td>
              <td class="col-name">
                <NcTextField
                  v-if="item.positionType === 'custom'"
                  :label="t('name')"
                  :placeholder="t('name')"
                  :value.sync="item.name"
                />
                <span v-else>{{ item.name || '–' }}</span>
              </td>
              <td class="col-description">
                <NcTextField
                  v-if="item.positionType === 'custom'"
                  :label="t('description')"
                  :placeholder="t('description')"
                  :value.sync="item.description"
                />
                <span v-else>{{ item.description || '–' }}</span>
              </td>
              <td class="col-qty">
                <NcTextField
                  :label="t('quantityRequired')"
                  type="text"
                  placeholder="1"
                  :value.sync="item.quantity"
                />
              </td>
              <td class="price">
                <NcTextField
                  :label="t('unitPriceRequired')"
                  type="text"
                  placeholder="0.00"
                  :value.sync="item.unitPrice"
                />
              </td>
              <td class="price">{{ formatPrice(itemTotalCents(item)) }}</td>
              <td class="actions">
                <NcButton
                  type="tertiary-no-background"
                  :aria-label="t('removePosition')"
                  :title="t('remove')"
                  @click="removeItem(item)"
                >
                  <template #icon>
                    <TrashCanOutline :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  :aria-label="t('moveUpPosition')"
                  :title="t('moveUp')"
                  :disabled="index === 0"
                  @click="moveItemUp(index)"
                >
                  ↑
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  :aria-label="t('moveDownPosition')"
                  :title="t('moveDown')"
                  :disabled="index === form.items.length - 1"
                  @click="moveItemDown(index)"
                >
                  ↓
                </NcButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="summary">
        <div>
          <p>{{ t('subtotal') }}: {{ formatPrice(subtotalCents) }}</p>
          <p v-if="form.isSmallBusiness">
            {{ smallBusinessNote }}
          </p>
          <p v-else>
            {{ t('tax') }} ({{ formatTaxRate(resolvedTaxRateBp) }}): {{ formatPrice(taxCents) }}
          </p>
        </div>
        <div class="total">
          {{ t('total') }}: {{ formatPrice(totalCents) }}
        </div>
      </div>

      <hr class="divider" />

      <div class="actions">
        <NcButton type="primary" :disabled="saving || !canSave" @click="save">
          {{ t('saveOffer') }}
        </NcButton>
        <NcButton type="secondary" @click="goBack">{{ t('cancel') }}</NcButton>
        <span v-if="saving" class="hint">{{ t('saving') }}</span>
        <span v-if="saved" class="success">{{ t('saved') }}</span>
        <span v-if="error" class="error">{{ error }}</span>
      </div>
    </div>
  </section>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcLoadingIcon } from '@nextcloud/vue'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.mjs'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.mjs'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { getOffer, updateOffer } from '../api/offers'
import { createOfferItem, deleteOfferItem, getOfferItems } from '../api/offerItems'
import { getCases } from '../api/cases'
import { getCustomers } from '../api/customers'
import { getProducts } from '../api/products'
import { getTax, getTexts } from '../api/settings'

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

const percentToBasisPoints = (value) => {
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
  if (Number.isNaN(parsed) || parsed < 0) {
    return null
  }
  return Math.round(parsed * 100)
}

const percentFromBasisPoints = (value) => {
  if (value === null || value === undefined) {
    return ''
  }
  return (Number(value) / 100).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
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

const toDateInput = (timestamp) => {
  if (!timestamp) {
    return ''
  }
  const date = new Date(timestamp * 1000)
  const pad = (value) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const fromDateInput = (value) => {
  if (!value) {
    return null
  }
  const date = new Date(`${value}T00:00:00`)
  const utcSeconds = Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()) / 1000
  return Math.floor(utcSeconds)
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

export default {
  name: 'OfferEdit',
  components: {
    NcButton,
    NcCheckboxRadioSwitch,
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
      texts: null,
      tax: null,
      offerItems: [],
      showGeneral: false,
      form: {
        number: '',
        customerId: null,
        caseId: null,
        status: 'draft',
        issueDate: '',
        validUntil: '',
        greetingText: '',
        extraText: '',
        footerText: '',
        taxRateBp: null,
        taxRatePercent: '19,00',
        isSmallBusiness: false,
        items: [],
      },
      fieldErrors: {},
    }
  },
  computed: {
    offerId() {
      return Number(this.$route.params.id)
    },
    customerOptions() {
      return this.customers.map((customer) => ({
        label: customer.company || this.t('unnamed'),
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
            ? `${item.caseNumber} – ${item.name || this.t('unnamed')}`
            : item.name || this.t('unnamed'),
          value: item.id,
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
        { label: this.t('positionTypeProduct'), value: 'product' },
        { label: this.t('positionTypeCustom'), value: 'custom' },
      ]
    },
    statusOptions() {
      return [
        { label: this.t('statusDraft'), value: 'draft' },
        { label: this.t('statusSent'), value: 'sent' },
        { label: this.t('statusAccepted'), value: 'accepted' },
      ]
    },
    subtotalCents() {
      return this.form.items.reduce((sum, item) => sum + this.itemTotalCents(item), 0)
    },
    resolvedTaxRateBp() {
      if (this.form.isSmallBusiness) {
        return 0
      }
      return percentToBasisPoints(this.form.taxRatePercent) ?? Number(this.form.taxRateBp || 0)
    },
    taxCents() {
      if (this.form.isSmallBusiness) {
        return 0
      }
      const rate = this.resolvedTaxRateBp
      return Math.round((this.subtotalCents * rate) / 10000)
    },
    smallBusinessNote() {
      return this.tax?.smallBusinessNote || this.t('smallBusinessRule')
    },
    totalCents() {
      return this.subtotalCents + this.taxCents
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
  },
  watch: {
    async '$route'() {
      await this.load()
    },
  },
  methods: {
    t(key) {
      return this.$tKey(`offerForm.${key}`, key)
    },
    async load() {
      this.loading = true
      this.error = ''
      try {
        const [customers, cases, products, texts, tax, offer, items] = await Promise.all([
          getCustomers(),
          getCases(),
          getProducts(),
          getTexts(),
          getTax(),
          getOffer(this.offerId),
          getOfferItems(this.offerId),
        ])
        this.customers = Array.isArray(customers) ? customers : []
        this.cases = Array.isArray(cases) ? cases : []
        this.products = Array.isArray(products) ? products : []
        this.texts = texts || {}
        const normalizeTax = (value) => {
          const parseBool = (v) => {
            if (v === true || v === false) {
              return v
            }
            if (v === 1 || v === '1') {
              return true
            }
            if (v === 0 || v === '0') {
              return false
            }
            if (typeof v === 'string') {
              return ['true', 'yes', 'on'].includes(v.toLowerCase())
            }
            return Boolean(v)
          }
          return {
            ...value,
            isSmallBusiness: parseBool(value?.isSmallBusiness),
          }
        }
        this.tax = normalizeTax(tax || {})
        this.offerItems = Array.isArray(items) ? items : []

        this.form = {
          number: offer.number || '',
          customerId: offer.customerId || null,
          caseId: offer.caseId || null,
          status: offer.status || 'draft',
          issueDate: toDateInput(offer.issueDate),
          validUntil: toDateInput(offer.validUntil),
          greetingText: offer.greetingText || this.texts?.offerGreeting || '',
          extraText: offer.extraText || '',
          footerText: offer.footerText || this.texts?.footerText || '',
          taxRateBp: offer.taxRateBp ?? this.tax?.vatRateBp ?? 0,
          taxRatePercent: percentFromBasisPoints(offer.taxRateBp ?? this.tax?.vatRateBp ?? 0),
          isSmallBusiness: offer.isSmallBusiness ?? this.tax?.isSmallBusiness ?? false,
          items: this.offerItems.map((item) => ({
            key: `item-${item.id}`,
            positionType: item.positionType || 'product',
            productId: item.productId || null,
            name: item.name || '',
            description: item.description || '',
            quantity: item.quantity || 1,
            unitPrice: inputFromCents(item.unitPriceCents),
          })),
        }

        if (this.form.items.length === 0) {
          this.form.items = [createEmptyItem()]
        }
        this.fieldErrors = {}

      } catch (e) {
        this.error = this.t('loadError')
      } finally {
        this.loading = false
      }
    },
    addItem() {
      this.form.items.push(createEmptyItem())
    },
    removeItem(item) {
      if (!window.confirm(this.t('removePositionConfirm'))) {
        return
      }
      this.form.items = this.form.items.filter((entry) => entry.key !== item.key)
      if (this.form.items.length === 0) {
        this.form.items.push(createEmptyItem())
      }
    },
    moveItemUp(index) {
      if (index <= 0) {
        return
      }
      const items = [...this.form.items]
      ;[items[index - 1], items[index]] = [items[index], items[index - 1]]
      this.form.items = items
    },
    moveItemDown(index) {
      if (index >= this.form.items.length - 1) {
        return
      }
      const items = [...this.form.items]
      ;[items[index + 1], items[index]] = [items[index], items[index + 1]]
      this.form.items = items
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
      return this.$formatCurrencyCents(value)
    },
    formatTaxRate(value) {
      if (value === null || value === undefined) {
        return '0 %'
      }
      return `${(Number(value) / 100).toLocaleString('de-DE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })} %`
    },
    toggleGeneral() {
      this.showGeneral = !this.showGeneral
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
        this.fieldErrors = { caseId: this.t('caseRequiredError') }
        return
      }
      if (!this.form.customerId) {
        this.fieldErrors = { customerId: this.t('customerRequiredError') }
        return
      }
      if (!this.isValidDateInput(this.form.issueDate)) {
        this.fieldErrors = { issueDate: this.t('issueDateError') }
        return
      }
      if (this.form.validUntil && !this.isValidDateInput(this.form.validUntil)) {
        this.fieldErrors = { validUntil: this.t('validUntilError') }
        return
      }
      const taxRateBp = this.form.isSmallBusiness
        ? 0
        : percentToBasisPoints(this.form.taxRatePercent)
      if (!this.form.isSmallBusiness && taxRateBp === null) {
        this.fieldErrors = { taxRatePercent: this.t('vatRateError') }
        return
      }
      this.saving = true
      this.error = ''
      this.saved = false
      try {
        const payload = {
          number: this.form.number || null,
          caseId: this.form.caseId,
          customerId: this.form.customerId,
          status: this.form.status || 'draft',
          issueDate: fromDateInput(this.form.issueDate),
          validUntil: fromDateInput(this.form.validUntil),
          greetingText: this.form.greetingText || null,
          extraText: this.form.extraText || null,
          footerText: this.form.footerText || null,
          subtotalCents: this.subtotalCents,
          taxCents: this.taxCents,
          totalCents: this.totalCents,
          taxRateBp,
          isSmallBusiness: this.form.isSmallBusiness,
        }
        this.form.taxRateBp = taxRateBp
        await updateOffer(this.offerId, payload)

        const existingItems = await getOfferItems(this.offerId)
        await Promise.all(
          (Array.isArray(existingItems) ? existingItems : []).map((item) =>
            deleteOfferItem(item.id)
          )
        )

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

        await Promise.all(items.map((item) => createOfferItem(this.offerId, item)))

        this.saved = true
        this.goBack()
      } catch (e) {
        this.error = this.t('saveError')
      } finally {
        this.saving = false
      }
    },
    goBack() {
      const returnTo = this.$route.query.returnTo
      const caseId = this.$route.query.caseId || this.form.caseId
      if (returnTo === 'case' && caseId) {
        this.$router.push({ name: 'case-detail', params: { id: caseId } })
        return
      }
      this.$router.push({ name: 'offers' })
    },
  },
}
</script>

<style scoped>
.offer-edit {
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
  width: clamp(90px, 10vw, 120px);
}

.table th.actions,
.table td.actions {
  text-align: right;
  white-space: nowrap;
  min-width: 160px;
}

.table td.actions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.table th.col-type,
.table td.col-type {
  width: clamp(120px, 14vw, 160px);
}

.table th.col-product,
.table td.col-product {
  width: clamp(170px, 18vw, 240px);
}

.table th.col-name,
.table td.col-name {
  width: clamp(200px, 22vw, 320px);
}

.table th.col-description,
.table td.col-description {
  min-width: clamp(220px, 26vw, 360px);
}

.table th.col-qty,
.table td.col-qty {
  width: clamp(70px, 8vw, 90px);
}

.table td.name {
  font-weight: 600;
}


.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 16px;
}

.general {
  max-width: 980px;
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

.divider {
  border: none;
  border-top: 1px solid var(--color-border, #e5e7eb);
  margin: 16px 0;
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

.tax-toggle {
  justify-content: flex-end;
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

.field-hint {
  color: var(--color-text-lighter, #6b7280);
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
}
</style>
