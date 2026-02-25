<template>
  <section class="invoices">
    <div class="header">
      <div>
        <h1>{{ t('title') }}</h1>
        <p class="subline">{{ t('subline') }}</p>
      </div>
      <NcButton type="primary" @click="openCreateView">{{ t('newInvoice') }}</NcButton>
    </div>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="content">
      <div class="filters">
        <div class="filter-group">
          <NcSelect
            id="invoiceCustomerFilter"
            v-model="filterCustomerId"
            :options="customerFilterOptions"
            :reduce="(option) => option.value"
            :append-to-body="false"
            :clearable="true"
            :input-label="t('customer')"
            :label-outside="true"
            :placeholder="t('allCustomers')"
          />
        </div>
        <div class="filter-group">
          <NcTextField
            :label="t('dateFrom')"
            type="text"
            placeholder="YYYY-MM-DD"
            :value.sync="filterDateFrom"
          />
        </div>
        <div class="filter-group">
          <NcTextField
            :label="t('dateTo')"
            type="text"
            placeholder="YYYY-MM-DD"
            :value.sync="filterDateTo"
          />
        </div>
        <div class="filter-group">
          <NcSelect
            id="invoiceSort"
            v-model="sortBy"
            :options="sortOptions"
            :reduce="(option) => option.value"
            :append-to-body="false"
            :clearable="false"
            :input-label="t('sorting')"
            :label-outside="true"
          />
        </div>
      </div>

      <NcEmptyContent
        v-if="filteredInvoices.length === 0"
        :name="t('emptyName')"
        :description="t('emptyDescription')"
      />

      <table v-else class="table">
        <thead>
          <tr>
            <th>{{ t('number') }}</th>
            <th>{{ t('customer') }}</th>
            <th>{{ t('case') }}</th>
            <th>{{ t('date') }}</th>
            <th class="price">{{ t('total') }}</th>
            <th>{{ t('status') }}</th>
            <th class="actions">{{ t('actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="invoice in filteredInvoices">
            <tr :key="invoice.id">
              <td class="name">
                <button class="link" type="button" @click="toggleExpand(invoice)">
                  {{ invoice.number || '–' }}
                </button>
              </td>
              <td>{{ customerName(invoice.customerId) }}</td>
              <td>{{ caseName(invoice.caseId) }}</td>
              <td>{{ formatDate(invoice.issueDate) }}</td>
              <td class="price">{{ formatPrice(invoice.totalCents) }}</td>
              <td>{{ statusLabel(invoice.status) }}</td>
              <td class="actions">
                <NcButton
                  type="tertiary-no-background"
                  :aria-label="t('editInvoice')"
                  :title="t('edit')"
                  @click="openEditInvoice(invoice)"
                >
                  <template #icon>
                    <Pencil :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  :aria-label="t('sendInvoice')"
                  :title="t('send')"
                  @click="openSendInvoiceModal(invoice)"
                >
                  <template #icon>
                    <EmailOutline :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  :aria-label="t('downloadPdf')"
                  :title="t('downloadPdf')"
                  @click="downloadPdf(invoice)"
                >
                  <template #icon>
                    <DownloadBoxOutline :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  :aria-label="t('markInvoicePaid')"
                  :title="t('markAsPaid')"
                  :disabled="invoice.status === 'paid'"
                  @click="markPaid(invoice)"
                >
                  <template #icon>
                    <CheckCircleOutline :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  :aria-label="t('deleteInvoice')"
                  :title="t('delete')"
                  @click="removeInvoice(invoice)"
                >
                  <template #icon>
                    <TrashCanOutline :size="18" />
                  </template>
                </NcButton>
              </td>
            </tr>
            <tr v-if="expandedId === invoice.id" :key="`detail-${invoice.id}`">
              <td colspan="7" class="detail">
                <div class="detail-inner">
                  <h3>{{ t('positions') }}</h3>
                  <NcEmptyContent
                    v-if="invoiceItems.length === 0"
                    :name="t('noPositions')"
                    :description="t('noPositionsInvoiceDescription')"
                  />
                  <table v-else class="table compact">
                    <thead>
                      <tr>
                        <th>{{ t('position') }}</th>
                        <th>{{ t('description') }}</th>
                        <th class="price">{{ t('quantity') }}</th>
                        <th class="price">{{ t('unit') }}</th>
                        <th class="price">{{ t('total') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in invoiceItems" :key="item.id">
                        <td class="name">{{ item.name || '–' }}</td>
                        <td>{{ item.description || '–' }}</td>
                        <td class="price">{{ item.quantity || '–' }}</td>
                        <td class="price">{{ formatPrice(item.unitPriceCents) }}</td>
                        <td class="price">{{ formatPrice(item.totalCents) }}</td>
                      </tr>
                    </tbody>
                  </table>
                  <p v-if="itemsError" class="error">{{ itemsError }}</p>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <p v-if="error" class="error">{{ error }}</p>
    </div>

    <NcModal v-if="showSendInvoiceModal" size="normal" @close="closeSendInvoiceModal">
      <div class="modal__content">
        <h2>{{ t('sendInvoiceModalTitle') }}</h2>
        <template v-if="isDirectEmail">
          <p>{{ directDeliveryHint }}</p>
          <div class="email-preview">
            <p><strong>{{ t('recipient') }}:</strong> {{ sendInvoicePreview?.to?.join(', ') || '–' }}</p>
            <p v-if="effectiveFromEmail"><strong>{{ t('sender') }}:</strong> {{ effectiveFromEmail }}</p>
            <p v-if="effectiveReplyToEmail"><strong>{{ t('replyTo') }}:</strong> {{ effectiveReplyToEmail }}</p>
            <p><strong>{{ t('subject') }}:</strong> {{ sendInvoicePreview?.subject || '–' }}</p>
            <p><strong>{{ t('attachment') }}:</strong> {{ sendInvoicePreview?.attachmentName || '–' }}</p>
            <pre class="email-body">{{ sendInvoicePreview?.body || '' }}</pre>
          </div>
          <div class="actions">
            <NcButton
              type="primary"
              :disabled="!canSendInvoiceEmail || sendingInvoice"
              @click="sendInvoiceDirect"
            >
              {{ t('sendEmail') }}
            </NcButton>
            <NcButton type="secondary" @click="closeSendInvoiceModal">{{ t('cancel') }}</NcButton>
            <span v-if="sendingInvoice" class="hint">{{ t('sending') }}</span>
            <span v-if="sentInvoiceEmail" class="success">{{ t('sent') }}</span>
            <span v-if="sendInvoiceError" class="error">{{ sendInvoiceError }}</span>
          </div>
        </template>
        <template v-else>
          <p>
            {{ t('pdfDownloadedHint') }}
          </p>
          <p>
            {{ t('templateOpensHint') }}
          </p>
          <div class="actions">
            <NcButton
              type="primary"
              :disabled="!canSendInvoiceEmail"
              @click="openInvoiceMailto"
            >
              {{ t('createMailTemplate') }}
            </NcButton>
            <NcButton type="secondary" @click="closeSendInvoiceModal">{{ t('close') }}</NcButton>
          </div>
          <p class="hint">
            {{ t('manualAttachmentHint') }}
          </p>
        </template>
      </div>
    </NcModal>
  </section>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon, NcModal } from '@nextcloud/vue'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.mjs'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import DownloadBoxOutline from 'vue-material-design-icons/DownloadBoxOutline.vue'
import EmailOutline from 'vue-material-design-icons/EmailOutline.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import {
  deleteInvoice,
  getInvoicePdfUrl,
  getInvoices,
  sendInvoiceEmail,
  updateInvoice,
} from '../api/invoices'
import { getInvoiceItems } from '../api/invoiceItems'
import { getCases } from '../api/cases'
import { getCustomers } from '../api/customers'
import { getEmailBehavior, getTexts } from '../api/settings'

export default {
  name: 'Invoices',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcModal,
    NcSelect,
    NcTextField,
    CheckCircleOutline,
    DownloadBoxOutline,
    EmailOutline,
    Pencil,
    TrashCanOutline,
  },
  data() {
    return {
      loading: true,
      error: '',
      itemsError: '',
      invoices: [],
      customers: [],
      cases: [],
      texts: null,
      emailBehavior: null,
      expandedId: null,
      invoiceItems: [],
      filterCustomerId: null,
      filterDateFrom: '',
      filterDateTo: '',
      sortBy: 'date_desc',
      showSendInvoiceModal: false,
      sendInvoiceTarget: null,
      sendInvoiceMailto: '',
      sendInvoicePreview: null,
      sendInvoiceError: '',
      sendingInvoice: false,
      sentInvoiceEmail: false,
    }
  },
  computed: {
    customerMap() {
      return new Map(this.customers.map((customer) => [customer.id, customer]))
    },
    caseMap() {
      return new Map(this.cases.map((item) => [item.id, item]))
    },
    customerFilterOptions() {
      return this.customers.map((customer) => ({
        label: customer.company || this.t('unnamed'),
        value: customer.id,
      }))
    },
    sortOptions() {
      return [
        { label: this.t('sortDateDesc'), value: 'date_desc' },
        { label: this.t('sortDateAsc'), value: 'date_asc' },
        { label: this.t('sortPriceDesc'), value: 'price_desc' },
        { label: this.t('sortPriceAsc'), value: 'price_asc' },
      ]
    },
    filteredInvoices() {
      const from = this.filterDateFrom ? this.dateInputToUnix(this.filterDateFrom) : null
      const to = this.filterDateTo ? this.dateInputToUnix(this.filterDateTo, true) : null

      const filtered = this.invoices.filter((invoice) => {
        if (this.filterCustomerId && invoice.customerId !== this.filterCustomerId) {
          return false
        }
        if (from && (!invoice.issueDate || invoice.issueDate < from)) {
          return false
        }
        if (to && (!invoice.issueDate || invoice.issueDate > to)) {
          return false
        }
        return true
      })

      return filtered.sort((a, b) => this.sortInvoices(a, b))
    },
    canSendInvoiceEmail() {
      if (this.isDirectEmail) {
        return !!this.sendInvoicePreview?.to?.length
      }
      return !!this.sendInvoiceMailto
    },
    isDirectEmail() {
      const mode = this.emailBehavior?.mode
      return mode === 'direct' || mode === 'admin_smtp' || mode === 'nextcloud_mail'
    },
    directDeliveryHint() {
      if (this.emailBehavior?.mode === 'nextcloud_mail') {
        return this.t('directDeliveryMailHint')
      }
      return this.t('directDeliveryAdminHint')
    },
    effectiveFromEmail() {
      return (this.emailBehavior?.effectiveFromEmail || '').trim()
    },
    effectiveReplyToEmail() {
      return (this.emailBehavior?.effectiveReplyToEmail || '').trim()
    },
  },
  async mounted() {
    await this.load()
  },
  methods: {
    t(key) {
      return this.$tKey(`invoices.${key}`, key)
    },
    async load() {
      this.loading = true
      this.error = ''
      try {
        const [invoices, customers, cases, texts, emailBehavior] = await Promise.all([
          getInvoices(),
          getCustomers(),
          getCases(),
          getTexts(),
          getEmailBehavior(),
        ])
        this.invoices = Array.isArray(invoices) ? invoices : []
        this.customers = Array.isArray(customers) ? customers : []
        this.cases = Array.isArray(cases) ? cases : []
        this.texts = texts || {}
        this.emailBehavior = emailBehavior || { mode: 'manual' }
      } catch (e) {
        this.error = this.t('loadError')
      } finally {
        this.loading = false
      }
    },
    openCreateView() {
      this.$router.push({ name: 'invoices-new' })
    },
    openEditInvoice(invoice) {
      this.$router.push({ name: 'invoices-edit', params: { id: invoice.id } })
    },
    async toggleExpand(invoice) {
      if (this.expandedId === invoice.id) {
        this.expandedId = null
        this.invoiceItems = []
        return
      }
      this.expandedId = invoice.id
      await this.loadItems(invoice.id)
    },
    async loadItems(invoiceId) {
      this.itemsError = ''
      try {
        const items = await getInvoiceItems(invoiceId)
        this.invoiceItems = Array.isArray(items) ? items : []
      } catch (e) {
        this.invoiceItems = []
        this.itemsError = this.t('itemsLoadError')
      }
    },
    async removeInvoice(invoice) {
      if (!window.confirm(this.t('deleteConfirm'))) {
        return
      }
      try {
        await deleteInvoice(invoice.id)
        this.invoices = this.invoices.filter((item) => item.id !== invoice.id)
        if (this.expandedId === invoice.id) {
          this.expandedId = null
          this.invoiceItems = []
        }
      } catch (e) {
        this.error = this.t('deleteError')
      }
    },
    async markPaid(invoice) {
      if (invoice.status === 'paid') {
        return
      }
      try {
        const payload = this.buildUpdatePayload(invoice, 'paid')
        await updateInvoice(invoice.id, payload)
        this.invoices = this.invoices.map((item) =>
          item.id === invoice.id ? { ...item, status: 'paid' } : item
        )
      } catch (e) {
        this.error = this.t('statusUpdateError')
      }
    },
    buildUpdatePayload(invoice, status) {
      return {
        number: invoice.number || null,
        status,
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
    },
    downloadPdf(invoice) {
      const url = getInvoicePdfUrl(invoice.id)
      window.open(url, '_blank')
    },
    openSendInvoiceModal(invoice) {
      this.sendInvoiceTarget = invoice
      this.sendInvoiceError = ''
      this.sentInvoiceEmail = false
      const emailData = this.buildInvoiceEmailData(invoice)
      this.sendInvoicePreview = {
        ...emailData,
        attachmentName: this.buildInvoiceAttachmentName(invoice),
      }
      this.sendInvoiceMailto = this.buildInvoiceMailtoFromData(emailData)
      if (!this.isDirectEmail) {
        const pdfUrl = getInvoicePdfUrl(invoice.id)
        window.open(pdfUrl, '_blank')
      }
      this.showSendInvoiceModal = true
    },
    closeSendInvoiceModal() {
      this.showSendInvoiceModal = false
      this.sendInvoiceTarget = null
      this.sendInvoiceMailto = ''
      this.sendInvoicePreview = null
      this.sendInvoiceError = ''
      this.sendingInvoice = false
      this.sentInvoiceEmail = false
    },
    openInvoiceMailto() {
      if (!this.sendInvoiceMailto) {
        return
      }
      window.location.href = this.sendInvoiceMailto
    },
    buildInvoiceMailtoFromData(data) {
      const to = data.to.join(',')
      const subject = encodeURIComponent(data.subject)
      const body = encodeURIComponent(data.body)
      const base = to ? `mailto:${to}` : 'mailto:'
      return `${base}?subject=${subject}&body=${body}`
    },
    buildInvoiceEmailData(invoice) {
      const customer = this.customers.find((entry) => entry.id === invoice.customerId)
      const caseItem = this.cases.find((entry) => entry.id === invoice.caseId)
      const contact = (customer?.contactName || '').trim()
      const salutation = contact
        ? this.t('salutationContact').replace('{name}', contact)
        : this.t('salutationDefault')
      const context = {
        invoiceNumber: invoice.number || '',
        customerName: customer?.company || '',
        customerContact: contact,
        customerSalutation: salutation,
        caseName: caseItem?.name || '',
        total: this.formatPrice(invoice.totalCents),
        issueDate: invoice.issueDate
          ? new Date(invoice.issueDate * 1000).toLocaleDateString('de-DE')
          : '',
      }

      const subjectTemplate = this.texts?.invoiceEmailSubject || this.t('defaultEmailSubject')
      const bodyTemplate =
        this.texts?.invoiceEmailBody ||
        this.t('defaultEmailBody')

      const recipients = this.buildInvoiceRecipients(caseItem, customer)
      return {
        to: recipients,
        subject: this.applyTemplate(subjectTemplate, context),
        body: this.applyTemplate(bodyTemplate, context),
      }
    },
    buildInvoiceAttachmentName(invoice) {
      const suffix = invoice.number || invoice.id
      return `rechnung-${suffix}.pdf`
    },
    async sendInvoiceDirect() {
      if (!this.sendInvoiceTarget || !this.sendInvoicePreview) {
        return
      }
      this.sendingInvoice = true
      this.sendInvoiceError = ''
      this.sentInvoiceEmail = false
      try {
        await sendInvoiceEmail(this.sendInvoiceTarget.id, {
          to: this.sendInvoicePreview.to,
          subject: this.sendInvoicePreview.subject,
          body: this.sendInvoicePreview.body,
        })
        this.sentInvoiceEmail = true
        window.setTimeout(() => {
          this.closeSendInvoiceModal()
        }, 700)
      } catch (e) {
        this.sendInvoiceError = this.t('sendError')
      } finally {
        this.sendingInvoice = false
      }
    },
    buildInvoiceRecipients(caseItem, customer) {
      const billingEmail = (customer?.billingEmail || '').trim()
      const contactEmail = (customer?.email || '').trim()
      const recipientState = this.getInvoiceRecipientFlags(customer)
      const recipients = []
      if (recipientState.sendInvoiceToBillingEmail && billingEmail) {
        recipients.push(billingEmail)
      }
      if (recipientState.sendInvoiceToContactEmail && contactEmail) {
        recipients.push(contactEmail)
      }
      return recipients
    },
    getInvoiceRecipientFlags(customer) {
      if (
        customer &&
        ((customer.sendInvoiceToBillingEmail !== null &&
          customer.sendInvoiceToBillingEmail !== undefined) ||
          (customer.sendInvoiceToContactEmail !== null &&
            customer.sendInvoiceToContactEmail !== undefined))
      ) {
        return {
          sendInvoiceToBillingEmail: !!customer.sendInvoiceToBillingEmail,
          sendInvoiceToContactEmail: !!customer.sendInvoiceToContactEmail,
        }
      }
      return this.getInvoiceRecipientDefaults(customer)
    },
    getInvoiceRecipientDefaults(customer) {
      const billingEmail = (customer?.billingEmail || '').trim()
      return {
        sendInvoiceToBillingEmail: !!billingEmail,
        sendInvoiceToContactEmail: !billingEmail,
      }
    },
    applyTemplate(template, context) {
      return Object.entries(context).reduce(
        (text, [key, value]) => text.replaceAll(`{{${key}}}`, value || ''),
        template || ''
      )
    },
    sortInvoices(a, b) {
      switch (this.sortBy) {
        case 'date_asc':
          return (a.issueDate || 0) - (b.issueDate || 0)
        case 'price_desc':
          return (b.totalCents || 0) - (a.totalCents || 0)
        case 'price_asc':
          return (a.totalCents || 0) - (b.totalCents || 0)
        case 'date_desc':
        default:
          return (b.issueDate || 0) - (a.issueDate || 0)
      }
    },
    dateInputToUnix(value, endOfDay = false) {
      if (!value) {
        return null
      }
      const date = new Date(`${value}T00:00:00`)
      if (endOfDay) {
        date.setHours(23, 59, 59, 999)
      }
      return Math.floor(date.getTime() / 1000)
    },
    formatDate(value) {
      if (!value) {
        return '–'
      }
      const date = new Date(value * 1000)
      return date.toLocaleDateString('de-DE')
    },
    formatPrice(value) {
      if (value === null || value === undefined) {
        return '–'
      }
      return `${(Number(value) / 100).toLocaleString('de-DE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })} €`
    },
    statusLabel(status) {
      if (status === 'paid') {
        return this.t('paid')
      }
      if (status === 'open') {
        return this.t('open')
      }
      return status || '–'
    },
    customerName(id) {
      return this.customerMap.get(id)?.company || '–'
    },
    caseName(id) {
      const item = this.caseMap.get(id)
      if (!item) {
        return '–'
      }
      if (item.caseNumber) {
        return `${item.caseNumber} – ${item.name || this.t('unnamed')}`
      }
      return item.name || this.t('unnamed')
    },
  },
}
</script>

<style scoped>
.invoices {
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

.link {
  background: none;
  border: none;
  padding: 0;
  color: var(--color-primary, #1a73e8);
  cursor: pointer;
  font-weight: 600;
}

.detail {
  background: var(--color-background-hover, #f9fafb);
}

.detail-inner {
  padding: 12px 8px 16px;
}

.error {
  color: var(--color-error, #b91c1c);
}

.actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.email-preview {
  background: var(--color-background-dark, #f3f4f6);
  border-radius: 8px;
  padding: 12px;
  margin: 12px 0;
}

.email-body {
  white-space: pre-wrap;
  background: var(--color-main-background, #ffffff);
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 6px;
  padding: 8px;
  margin-top: 8px;
  max-height: 200px;
  overflow: auto;
}

.hint {
  color: var(--color-text-lighter, #6b7280);
}

.success {
  color: var(--color-success, #2d9a4f);
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
