<template>
  <section class="settings">
    <h1>{{ t('title') }}</h1>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="form">
      <div class="new-company-row">
        <NcTextField
          :label="t('newCompanyLabel')"
          :value.sync="newCompanyName"
          :placeholder="t('newCompanyPlaceholder')"
        />
        <NcButton type="secondary" :disabled="creating" @click="createNewCompany">
          {{ t('create') }}
        </NcButton>
      </div>

      <div class="company-list">
        <h2>{{ t('overviewTitle') }}</h2>
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('groupName') }}</th>
              <th>{{ t('company') }}</th>
              <th>{{ t('owner') }}</th>
              <th>{{ t('status') }}</th>
              <th class="actions-cell">{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in companies" :key="entry.id">
              <td>{{ entry.groupName || '—' }}</td>
              <td class="name">{{ entry.name || `${t('company')} #${entry.id}` }}</td>
              <td>{{ entry.ownerName || '—' }}</td>
              <td>
                <span v-if="isActiveCompany(entry.id)" class="status active">{{ t('active') }}</span>
                <span v-else class="status inactive">{{ t('inactive') }}</span>
              </td>
              <td class="actions-cell">
                <NcButton
                  type="tertiary"
                  :disabled="switching || isActiveCompany(entry.id)"
                  @click="switchCompany(entry.id)"
                >
                  {{ t('switch') }}
                </NcButton>
                <NcButton
                  type="tertiary"
                  :disabled="switching || companies.length <= 1 || isActiveCompany(entry.id) || !entry.isOwner"
                  @click="deleteCompanyEntry(entry)"
                >
                  {{ t('delete') }}
                </NcButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <NcTextField :label="t('company')" :value.sync="form.name" />
      <NcTextField :label="t('groupName')" :value.sync="form.groupName" />
      <p class="hint">{{ t('groupNameHint') }}</p>
      <NcTextField :label="t('ownerName')" :value.sync="form.ownerName" />
      <NcTextField :label="t('street')" :value.sync="form.street" />
      <NcTextField :label="t('houseNumber')" :value.sync="form.houseNumber" />
      <NcTextField :label="t('zip')" :value.sync="form.zip" />
      <NcTextField :label="t('city')" :value.sync="form.city" />
      <NcTextField :label="t('email')" :value.sync="form.email" />
      <NcTextField :label="t('phone')" :value.sync="form.phone" />
      <NcTextField :label="t('vatId')" :value.sync="form.vatId" />
      <NcTextField :label="t('taxId')" :value.sync="form.taxId" />
      <NcSelect
        :value="form.currencyCode"
        :options="currencyOptions"
        :reduce="(option) => option.value"
        :append-to-body="false"
        :clearable="false"
        :input-label="t('currencyCode')"
        :label-outside="true"
        @input="form.currencyCode = $event"
      />
      <p class="hint">{{ t('currencyCodeHint') }}</p>
      <div v-if="canManageUsers" class="share-box">
        <NcSelect
          :value="selectedSharedUserId"
          :options="sharedUserOptions"
          :reduce="(option) => option.value"
          :append-to-body="false"
          :clearable="true"
          :input-label="t('sharedUsers')"
          :label-outside="true"
          :placeholder="t('sharedUsersPlaceholder')"
          @input="addSharedUser"
        />
        <div v-if="sharedUsersDisplay.length" class="shared-users-list">
          <div
            v-for="user in sharedUsersDisplay"
            :key="user.userId"
            class="shared-user-chip"
          >
            <span>{{ user.label }}</span>
            <NcButton
              type="tertiary-no-background"
              :aria-label="t('removeSharedUser')"
              @click="removeSharedUser(user.userId)"
            >
              {{ t('delete') }}
            </NcButton>
          </div>
        </div>
        <p class="hint">{{ t('sharedUsersHint') }}</p>
      </div>

      <div class="form-actions">
        <NcButton type="primary" :disabled="saving" @click="save">
          {{ t('save') }}
        </NcButton>
        <span v-if="saving" class="hint">{{ t('saving') }}</span>
        <span v-if="saved" class="success">{{ t('saved') }}</span>
        <span v-if="error" class="error">{{ error }}</span>
      </div>
    </div>
  </section>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.mjs'
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
    NcSelect,
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
      canManageUsers: false,
      availableUsers: [],
      companies: [],
      newCompanyName: '',
      sharedUserIds: [],
      selectedSharedUserId: '',
      form: {
        name: '',
        groupName: '',
        ownerName: '',
        street: '',
        houseNumber: '',
        zip: '',
        city: '',
        email: '',
        phone: '',
        vatId: '',
        taxId: '',
        currencyCode: 'EUR',
      },
    }
  },
  async mounted() {
    await this.load()
  },
  computed: {
    currencyOptions() {
      return [
        { value: 'EUR', label: 'Euro (EUR)' },
        { value: 'USD', label: 'US-Dollar (USD)' },
        { value: 'CHF', label: 'Schweizer Franken (CHF)' },
        { value: 'GBP', label: 'Pfund Sterling (GBP)' },
      ]
    },
    sharedUserOptions() {
      return this.availableUsers
        .filter((entry) => !this.sharedUserIds.includes(entry.userId))
        .map((entry) => ({
          value: entry.userId,
          label: entry.label,
        }))
    },
    sharedUsersDisplay() {
      return this.sharedUserIds.map((userId) => {
        const match = this.availableUsers.find((entry) => entry.userId === userId)
        return {
          userId,
          label: match?.label || userId,
        }
      })
    },
  },
  methods: {
    t(key) {
      return this.$tKey(`settingsCompany.${key}`, key)
    },
    applyCompanyData(data) {
      const safeString = (value) => (value === null || value === undefined ? '' : String(value))
      this.form = {
        ...this.form,
        name: safeString(data.name),
        groupName: safeString(data.groupName),
        ownerName: safeString(data.ownerName),
        street: safeString(data.street),
        houseNumber: safeString(data.houseNumber),
        zip: safeString(data.zip),
        city: safeString(data.city),
        email: safeString(data.email),
        phone: safeString(data.phone),
        vatId: safeString(data.vatId),
        taxId: safeString(data.taxId),
        currencyCode: safeString(data.currencyCode || 'EUR'),
      }
      this.canManageUsers = Boolean(data.canManageUsers)
      this.availableUsers = Array.isArray(data.availableUsers) ? data.availableUsers : []
      this.sharedUserIds = Array.isArray(data.sharedUserIds) ? data.sharedUserIds : []
      this.selectedSharedUserId = ''
    },
    async load() {
      this.loading = true
      this.error = ''
      try {
        await this.refreshCompanies()
        await this.loadActiveCompany()
      } catch (e) {
        this.error = this.t('loadError')
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
      this.applyCompanyData(data)
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
        this.error = this.t('switchError')
      } finally {
        this.switching = false
      }
    },
    async createNewCompany() {
      this.creating = true
      this.error = ''
      try {
        const name = (this.newCompanyName || '').trim() || this.t('newCompanyDefault')
        await createCompany({ name })
        this.newCompanyName = ''
        await this.refreshCompanies()
        await this.loadActiveCompany()
        this.emitCompanyChanged()
      } catch (e) {
        this.error = this.t('createError')
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
        this.error = this.t('activeDeleteError')
        return
      }
      if (!window.confirm(this.t('deleteConfirm').replace('{name}', entry?.name || `#${companyId}`))) {
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
        this.error = this.t('deleteError')
      } finally {
        this.switching = false
      }
    },
    async save() {
      this.saving = true
      this.saved = false
      this.error = ''
      try {
        const payload = {
          ...this.form,
        }
        if (this.canManageUsers) {
          payload.sharedUserIds = this.sharedUserIds
        }
        const data = await saveCompany(payload)
        this.applyCompanyData(data)
        await this.refreshCompanies()
        this.saved = true
        this.emitCompanyChanged()
        window.setTimeout(() => {
          this.saved = false
        }, 2000)
      } catch (e) {
        this.error = this.t('saveError')
      } finally {
        this.saving = false
      }
    },
    emitCompanyChanged() {
      window.dispatchEvent(new CustomEvent('nextledger-company-changed'))
    },
    addSharedUser(userId) {
      const value = String(userId || '').trim()
      this.selectedSharedUserId = ''
      if (!value || this.sharedUserIds.includes(value)) {
        return
      }
      this.sharedUserIds = [...this.sharedUserIds, value]
    },
    removeSharedUser(userId) {
      this.sharedUserIds = this.sharedUserIds.filter((entry) => entry !== userId)
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

.share-box {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.shared-users-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.shared-user-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border: 1px solid var(--color-border, #d1d5db);
  border-radius: 999px;
  background: var(--color-background-dark, #f7f9fb);
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
