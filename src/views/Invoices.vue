<template>
  <section class="invoices">
    <div class="header">
      <div>
        <h1>Rechnungen</h1>
        <p class="subline">Übersicht der Rechnungen.</p>
      </div>
      <NcButton type="primary" @click="openCreateView">Neue Rechnung</NcButton>
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
            input-label="Kunde"
            :label-outside="true"
            placeholder="Alle Kunden"
          />
        </div>
        <div class="filter-group">
          <NcTextField
            label="Datum von"
            type="text"
            placeholder="YYYY-MM-DD"
            :value.sync="filterDateFrom"
          />
        </div>
        <div class="filter-group">
          <NcTextField
            label="Datum bis"
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
            input-label="Sortierung"
            :label-outside="true"
          />
        </div>
      </div>

      <NcEmptyContent
        v-if="filteredInvoices.length === 0"
        name="Noch keine Rechnungen"
        description="Erstelle deine erste Rechnung."
      />

      <table v-else class="table">
        <thead>
          <tr>
            <th>Nummer</th>
            <th>Kunde</th>
            <th>Vorgang</th>
            <th>Datum</th>
            <th class="price">Gesamt</th>
            <th>Status</th>
            <th class="actions">Aktionen</th>
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
                  aria-label="Rechnung bearbeiten"
                  title="Bearbeiten"
                  @click="openEditInvoice(invoice)"
                >
                  <template #icon>
                    <Pencil :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  aria-label="Rechnung verschicken"
                  title="Verschicken"
                  @click="openSendInvoiceModal(invoice)"
                >
                  <template #icon>
                    <EmailOutline :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  aria-label="PDF herunterladen"
                  title="PDF herunterladen"
                  @click="downloadPdf(invoice)"
                >
                  <template #icon>
                    <DownloadBoxOutline :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  aria-label="Rechnung als bezahlt markieren"
                  title="Als bezahlt markieren"
                  :disabled="invoice.status === 'paid'"
                  @click="markPaid(invoice)"
                >
                  <template #icon>
                    <CheckCircleOutline :size="18" />
                  </template>
                </NcButton>
                <NcButton
                  type="tertiary-no-background"
                  aria-label="Rechnung löschen"
                  title="Löschen"
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
                  <h3>Positionen</h3>
                  <NcEmptyContent
                    v-if="invoiceItems.length === 0"
                    name="Keine Positionen"
                    description="Diese Rechnung hat noch keine Positionen."
                  />
                  <table v-else class="table compact">
                    <thead>
                      <tr>
                        <th>Position</th>
                        <th>Beschreibung</th>
                        <th class="price">Menge</th>
                        <th class="price">Einzel</th>
                        <th class="price">Gesamt</th>
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
        <h2>Rechnung verschicken</h2>
        <template v-if="isDirectEmail">
          <p>Die E-Mail wird direkt über den SMTP-Server versendet.</p>
          <div class="email-preview">
            <p><strong>Empfänger:</strong> {{ sendInvoicePreview?.to?.join(', ') || '–' }}</p>
            <p v-if="effectiveFromEmail"><strong>Absender:</strong> {{ effectiveFromEmail }}</p>
            <p v-if="effectiveReplyToEmail"><strong>Antwort an:</strong> {{ effectiveReplyToEmail }}</p>
            <p><strong>Betreff:</strong> {{ sendInvoicePreview?.subject || '–' }}</p>
            <p><strong>Anhang:</strong> {{ sendInvoicePreview?.attachmentName || '–' }}</p>
            <pre class="email-body">{{ sendInvoicePreview?.body || '' }}</pre>
          </div>
          <div class="actions">
            <NcButton
              type="primary"
              :disabled="!canSendInvoiceEmail || sendingInvoice"
              @click="sendInvoiceDirect"
            >
              E-Mail senden
            </NcButton>
            <NcButton type="secondary" @click="closeSendInvoiceModal">Abbrechen</NcButton>
            <span v-if="sendingInvoice" class="hint">Sende…</span>
            <span v-if="sentInvoiceEmail" class="success">Gesendet</span>
            <span v-if="sendInvoiceError" class="error">{{ sendInvoiceError }}</span>
          </div>
        </template>
        <template v-else>
          <p>
            Das PDF wurde heruntergeladen. Bitte füge es als Anhang in deine E-Mail ein.
          </p>
          <p>
            Mit dem Button wird eine Mailvorlage geöffnet (Betreff + Text).
          </p>
          <div class="actions">
            <NcButton
              type="primary"
              :disabled="!canSendInvoiceEmail"
              @click="openInvoiceMailto"
            >
              Mailvorlage erstellen
            </NcButton>
            <NcButton type="secondary" @click="closeSendInvoiceModal">Schließen</NcButton>
          </div>
          <p class="hint">
            Hinweis: Das PDF muss manuell als Anhang hinzugefügt werden.
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
        label: customer.company || 'Unbenannt',
        value: customer.id,
      }))
    },
    sortOptions() {
      return [
        { label: 'Datum (neu zuerst)', value: 'date_desc' },
        { label: 'Datum (alt zuerst)', value: 'date_asc' },
        { label: 'Preis (hoch zuerst)', value: 'price_desc' },
        { label: 'Preis (niedrig zuerst)', value: 'price_asc' },
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
      return this.emailBehavior?.mode === 'direct'
    },
    effectiveFromEmail() {
      const stored = (this.emailBehavior?.fromEmail || '').trim()
      return stored || this.emailBehavior?.defaultFromEmail || ''
    },
    effectiveReplyToEmail() {
      const stored = (this.emailBehavior?.replyToEmail || '').trim()
      return stored || this.emailBehavior?.defaultReplyToEmail || ''
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
        this.error = 'Rechnungen konnten nicht geladen werden.'
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
        this.itemsError = 'Positionen konnten nicht geladen werden.'
      }
    },
    async removeInvoice(invoice) {
      if (!window.confirm('Rechnung wirklich löschen?')) {
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
        this.error = 'Rechnung konnte nicht gelöscht werden.'
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
        this.error = 'Status konnte nicht aktualisiert werden.'
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
      const salutation = contact ? `Hallo ${contact}` : 'Sehr geehrte Damen und Herren'
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

      const subjectTemplate = this.texts?.invoiceEmailSubject || 'Rechnung {{invoiceNumber}}'
      const bodyTemplate =
        this.texts?.invoiceEmailBody ||
        '{{customerSalutation}},\n\nanbei die Rechnung {{invoiceNumber}}.\n\nViele Grüße'

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
        this.sendInvoiceError = 'E-Mail konnte nicht gesendet werden.'
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
      return `${(Number(value) / 100).toFixed(2)} €`
    },
    statusLabel(status) {
      if (status === 'paid') {
        return 'Bezahlt'
      }
      if (status === 'open') {
        return 'Offen'
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
        return `${item.caseNumber} – ${item.name || 'Unbenannt'}`
      }
      return item.name || 'Unbenannt'
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
