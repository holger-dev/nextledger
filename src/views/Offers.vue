<template>
  <section class="offers">
    <div class="header">
      <div>
        <h1>Angebote</h1>
        <p class="subline">Übersicht der Angebote.</p>
      </div>
      <NcButton type="primary" @click="openCreateView">Neues Angebot</NcButton>
    </div>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="content">
      <div class="filters">
        <div class="filter-group">
          <NcSelect
            id="offerCustomerFilter"
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
            id="offerSort"
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
        v-if="filteredOffers.length === 0"
        name="Noch keine Angebote"
        description="Erstelle dein erstes Angebot."
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
          <template v-for="offer in filteredOffers">
            <tr :key="offer.id">
              <td class="name">
                <button class="link" type="button" @click="toggleExpand(offer)">
                  {{ offer.number || '–' }}
                </button>
              </td>
              <td>{{ customerName(offer.customerId) }}</td>
              <td>{{ caseName(offer.caseId) }}</td>
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
            <tr v-if="expandedId === offer.id" :key="`detail-${offer.id}`">
              <td colspan="7" class="detail">
                <div class="detail-inner">
                  <h3>Positionen</h3>
                  <NcEmptyContent
                    v-if="offerItems.length === 0"
                    name="Keine Positionen"
                    description="Dieses Angebot hat noch keine Positionen."
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
                      <tr v-for="item in offerItems" :key="item.id">
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

  </section>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.mjs'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import DownloadBoxOutline from 'vue-material-design-icons/DownloadBoxOutline.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { deleteOffer, getOfferPdfUrl, getOffers } from '../api/offers'
import { getOfferItems } from '../api/offerItems'
import { getCases } from '../api/cases'
import { getCustomers } from '../api/customers'

export default {
  name: 'Offers',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcSelect,
    NcTextField,
    DownloadBoxOutline,
    Pencil,
    TrashCanOutline,
  },
  data() {
    return {
      loading: true,
      error: '',
      itemsError: '',
      offers: [],
      customers: [],
      cases: [],
      expandedId: null,
      offerItems: [],
      filterCustomerId: null,
      filterDateFrom: '',
      filterDateTo: '',
      sortBy: 'date_desc',
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
    filteredOffers() {
      const from = this.filterDateFrom ? this.dateInputToUnix(this.filterDateFrom) : null
      const to = this.filterDateTo ? this.dateInputToUnix(this.filterDateTo, true) : null

      const filtered = this.offers.filter((offer) => {
        if (this.filterCustomerId && offer.customerId !== this.filterCustomerId) {
          return false
        }
        if (from && (offer.issueDate || 0) < from) {
          return false
        }
        if (to && (offer.issueDate || 0) > to) {
          return false
        }
        return true
      })

      return filtered.sort((a, b) => this.sortOffers(a, b))
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
        const [offers, customers, cases] = await Promise.all([
          getOffers(),
          getCustomers(),
          getCases(),
        ])
        this.offers = Array.isArray(offers) ? offers : []
        this.customers = Array.isArray(customers) ? customers : []
        this.cases = Array.isArray(cases) ? cases : []
      } catch (e) {
        this.error = 'Angebote konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    openCreateView() {
      this.$router.push({ name: 'offers-new' })
    },
    openEditOffer(offer) {
      this.$router.push({ name: 'offers-edit', params: { id: offer.id } })
    },
    downloadOfferPdf(offer) {
      const url = getOfferPdfUrl(offer.id)
      window.open(url, '_blank')
    },
    sortOffers(a, b) {
      switch (this.sortBy) {
        case 'date_asc':
          return (a.issueDate || 0) - (b.issueDate || 0)
        case 'date_desc':
          return (b.issueDate || 0) - (a.issueDate || 0)
        case 'price_asc':
          return (a.totalCents || 0) - (b.totalCents || 0)
        case 'price_desc':
          return (b.totalCents || 0) - (a.totalCents || 0)
        default:
          return 0
      }
    },
    dateInputToUnix(value, endOfDay = false) {
      if (!value) {
        return null
      }
      const suffix = endOfDay ? 'T23:59:59' : 'T00:00:00'
      const date = new Date(`${value}${suffix}`)
      return Math.floor(date.getTime() / 1000)
    },
    customerName(id) {
      const customer = this.customerMap.get(id)
      return customer ? customer.company || 'Unbenannt' : '–'
    },
    caseName(id) {
      const item = this.caseMap.get(id)
      if (!item) {
        return '–'
      }
      return item.caseNumber
        ? `${item.caseNumber} – ${item.name || 'Unbenannt'}`
        : item.name || 'Unbenannt'
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
    formatDate(value) {
      if (!value) {
        return '–'
      }
      const date = new Date(value * 1000)
      return date.toLocaleDateString('de-DE')
    },
    async toggleExpand(offer) {
      if (this.expandedId === offer.id) {
        this.expandedId = null
        this.offerItems = []
        return
      }
      this.expandedId = offer.id
      this.itemsError = ''
      try {
        const data = await getOfferItems(offer.id)
        this.offerItems = Array.isArray(data) ? data : []
      } catch (e) {
        this.itemsError = 'Positionen konnten nicht geladen werden.'
      }
    },
    async removeOffer(offer) {
      this.error = ''
      try {
        if (!window.confirm('Angebot wirklich löschen?')) {
          return
        }
        await deleteOffer(offer.id)
        await this.load()
      } catch (e) {
        this.error = 'Angebot konnte nicht gelöscht werden.'
      }
    },
  },
}
</script>

<style scoped>
.offers {
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

.link {
  background: none;
  border: none;
  padding: 0;
  color: var(--color-primary, #1a73e8);
  text-decoration: underline;
  cursor: pointer;
  font: inherit;
}

.detail {
  background: var(--color-background-hover, #f6f6f6);
}

.detail-inner {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px 16px;
  padding: 12px 16px;
  background: var(--color-background-dark, #f3f4f6);
  border-radius: 8px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-group :deep(.v-select) {
  flex: 1;
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

@media (max-width: 900px) {
  .header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
