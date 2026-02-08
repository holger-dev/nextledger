<template>
  <section class="cases">
    <div v-if="!standalone" class="header">
      <div>
        <h1>Vorgänge</h1>
        <p class="subline">Kompakt, filterbar und pro Vorgang aufklappbar.</p>
      </div>
      <NcButton type="primary" @click="openCreateModal">Neuer Vorgang</NcButton>
    </div>

    <NcLoadingIcon v-if="loading" />

    <NcModal
      v-if="showSetupModal"
      size="normal"
      :can-close="false"
      :close-on-click-outside="false"
    >
      <div class="modal__content">
        <h2>Einrichtung erforderlich</h2>
        <p class="hint">
          Ohne Firmendaten und ein Wirtschaftsjahr kann nicht gearbeitet werden.
          Bitte lege beides an, bevor du Vorgänge bearbeitest.
        </p>
        <div class="setup-links">
          <NcButton type="primary" @click="goToCompanySettings">
            Firmadaten anlegen
          </NcButton>
          <NcButton type="secondary" @click="goToFiscalYear">
            Wirtschaftsjahr anlegen
          </NcButton>
        </div>
      </div>
    </NcModal>

    <div v-else class="content">
      <div v-if="!standalone" class="filters">
        <label for="customerFilter">Kunde</label>
        <NcSelect
          id="customerFilter"
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

      <NcEmptyContent
        v-if="itemsToShow.length === 0"
        name="Noch keine Vorgänge"
        description="Lege deinen ersten Vorgang für einen Kunden an."
      />

      <div v-else class="case-list">
        <article v-for="item in itemsToShow" :key="item.id" class="case-card">
          <header class="case-header">
            <div>
              <div class="case-title-row">
                <h2 class="case-title">{{ item.name || 'Unbenannt' }}</h2>
                <span v-if="item.caseNumber" class="case-id">{{ item.caseNumber }}</span>
              </div>
              <p class="case-meta">
                <span>{{ customerName(item.customerId) }}</span>
                <span v-if="item.description">• {{ item.description }}</span>
              </p>
            </div>
            <div class="case-actions">
              <NcButton v-if="!standalone" type="tertiary" @click="openCaseDetail(item)">
                Öffnen
              </NcButton>
              <NcButton
                type="tertiary-no-background"
                aria-label="Vorgang bearbeiten"
                title="Bearbeiten"
                @click="openEditModal(item)"
              >
                <template #icon>
                  <Pencil :size="18" />
                </template>
              </NcButton>
              <NcButton
                type="tertiary-no-background"
                aria-label="Vorgang löschen"
                title="Löschen"
                @click="removeItem(item)"
              >
                <template #icon>
                  <TrashCanOutline :size="18" />
                </template>
              </NcButton>
            </div>
          </header>

          <div v-if="standalone" class="case-detail">
            <div class="detail-grid">
            <div>
              <h3>Vorgangsdaten</h3>
                <table class="detail-table">
                  <tbody>
                    <tr>
                      <th>Vorgangs-ID</th>
                      <td>{{ item.caseNumber || '–' }}</td>
                    </tr>
                    <tr>
                      <th>Beschreibung</th>
                      <td>{{ item.description || '–' }}</td>
                    </tr>
                    <tr>
                      <th>Deck</th>
                      <td>{{ item.deckLink || '–' }}</td>
                    </tr>
                    <tr>
                      <th>Kollektiv</th>
                      <td>{{ item.kollektivLink || '–' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div>
                <h3>Aktionen</h3>
                <div class="detail-actions">
                  <NcButton type="primary" @click="openCreateElementModal(item)">
                    Neue Korrespondenz/Notiz
                  </NcButton>
                  <NcButton type="secondary" @click="openCreateInvoice(item)">
                    Neue Rechnung
                  </NcButton>
                  <NcButton type="secondary" @click="openCreateOffer(item)">
                    Neues Angebot
                  </NcButton>
                </div>
              </div>
            </div>

            <hr class="detail-divider" />

            <div class="invoices">
              <div class="list-header">
                <h3>
                  Rechnungen
                  <span class="section-meta">
                    (Offen {{ invoiceOpenCount }}, Bezahlt {{ invoicePaidCount }}, Umsatz {{ formatPrice(invoiceRevenueCents) }})
                  </span>
                </h3>
                <div class="list-actions">
                  <NcButton type="tertiary" @click="toggleInvoices">
                    {{ showInvoices ? 'Schließen' : 'Öffnen' }}
                  </NcButton>
                  <NcButton type="secondary" @click="openCreateInvoice(item)">
                    Neue Rechnung
                  </NcButton>
                </div>
              </div>

              <div v-if="showInvoices && acceptedOffers.length" class="billing-summary">
                <div
                  v-for="offer in acceptedOffers"
                  :key="offer.id"
                  class="billing-summary__item"
                >
                  <h4>Auftrag {{ offer.number || 'Angebot' }}</h4>
                  <p>Auftragssumme: {{ formatPrice(offer.totalCents) }}</p>
                  <p>Bisher abgerechnet (Abschläge): {{ formatPrice(advanceTotal(offer.id)) }}</p>
                  <p>Rest: {{ formatPrice(offerRemaining(offer)) }}</p>
                  <div v-if="advanceInvoices(offer.id).length" class="billing-summary__list">
                    <p><strong>Abschlagsrechnungen</strong></p>
                    <ul>
                      <li v-for="invoice in advanceInvoices(offer.id)" :key="invoice.id">
                        {{ invoice.number || '–' }} • {{ formatDate(invoice.issueDate) }} •
                        {{ formatPrice(invoice.totalCents) }}
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <NcEmptyContent
                v-if="showInvoices && invoices.length === 0 && invoicesCaseId === item.id"
                name="Keine Rechnungen"
                description="Erstelle eine Rechnung für diesen Vorgang."
              />

              <table v-else-if="showInvoices" class="table">
                <thead>
                  <tr>
                    <th>Nummer</th>
                    <th>Datum</th>
                    <th class="price">Gesamt</th>
                    <th>Status</th>
                    <th class="actions">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="invoice in invoices" :key="invoice.id">
                    <td class="name">{{ invoice.number || '–' }}</td>
                    <td>{{ formatDate(invoice.issueDate) }}</td>
                    <td class="price">{{ formatPrice(invoice.totalCents) }}</td>
                    <td>{{ invoiceStatusLabel(invoice.status) }}</td>
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
                        @click="downloadInvoicePdf(invoice)"
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
                        @click="markInvoicePaid(invoice)"
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
                </tbody>
              </table>

              <p v-if="invoicesError" class="error">{{ invoicesError }}</p>
            </div>

            <hr class="section-divider" />

            <div class="offers">
              <div class="list-header">
                <h3>
                  Angebote
                  <span class="section-meta">({{ offers.length }})</span>
                </h3>
                <div class="list-actions">
                  <NcButton type="tertiary" @click="toggleOffers">
                    {{ showOffers ? 'Schließen' : 'Öffnen' }}
                  </NcButton>
                  <NcButton type="secondary" @click="openCreateOffer(item)">
                    Neues Angebot
                  </NcButton>
                </div>
              </div>

              <NcEmptyContent
                v-if="showOffers && offers.length === 0 && offersCaseId === item.id"
                name="Keine Angebote"
                description="Erstelle ein Angebot für diesen Vorgang."
              />

              <table v-else-if="showOffers" class="table">
                <thead>
                  <tr>
                    <th>Nummer</th>
                    <th>Datum</th>
                    <th class="price">Gesamt</th>
                    <th>Status</th>
                    <th class="actions">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="offer in offers" :key="offer.id">
                    <td class="name">{{ offer.number || '–' }}</td>
                    <td>{{ formatDate(offer.issueDate) }}</td>
                    <td class="price">{{ formatPrice(offer.totalCents) }}</td>
                    <td>{{ offer.status || '–' }}</td>
                    <td class="actions">
                      <NcButton
                        type="tertiary-no-background"
                        aria-label="Angebot bearbeiten"
                        title="Bearbeiten"
                        @click="openEditOffer(offer)"
                      >
                        <template #icon>
                          <Pencil :size="18" />
                        </template>
                      </NcButton>
                      <NcButton
                        type="tertiary-no-background"
                        aria-label="Angebot senden"
                        title="Senden"
                        @click="openSendOfferModal(offer)"
                      >
                        <template #icon>
                          <EmailOutline :size="18" />
                        </template>
                      </NcButton>
                      <NcButton
                        type="tertiary-no-background"
                        aria-label="Angebot als PDF herunterladen"
                        title="PDF herunterladen"
                        @click="downloadOfferPdf(offer)"
                      >
                        <template #icon>
                          <DownloadBoxOutline :size="18" />
                        </template>
                      </NcButton>
                      <NcButton
                        type="tertiary-no-background"
                        aria-label="Angebot löschen"
                        title="Löschen"
                        @click="removeOffer(offer)"
                      >
                        <template #icon>
                          <TrashCanOutline :size="18" />
                        </template>
                      </NcButton>
                    </td>
                  </tr>
                </tbody>
              </table>

              <p v-if="offersError" class="error">{{ offersError }}</p>
            </div>

            <hr class="section-divider" />

            <div class="elements">
              <div class="list-header">
                <h3>
                  Korrespondenz/Notizen
                  <span class="section-meta">({{ elements.length }})</span>
                </h3>
                <div class="list-actions">
                  <NcButton type="tertiary" @click="toggleElements">
                    {{ showElements ? 'Schließen' : 'Öffnen' }}
                  </NcButton>
                  <NcButton type="secondary" @click="openCreateElementModal(item)">
                    Neue Korrespondenz/Notiz
                  </NcButton>
                </div>
              </div>

              <NcEmptyContent
                v-if="showElements && elements.length === 0 && elementsCaseId === item.id"
                name="Keine Korrespondenz/Notizen"
                description="Füge Korrespondenz/Notizen zu diesem Vorgang hinzu."
              />

              <table v-else-if="showElements" class="table">
                <thead>
                  <tr>
                    <th>Korrespondenz/Notiz</th>
                    <th>Notiz</th>
                    <th>Anhang</th>
                    <th class="actions">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="element in elements" :key="element.id">
                    <td class="name">{{ element.name || '–' }}</td>
                    <td class="description">{{ element.note || '–' }}</td>
                    <td>{{ element.attachmentPath || '–' }}</td>
                    <td class="actions">
                      <NcButton
                        type="tertiary-no-background"
                        aria-label="Korrespondenz/Notiz bearbeiten"
                        title="Bearbeiten"
                        @click="openEditElementModal(item, element)"
                      >
                        <template #icon>
                          <Pencil :size="18" />
                        </template>
                      </NcButton>
                      <NcButton
                        type="tertiary-no-background"
                        aria-label="Korrespondenz/Notiz löschen"
                        title="Löschen"
                        @click="removeElement(element)"
                      >
                        <template #icon>
                          <TrashCanOutline :size="18" />
                        </template>
                      </NcButton>
                    </td>
                  </tr>
                </tbody>
              </table>

              <p v-if="elementError" class="error">{{ elementError }}</p>
            </div>
          </div>
        </article>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
    </div>

    <NcModal v-if="showCaseModal" size="normal" @close="closeCaseModal">
      <div class="modal__content">
        <h2>{{ editingId ? 'Vorgang bearbeiten' : 'Neuer Vorgang' }}</h2>

        <div class="form-group">
          <label for="caseCustomer">Kunde</label>
          <NcSelect
            id="caseCustomer"
            v-model="form.customerId"
            :options="customerOptions"
            :reduce="(option) => option.value"
            :append-to-body="false"
            :clearable="false"
            input-label="Kunde"
            :label-outside="true"
            placeholder="Bitte auswählen"
          />
          <span v-if="customers.length === 0" class="hint">
            Lege zuerst einen Kunden an.
          </span>
        </div>

        <div class="form-group">
          <NcTextField label="Name" :value.sync="form.name" />
        </div>
        <div class="form-group">
          <NcTextArea label="Beschreibung" :value.sync="form.description" />
        </div>
        <div class="form-group">
          <NcTextField label="Nextcloud Deck Link" :value.sync="form.deckLink" />
        </div>
        <div class="form-group">
          <NcTextField label="Nextcloud Kollektiv Link" :value.sync="form.kollektivLink" />
        </div>

        <div class="actions">
          <NcButton type="primary" :disabled="saving || !canSave" @click="save">
            {{ editingId ? 'Aktualisieren' : 'Anlegen' }}
          </NcButton>
          <NcButton type="secondary" @click="closeCaseModal">Abbrechen</NcButton>
          <span v-if="saving" class="hint">Speichere…</span>
          <span v-if="saved" class="success">Gespeichert</span>
          <span v-if="error" class="error">{{ error }}</span>
        </div>
      </div>
    </NcModal>

    <NcModal v-if="showElementModal" size="normal" @close="closeElementModal">
      <div class="modal__content">
        <h2>{{ editingElementId ? 'Korrespondenz/Notiz bearbeiten' : 'Neue Korrespondenz/Notiz' }}</h2>
        <p class="subline">Für Vorgang: {{ elementCaseName }}</p>

        <div class="form-group">
          <NcTextField label="Name" :value.sync="elementForm.name" />
        </div>
        <div class="form-group">
          <NcTextArea label="Notiz" :value.sync="elementForm.note" />
        </div>
        <div class="form-group">
          <NcTextField label="Anhang" :value.sync="elementForm.attachmentPath" />
          <div class="attachment-actions">
            <NcButton type="secondary" @click="openFilePicker">
              Aus Dateien wählen
            </NcButton>
            <NcButton type="secondary" @click="triggerUpload">
              Datei hochladen
            </NcButton>
            <NcButton
              v-if="elementForm.attachmentPath"
              type="tertiary-no-background"
              @click="clearAttachment"
            >
              Entfernen
            </NcButton>
            <input
              ref="attachmentInput"
              class="sr-only"
              type="file"
              @change="handleAttachmentUpload"
            />
          </div>
        </div>

        <div class="actions">
          <NcButton
            type="primary"
            :disabled="savingElement || !canSaveElement"
            @click="saveElement"
          >
            {{ editingElementId ? 'Aktualisieren' : 'Anlegen' }}
          </NcButton>
          <NcButton type="secondary" @click="closeElementModal">Abbrechen</NcButton>
          <span v-if="savingElement" class="hint">Speichere…</span>
          <span v-if="savedElement" class="success">Gespeichert</span>
          <span v-if="elementError" class="error">{{ elementError }}</span>
        </div>
      </div>
    </NcModal>

    <NcModal v-if="showSendOfferModal" size="normal" @close="closeSendOfferModal">
      <div class="modal__content">
        <h2>Angebot verschicken</h2>
        <template v-if="isDirectEmail">
          <p>Die E-Mail wird direkt über den SMTP-Server versendet.</p>
          <div class="email-preview">
            <p><strong>Empfänger:</strong> {{ sendOfferPreview?.to?.join(', ') || '–' }}</p>
            <p v-if="effectiveFromEmail"><strong>Absender:</strong> {{ effectiveFromEmail }}</p>
            <p v-if="effectiveReplyToEmail"><strong>Antwort an:</strong> {{ effectiveReplyToEmail }}</p>
            <p><strong>Betreff:</strong> {{ sendOfferPreview?.subject || '–' }}</p>
            <p><strong>Anhang:</strong> {{ sendOfferPreview?.attachmentName || '–' }}</p>
            <pre class="email-body">{{ sendOfferPreview?.body || '' }}</pre>
          </div>
          <div class="actions">
            <NcButton
              type="primary"
              :disabled="!canSendOfferEmail || sendingOffer"
              @click="sendOfferDirect"
            >
              E-Mail senden
            </NcButton>
            <NcButton type="secondary" @click="closeSendOfferModal">Abbrechen</NcButton>
            <span v-if="sendingOffer" class="hint">Sende…</span>
            <span v-if="sentOfferEmail" class="success">Gesendet</span>
            <span v-if="sendOfferError" class="error">{{ sendOfferError }}</span>
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
              :disabled="!canSendOfferEmail"
              @click="openOfferMailto"
            >
              Mailvorlage erstellen
            </NcButton>
            <NcButton type="secondary" @click="closeSendOfferModal">Schließen</NcButton>
          </div>
          <p class="hint">
            Hinweis: Das PDF muss manuell als Anhang hinzugefügt werden.
          </p>
        </template>
      </div>
    </NcModal>

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
import {
  NcButton,
  NcCheckboxRadioSwitch,
  NcEmptyContent,
  NcLoadingIcon,
  NcModal,
} from '@nextcloud/vue'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.mjs'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.mjs'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import DownloadBoxOutline from 'vue-material-design-icons/DownloadBoxOutline.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import EmailOutline from 'vue-material-design-icons/EmailOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import axios from '@nextcloud/axios'
import { generateRemoteUrl } from '@nextcloud/router'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { createCase, deleteCase, getCases, updateCase } from '../api/cases'
import {
  createCaseElement,
  deleteCaseElement,
  getCaseElements,
  updateCaseElement,
} from '../api/caseElements'
import { getCustomers } from '../api/customers'
import { getFiscalYears } from '../api/fiscalYears'
import {
  deleteInvoice,
  getInvoicePdfUrl,
  getInvoices,
  sendInvoiceEmail,
  updateInvoice,
} from '../api/invoices'
import { deleteOffer, getOfferPdfUrl, getOffers, sendOfferEmail, updateOffer } from '../api/offers'
import { getCompany, getEmailBehavior, getTexts } from '../api/settings'

export default {
  name: 'Cases',
  props: {
    standalone: {
      type: Boolean,
      default: false,
    },
    focusCaseId: {
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
    NcSelect,
    NcTextField,
    NcTextArea,
    CheckCircleOutline,
    DownloadBoxOutline,
    Pencil,
    EmailOutline,
    TrashCanOutline,
  },
  data() {
    return {
      loading: true,
      saving: false,
      saved: false,
      error: '',
      items: [],
      customers: [],
      filterCustomerId: '',
      editingId: null,
      expandedId: null,
      showCaseModal: false,
      form: {
        customerId: '',
        name: '',
        description: '',
        deckLink: '',
        kollektivLink: '',
      },
      elements: [],
      elementsCaseId: null,
      invoices: [],
      invoicesCaseId: null,
      invoicesError: '',
      offers: [],
      offersCaseId: null,
      offersError: '',
      texts: null,
      emailBehavior: null,
      showSendOfferModal: false,
      sendOfferTarget: null,
      sendOfferMailto: '',
      sendOfferPreview: null,
      sendOfferError: '',
      sendingOffer: false,
      sentOfferEmail: false,
      showInvoices: false,
      showOffers: false,
      showElements: false,
      showSendInvoiceModal: false,
      sendInvoiceTarget: null,
      sendInvoiceMailto: '',
      sendInvoicePreview: null,
      sendInvoiceError: '',
      sendingInvoice: false,
      sentInvoiceEmail: false,
      savingElement: false,
      savedElement: false,
      elementError: '',
      editingElementId: null,
      showElementModal: false,
      elementForm: {
        name: '',
        note: '',
        attachmentPath: '',
      },
      elementCaseName: '',
      attachmentUploading: false,
      showSetupModal: false,
      companyMissing: false,
      fiscalYearsMissing: false,
    }
  },
  computed: {
    canSave() {
      return this.form.customerId !== '' && this.form.name.trim() !== ''
    },
    canSaveElement() {
      return this.elementForm.name.trim() !== ''
    },
    canSendOfferEmail() {
      if (this.isDirectEmail) {
        return !!this.sendOfferPreview?.to?.length
      }
      return !!this.sendOfferMailto
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
    customerOptions() {
      return this.customers.map((customer) => ({
        label: customer.company || 'Unbenannt',
        value: String(customer.id),
      }))
    },
    customerFilterOptions() {
      return [
        { label: 'Alle Kunden', value: '' },
        ...this.customerOptions,
      ]
    },
    invoiceOpenCount() {
      return this.invoices.filter((invoice) => invoice.status !== 'paid').length
    },
    invoicePaidCount() {
      return this.invoices.filter((invoice) => invoice.status === 'paid').length
    },
    invoiceRevenueCents() {
      return this.invoices.reduce((sum, invoice) => sum + (invoice.totalCents || 0), 0)
    },
    acceptedOffers() {
      return this.offers.filter((offer) => (offer.status || '').toLowerCase() === 'accepted')
    },
    itemsToShow() {
      if (!this.standalone || !this.focusCaseId) {
        return this.items
      }
      const focusId = Number(this.focusCaseId)
      return this.items.filter((item) => item.id === focusId)
    },
  },
  watch: {
    filterCustomerId() {
      this.loadCases()
    },
    '$route.query.caseId': {
      handler() {
        if (!this.standalone) {
          this.applyRouteExpand()
        }
      },
    },
    focusCaseId() {
      if (this.standalone) {
        this.applyStandaloneFocus()
      }
    },
  },
  async mounted() {
    await this.loadAll()
    if (this.standalone) {
      await this.applyStandaloneFocus()
    } else {
      await this.applyRouteExpand()
    }
  },
  methods: {
    async applyStandaloneFocus() {
      if (!this.focusCaseId) {
        return
      }
      const focusId = Number(this.focusCaseId)
      if (this.items.length === 0) {
        await this.loadCases()
      }
      const item = this.items.find((entry) => entry.id === focusId)
      if (!item) {
        return
      }
      this.expandedId = focusId
      this.showInvoices = false
      this.showOffers = false
      this.showElements = false
      await Promise.all([
        this.loadElements(focusId),
        this.loadInvoices(focusId),
        this.loadOffers(focusId),
      ])
    },
    async applyRouteExpand() {
      const { caseId, customerId, expand } = this.$route.query || {}
      if (!caseId) {
        return
      }
      if (customerId && String(this.filterCustomerId) !== String(customerId)) {
        this.filterCustomerId = String(customerId)
        await this.loadCases()
      } else if (this.items.length === 0) {
        await this.loadCases()
      }

      if (expand === '0') {
        return
      }

      const targetId = Number(caseId)
      const item = this.items.find((entry) => entry.id === targetId)
      if (!item) {
        return
      }
      this.expandedId = targetId
      this.showInvoices = false
      this.showOffers = false
      this.showElements = false
      await Promise.all([
        this.loadElements(targetId),
        this.loadInvoices(targetId),
        this.loadOffers(targetId),
      ])
    },
    async loadAll() {
      this.loading = true
      this.error = ''
      try {
        const [customers, cases, texts, emailBehavior, company, fiscalYears] = await Promise.all([
          getCustomers(),
          getCases(this.filterCustomerId),
          getTexts(),
          getEmailBehavior(),
          getCompany(),
          getFiscalYears(),
        ])
        this.customers = Array.isArray(customers) ? customers : []
        this.items = Array.isArray(cases) ? cases : []
        this.texts = texts || {}
        this.emailBehavior = emailBehavior || { mode: 'manual' }
        this.updateSetupState(company, fiscalYears)
      } catch (e) {
        this.error = 'Vorgänge konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    async loadCases() {
      this.loading = true
      this.error = ''
      try {
        const data = await getCases(this.filterCustomerId)
        this.items = Array.isArray(data) ? data : []
        if (this.expandedId && !this.items.find((item) => item.id === this.expandedId)) {
          this.expandedId = null
          this.elements = []
          this.elementsCaseId = null
          this.invoices = []
          this.invoicesCaseId = null
          this.invoicesError = ''
          this.offers = []
          this.offersCaseId = null
        }
      } catch (e) {
        this.error = 'Vorgänge konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    async loadElements(caseId) {
      this.elementError = ''
      this.elementsCaseId = caseId
      try {
        const data = await getCaseElements(caseId)
        this.elements = Array.isArray(data) ? data : []
      } catch (e) {
        this.elementError = 'Korrespondenz/Notizen konnten nicht geladen werden.'
      }
    },
    async loadInvoices(caseId) {
      this.invoicesError = ''
      this.invoicesCaseId = caseId
      try {
        const data = await getInvoices({ caseId })
        const items = Array.isArray(data) ? data : []
        this.invoices = items.sort(
          (a, b) => (a.issueDate || 0) - (b.issueDate || 0)
        )
      } catch (e) {
        this.invoicesError = 'Rechnungen konnten nicht geladen werden.'
      }
    },
    async loadOffers(caseId) {
      this.offersError = ''
      this.offersCaseId = caseId
      try {
        const data = await getOffers({ caseId })
        const items = Array.isArray(data) ? data : []
        this.offers = items.sort(
          (a, b) => (a.issueDate || 0) - (b.issueDate || 0)
        )
      } catch (e) {
        this.offersError = 'Angebote konnten nicht geladen werden.'
      }
    },
    async markOfferSent(offer) {
      const payload = {
        caseId: offer.caseId ?? null,
        customerId: offer.customerId ?? null,
        number: offer.number ?? null,
        status: 'sent',
        issueDate: offer.issueDate ?? null,
        validUntil: offer.validUntil ?? null,
        greetingText: offer.greetingText ?? null,
        extraText: offer.extraText ?? null,
        footerText: offer.footerText ?? null,
        subtotalCents: offer.subtotalCents ?? null,
        taxCents: offer.taxCents ?? null,
        totalCents: offer.totalCents ?? null,
        taxRateBp: offer.taxRateBp ?? null,
        isSmallBusiness: offer.isSmallBusiness ?? false,
      }
      try {
        const saved = await updateOffer(offer.id, payload)
        this.offers = this.offers.map((item) =>
          item.id === offer.id ? saved : item
        )
      } catch (e) {
        this.offersError = 'Status konnte nicht aktualisiert werden.'
      }
    },
    resetForm() {
      this.editingId = null
      this.form = {
        customerId: '',
        name: '',
        description: '',
        deckLink: '',
        kollektivLink: '',
      }
      this.error = ''
    },
    openCreateModal() {
      this.resetForm()
      this.showCaseModal = true
    },
    openCaseDetail(item) {
      this.$router.push({ name: 'case-detail', params: { id: item.id } })
    },
    openCreateInvoice(item) {
      this.$router.push({
        name: 'invoices-new',
        query: {
          caseId: item.id,
          customerId: item.customerId,
          returnTo: this.standalone ? 'case' : '',
        },
      })
    },
    openCreateOffer(item) {
      this.$router.push({
        name: 'offers-new',
        query: {
          caseId: item.id,
          customerId: item.customerId,
          returnTo: this.standalone ? 'case' : '',
        },
      })
    },
    openEditInvoice(invoice) {
      this.$router.push({
        name: 'invoices-edit',
        params: { id: invoice.id },
        query: this.standalone
          ? { returnTo: 'case', caseId: invoice.caseId }
          : {},
      })
    },
    openEditOffer(offer) {
      this.$router.push({
        name: 'offers-edit',
        params: { id: offer.id },
        query: this.standalone
          ? { returnTo: 'case', caseId: offer.caseId }
          : {},
      })
    },
    async markInvoicePaid(invoice) {
      if (invoice.status === 'paid') {
        return
      }
      const payload = {
        number: invoice.number ?? null,
        status: 'paid',
        caseId: invoice.caseId ?? null,
        customerId: invoice.customerId ?? null,
        invoiceType: invoice.invoiceType || 'standard',
        relatedOfferId: invoice.relatedOfferId || null,
        servicePeriodStart: invoice.servicePeriodStart || null,
        servicePeriodEnd: invoice.servicePeriodEnd || null,
        issueDate: invoice.issueDate ?? null,
        dueDate: invoice.dueDate ?? null,
        greetingText: invoice.greetingText ?? null,
        extraText: invoice.extraText ?? null,
        footerText: invoice.footerText ?? null,
        subtotalCents: invoice.subtotalCents ?? null,
        taxCents: invoice.taxCents ?? null,
        totalCents: invoice.totalCents ?? null,
        taxRateBp: invoice.taxRateBp ?? null,
        isSmallBusiness: invoice.isSmallBusiness ?? false,
      }
      try {
        const saved = await updateInvoice(invoice.id, payload)
        this.invoices = this.invoices.map((item) =>
          item.id === invoice.id ? saved : item
        )
      } catch (e) {
        this.invoicesError = 'Status konnte nicht aktualisiert werden.'
      }
    },
    async removeInvoice(invoice) {
      if (!window.confirm('Rechnung wirklich löschen?')) {
        return
      }
      try {
        await deleteInvoice(invoice.id)
        this.invoices = this.invoices.filter((item) => item.id !== invoice.id)
      } catch (e) {
        this.invoicesError = 'Rechnung konnte nicht gelöscht werden.'
      }
    },
    downloadInvoicePdf(invoice) {
      const url = getInvoicePdfUrl(invoice.id)
      window.open(url, '_blank')
    },
    openSendOfferModal(offer) {
      this.sendOfferTarget = offer
      this.sendOfferError = ''
      this.sentOfferEmail = false
      const emailData = this.buildOfferEmailData(offer)
      this.sendOfferPreview = {
        ...emailData,
        attachmentName: this.buildOfferAttachmentName(offer),
      }
      this.sendOfferMailto = this.buildOfferMailtoFromData(emailData)
      if (!this.isDirectEmail) {
        const pdfUrl = getOfferPdfUrl(offer.id)
        window.open(pdfUrl, '_blank')
      }
      this.showSendOfferModal = true
      if ((offer.status || '').toLowerCase() !== 'sent') {
        this.markOfferSent(offer)
      }
    },
    closeSendOfferModal() {
      this.showSendOfferModal = false
      this.sendOfferTarget = null
      this.sendOfferMailto = ''
      this.sendOfferPreview = null
      this.sendOfferError = ''
      this.sendingOffer = false
      this.sentOfferEmail = false
    },
    openOfferMailto() {
      if (!this.sendOfferMailto) {
        return
      }
      window.location.href = this.sendOfferMailto
    },
    downloadOfferPdf(offer) {
      const url = getOfferPdfUrl(offer.id)
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
    buildOfferMailtoFromData(data) {
      const to = data.to.join(',')
      const subject = encodeURIComponent(data.subject)
      const body = encodeURIComponent(data.body)
      const base = to ? `mailto:${to}` : 'mailto:'
      return `${base}?subject=${subject}&body=${body}`
    },
    buildOfferEmailData(offer) {
      const customer = this.customers.find((entry) => entry.id === offer.customerId)
      const caseItem = this.items.find((entry) => entry.id === offer.caseId)
      const contact = (customer?.contactName || '').trim()
      const salutation = contact ? `Hallo ${contact}` : 'Sehr geehrte Damen und Herren'
      const context = {
        offerNumber: offer.number || '',
        customerName: customer?.company || '',
        customerContact: contact,
        customerSalutation: salutation,
        caseName: caseItem?.name || '',
        total: this.formatPrice(offer.totalCents),
        issueDate: offer.issueDate
          ? new Date(offer.issueDate * 1000).toLocaleDateString('de-DE')
          : '',
      }

      const subjectTemplate = this.texts?.offerEmailSubject || 'Angebot {{offerNumber}}'
      const bodyTemplate =
        this.texts?.offerEmailBody ||
        '{{customerSalutation}},\n\nanbei das Angebot {{offerNumber}}.\n\nViele Grüße'

      const to = customer?.email ? [customer.email] : []
      return {
        to,
        subject: this.applyTemplate(subjectTemplate, context),
        body: this.applyTemplate(bodyTemplate, context),
      }
    },
    buildOfferAttachmentName(offer) {
      const suffix = offer.number || offer.id
      return `angebot-${suffix}.pdf`
    },
    async sendOfferDirect() {
      if (!this.sendOfferTarget || !this.sendOfferPreview) {
        return
      }
      this.sendingOffer = true
      this.sendOfferError = ''
      this.sentOfferEmail = false
      try {
        await sendOfferEmail(this.sendOfferTarget.id, {
          to: this.sendOfferPreview.to,
          subject: this.sendOfferPreview.subject,
          body: this.sendOfferPreview.body,
        })
        this.sentOfferEmail = true
        window.setTimeout(() => {
          this.closeSendOfferModal()
        }, 700)
      } catch (e) {
        this.sendOfferError = 'E-Mail konnte nicht gesendet werden.'
      } finally {
        this.sendingOffer = false
      }
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
      const caseItem = this.items.find((entry) => entry.id === invoice.caseId)
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
    async removeOffer(offer) {
      if (!window.confirm('Angebot wirklich löschen?')) {
        return
      }
      try {
        await deleteOffer(offer.id)
        await this.loadOffers(offer.caseId)
      } catch (e) {
        this.offersError = 'Angebot konnte nicht gelöscht werden.'
      }
    },
    openEditModal(item) {
      this.editingId = item.id
      const customer = this.customers.find((entry) => entry.id === item.customerId)
      this.form = {
        customerId: item.customerId ? String(item.customerId) : '',
        name: item.name || '',
        description: item.description || '',
        deckLink: item.deckLink || '',
        kollektivLink: item.kollektivLink || '',
      }
      this.error = ''
      this.showCaseModal = true
    },
    closeCaseModal() {
      this.showCaseModal = false
      this.resetForm()
    },
    async save() {
      if (!this.canSave) {
        this.error = 'Bitte Kunde und Name angeben.'
        return
      }

      this.saving = true
      this.saved = false
      this.error = ''

      const payload = {
        customerId: Number(this.form.customerId),
        name: this.form.name.trim(),
        description: this.form.description.trim(),
        deckLink: this.form.deckLink.trim(),
        kollektivLink: this.form.kollektivLink.trim(),
      }

      try {
        if (this.editingId) {
          const saved = await updateCase(this.editingId, payload)
          this.items = this.items.map((item) =>
            item.id === this.editingId ? saved : item
          )
          if (this.expandedId === saved.id) {
            this.expandedId = saved.id
          }
        } else {
          const saved = await createCase(payload)
          this.items = [...this.items, saved]
          this.expandedId = saved.id
          await this.loadElements(saved.id)
        }

        this.saved = true
        window.setTimeout(() => {
          this.saved = false
        }, 2000)
        this.closeCaseModal()
      } catch (e) {
        this.error = 'Speichern fehlgeschlagen.'
      } finally {
        this.saving = false
      }
    },
    async removeItem(item) {
      if (!window.confirm('Vorgang wirklich löschen?')) {
        return
      }
      this.saving = true
      this.error = ''
      try {
        await deleteCase(item.id)
        this.items = this.items.filter((entry) => entry.id !== item.id)
        if (this.expandedId === item.id) {
          this.expandedId = null
          this.elements = []
          this.elementsCaseId = null
          this.invoices = []
          this.invoicesCaseId = null
          this.offers = []
          this.offersCaseId = null
        }
      } catch (e) {
        this.error = 'Löschen fehlgeschlagen.'
      } finally {
        this.saving = false
      }
    },
    async toggleExpand(item) {
      if (this.expandedId === item.id) {
        this.expandedId = null
        this.invoices = []
        this.invoicesCaseId = null
        this.offers = []
        this.offersCaseId = null
        return
      }
      this.expandedId = item.id
      this.showInvoices = false
      this.showOffers = false
      this.showElements = false
      await Promise.all([
        this.loadElements(item.id),
        this.loadInvoices(item.id),
        this.loadOffers(item.id),
      ])
    },
    formatPrice(value) {
      if (value === null || value === undefined) {
        return '–'
      }
      return `${(Number(value) / 100).toFixed(2)} €`
    },
    formatDate(value) {
      if (!value) {
        return '–'
      }
      const date = new Date(value * 1000)
      return date.toLocaleDateString('de-DE')
    },
    invoiceStatusLabel(status) {
      if (status === 'paid') {
        return 'Bezahlt'
      }
      if (status === 'open') {
        return 'Offen'
      }
      return status || '–'
    },
    normalizeInvoiceType(value) {
      const normalized = (value || '').toString().toLowerCase()
      if (normalized === 'advance') {
        return 'advance'
      }
      if (normalized === 'final') {
        return 'final'
      }
      return 'standard'
    },
    advanceInvoices(offerId) {
      return this.invoices.filter(
        (invoice) =>
          invoice.relatedOfferId === offerId &&
          this.normalizeInvoiceType(invoice.invoiceType) === 'advance'
      )
    },
    advanceTotal(offerId) {
      return this.advanceInvoices(offerId).reduce(
        (sum, invoice) => sum + (invoice.totalCents || 0),
        0
      )
    },
    offerRemaining(offer) {
      if (!offer || offer.totalCents === null || offer.totalCents === undefined) {
        return null
      }
      return offer.totalCents - this.advanceTotal(offer.id)
    },
    openCreateElementModal(item) {
      this.editingElementId = null
      this.elementForm = {
        name: '',
        note: '',
        attachmentPath: '',
      }
      this.elementError = ''
      this.elementsCaseId = item.id
      this.elementCaseName = item.name || 'Unbenannt'
      this.showElementModal = true
      if (this.expandedId !== item.id) {
        this.expandedId = item.id
        this.loadElements(item.id)
        this.loadInvoices(item.id)
        this.loadOffers(item.id)
      }
    },
    toggleInvoices() {
      this.showInvoices = !this.showInvoices
    },
    toggleOffers() {
      this.showOffers = !this.showOffers
    },
    toggleElements() {
      this.showElements = !this.showElements
    },
    openEditElementModal(item, element) {
      this.editingElementId = element.id
      this.elementForm = {
        name: element.name || '',
        note: element.note || '',
        attachmentPath: element.attachmentPath || '',
      }
      this.elementError = ''
      this.elementsCaseId = item.id
      this.elementCaseName = item.name || 'Unbenannt'
      this.showElementModal = true
    },
    closeElementModal() {
      this.showElementModal = false
      this.editingElementId = null
      this.elementForm = {
        name: '',
        note: '',
        attachmentPath: '',
      }
    },
    async saveElement() {
      if (!this.elementsCaseId) {
        this.elementError = 'Bitte zuerst einen Vorgang auswählen.'
        return
      }
      if (!this.canSaveElement) {
        this.elementError = 'Bitte einen Namen angeben.'
        return
      }

      this.savingElement = true
      this.savedElement = false
      this.elementError = ''

      const payload = {
        name: this.elementForm.name.trim(),
        note: this.elementForm.note.trim(),
        attachmentPath: this.elementForm.attachmentPath.trim(),
      }

      try {
        if (this.editingElementId) {
          const saved = await updateCaseElement(this.editingElementId, payload)
          this.elements = this.elements.map((entry) =>
            entry.id === this.editingElementId ? saved : entry
          )
        } else {
          const saved = await createCaseElement(this.elementsCaseId, payload)
          this.elements = [...this.elements, saved]
        }

        this.savedElement = true
        window.setTimeout(() => {
          this.savedElement = false
        }, 2000)
        this.closeElementModal()
      } catch (e) {
        this.elementError = 'Speichern fehlgeschlagen.'
      } finally {
        this.savingElement = false
      }
    },
    clearAttachment() {
      this.elementForm.attachmentPath = ''
    },
    openFilePicker() {
      const handlePicked = (path) => {
        if (Array.isArray(path)) {
          this.elementForm.attachmentPath = path[0] || ''
        } else {
          this.elementForm.attachmentPath = path || ''
        }
      }

      const legacyPicker = window?.OC?.dialogs?.filepicker
      if (legacyPicker) {
        legacyPicker('Datei auswählen', handlePicked, false, null, false)
        return
      }

      const picker = getFilePickerBuilder('Datei auswählen')
        .setMultiSelect(false)
        .build()

      picker.pick().then(handlePicked).catch(() => {})
    },
    triggerUpload() {
      const input = this.$refs.attachmentInput
      if (input && input.click) {
        input.click()
      }
    },
    async handleAttachmentUpload(event) {
      const file = event.target?.files?.[0]
      if (!file) {
        return
      }
      event.target.value = ''

      this.attachmentUploading = true
      try {
        const path = await this.uploadToNextcloud(file)
        this.elementForm.attachmentPath = path
        showSuccess('Datei hochgeladen.')
      } catch (e) {
        showError('Upload fehlgeschlagen.')
      } finally {
        this.attachmentUploading = false
      }
    },
    async uploadToNextcloud(file) {
      const userId = this.getCurrentUserId()
      if (!userId) {
        throw new Error('no-user')
      }
      const base = generateRemoteUrl(`dav/files/${encodeURIComponent(userId)}`)
      const folderUrl = `${base}/NextLedger`
      await axios
        .request({ url: folderUrl, method: 'MKCOL' })
        .catch((err) => {
          const status = err?.response?.status
          if (status !== 405 && status !== 409) {
            throw err
          }
        })

      const safeName = encodeURIComponent(file.name)
      const uploadUrl = `${folderUrl}/${safeName}`
      await axios.put(uploadUrl, file, {
        headers: {
          'Content-Type': file.type || 'application/octet-stream',
        },
      })

      return `/NextLedger/${file.name}`
    },
    updateSetupState(company, fiscalYears) {
      const hasCompanyData = this.hasCompanyData(company)
      const hasFiscalYears = Array.isArray(fiscalYears) && fiscalYears.length > 0
      this.companyMissing = !hasCompanyData
      this.fiscalYearsMissing = !hasFiscalYears
      this.showSetupModal = this.companyMissing && this.fiscalYearsMissing
    },
    hasCompanyData(company) {
      if (!company) {
        return false
      }
      const fields = [
        company.name,
        company.ownerName,
        company.street,
        company.houseNumber,
        company.zip,
        company.city,
        company.email,
        company.phone,
        company.vatId,
        company.taxId,
      ]
      return fields.some((value) => String(value || '').trim() !== '')
    },
    handleSetupClose() {
      if (this.showSetupModal) {
        return
      }
      this.showSetupModal = false
    },
    goToCompanySettings() {
      this.$router.push({ name: 'settings-company' })
    },
    goToFiscalYear() {
      this.$router.push({ name: 'fiscal-year' })
    },
    getCurrentUserId() {
      if (window?.OC?.getCurrentUser) {
        const user = window.OC.getCurrentUser()
        if (user?.uid) {
          return user.uid
        }
      }
      const head = document.querySelector('head')
      return head?.getAttribute('data-user') || ''
    },
    async removeElement(element) {
      if (!window.confirm('Korrespondenz/Notiz wirklich löschen?')) {
        return
      }
      this.savingElement = true
      this.elementError = ''
      try {
        await deleteCaseElement(element.id)
        this.elements = this.elements.filter((entry) => entry.id !== element.id)
      } catch (e) {
        this.elementError = 'Löschen fehlgeschlagen.'
      } finally {
        this.savingElement = false
      }
    },
    customerName(customerId) {
      if (!customerId) {
        return '–'
      }
      const found = this.customers.find((customer) => customer.id === customerId)
      return found ? found.company || 'Unbenannt' : '–'
    },
  },
}
</script>

<style scoped>
.cases {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.subline {
  margin: 4px 0 0;
  color: var(--color-text-lighter, #6b7280);
  font-size: 13px;
}

.content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.filters {
  display: flex;
  align-items: center;
  gap: 12px;
}

.filters label,
.form-group label {
  font-weight: 600;
  font-size: 13px;
}

.filters select,
.form-group select {
  flex: 1;
  min-height: 34px;
  border-radius: 8px;
  border: 1px solid var(--color-border, #e5e7eb);
  background: var(--color-main-background, #fff);
  padding: 6px 10px;
  font-size: 14px;
}

.case-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.case-card {
  background: var(--color-main-background, #fff);
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 12px;
  padding: 10px;
}

.case-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 6px;
}

.case-title {
  margin: 0;
  font-size: 16px;
}

.case-title-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.case-id {
  font-size: 12px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 999px;
  background: var(--color-background-hover, #f3f4f6);
  color: var(--color-text-lighter, #6b7280);
}

.case-meta {
  margin: 2px 0 0;
  color: var(--color-text-lighter, #6b7280);
  font-size: 13px;
}

.case-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.case-detail {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--color-border, #e5e7eb);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
  gap: 12px;
}

.detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.detail-table th,
.detail-table td {
  text-align: left;
  padding: 4px 0;
  vertical-align: top;
}

.detail-table th {
  width: 110px;
  font-weight: 600;
  color: var(--color-text-lighter, #6b7280);
}

.detail-actions {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: flex-start;
}

.detail-divider {
  border: 0;
  border-top: 1px solid var(--color-border, #e5e7eb);
  margin: 6px 0 0;
}

.elements {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.invoices {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.billing-summary {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px;
  background: var(--color-background-dark, #f3f4f6);
  border-radius: 8px;
}

.billing-summary__item h4 {
  margin: 0 0 4px;
}

.billing-summary__list ul {
  margin: 4px 0 0;
  padding-left: 18px;
}

.list-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
}

.list-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-meta {
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-lighter, #6b7280);
  margin-left: 6px;
}

.section-divider {
  border: 0;
  border-top: 1px solid var(--color-border, #e5e7eb);
  margin: 8px 0 4px;
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.table th,
.table td {
  text-align: left;
  padding: 6px 4px;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
}

.invoices .table th {
  background: var(--color-background-dark, #f3f4f6);
}

.table th.actions,
.table td.actions {
  text-align: right;
  white-space: nowrap;
}

.table td.actions > * {
  margin-left: 4px;
}

.case-actions :deep(.button),
.detail-actions :deep(.button),
.attachment-actions :deep(.button) {
  padding: 4px 10px;
  min-height: 30px;
}

.table td.name {
  font-weight: 600;
}

.table td.description {
  color: var(--color-text-lighter, #6b7280);
}

.attachment-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: calc(var(--default-grid-baseline) * 2) 0;
}

.actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.setup-links {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
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

.filters :deep(.v-select),
.form-group :deep(.v-select) {
  flex: 1;
}

.modal__content {
  margin: calc(var(--default-grid-baseline) * 4);
  display: flex;
  flex-direction: column;
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

@media (max-width: 900px) {
  .header {
    flex-direction: column;
    align-items: flex-start;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
