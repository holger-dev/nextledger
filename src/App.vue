<template>
  <NcContent app-name="nextledger">
    <NcAppNavigation>
      <NcAppNavigationItem :to="{ name: 'cases' }" :name="casesNavName" />
      <NcAppNavigationItem :to="{ name: 'invoices' }" :name="t('invoices')" />
      <NcAppNavigationItem :to="{ name: 'offers' }" :name="t('offers')" />
      <NcAppNavigationSpacer />
      <NcAppNavigationItem :to="{ name: 'products' }" :name="t('products')" />
      <NcAppNavigationItem :to="{ name: 'customers' }" :name="t('customers')" />
      <NcAppNavigationItem :to="{ name: 'fiscal-year' }" :name="t('fiscalYear')" />

      <NcAppNavigationSpacer />
      <NcAppNavigationSettings :name="t('settings')">
        <NcAppNavigationItem :to="{ name: 'settings-company' }" :name="t('company')" />
        <NcAppNavigationItem :to="{ name: 'settings-texts' }" :name="t('texts')" />
        <NcAppNavigationItem :to="{ name: 'settings-tax' }" :name="t('tax')" />
        <NcAppNavigationItem :to="{ name: 'settings-misc' }" :name="t('banking')" />
        <NcAppNavigationItem :to="{ name: 'settings-documents' }" :name="t('documents')" />
        <NcAppNavigationItem :to="{ name: 'settings-email' }" :name="t('emailBehavior')" />
      </NcAppNavigationSettings>

      <NcAppNavigationSpacer />
      <NcAppNavigationItem :to="{ name: 'settings-help' }" :name="t('help')" />
    </NcAppNavigation>

    <NcAppContent>
      <div class="nextledger-content">
        <router-view v-if="!ownershipRecovery.required" :key="viewKey" />
      </div>
    </NcAppContent>

    <NcModal
      v-if="ownershipRecovery.required"
      size="normal"
      @close="noop"
    >
      <div class="modal__content ownership-recovery">
        <div class="ownership-recovery__header">
          <h2>{{ t('ownershipRecoveryTitle') }}</h2>
          <p class="hint">{{ t('ownershipRecoveryText') }}</p>
        </div>

        <div class="ownership-recovery__panel">
          <div class="ownership-recovery__panel-title">
            {{ t('ownershipRecoveryCompaniesTitle') }}
          </div>
          <table class="table recovery-table">
            <thead>
              <tr>
                <th>{{ t('company') }}</th>
                <th>{{ t('group') }}</th>
                <th>{{ t('owner') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="company in ownershipRecovery.companies" :key="company.id">
                <td>{{ company.name || `${t('company')} #${company.id}` }}</td>
                <td>{{ company.groupName || '—' }}</td>
                <td>{{ company.ownerName || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="ownership-recovery__panel">
          <div class="ownership-recovery__panel-title">
            {{ t('ownershipRecoveryAssignTitle') }}
          </div>
          <NcSelect
            :value="ownershipRecovery.userId"
            :options="ownershipUserOptions"
            :reduce="(option) => option.value"
            :append-to-body="false"
            :clearable="false"
            :input-label="t('ownershipRecoveryUserLabel')"
            :label-outside="true"
            @input="ownershipRecovery.userId = $event"
          />
          <p class="hint">{{ t('ownershipRecoveryUserHint') }}</p>
        </div>

        <div class="form-actions ownership-recovery__actions">
          <NcButton type="primary" :disabled="ownershipRecovery.saving || !ownershipRecovery.userId" @click="claimOwnershipRecovery">
            {{ t('ownershipRecoveryAction') }}
          </NcButton>
          <span v-if="ownershipRecovery.saving" class="hint">{{ t('saving') }}</span>
          <span v-if="ownershipRecovery.error" class="error">{{ ownershipRecovery.error }}</span>
        </div>
      </div>
    </NcModal>
  </NcContent>
</template>

<script>
import {
  NcContent,
  NcAppContent,
  NcAppNavigation,
  NcAppNavigationItem,
  NcAppNavigationSettings,
  NcButton,
  NcModal,
} from '@nextcloud/vue'
import NcAppNavigationSpacer from '@nextcloud/vue/dist/Components/NcAppNavigationSpacer.mjs'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.mjs'
import {
  claimCompanyOwnershipRecovery,
  getCompanies,
  getCompanyOwnershipRecovery,
} from './api/settings'

export default {
  name: 'NextLedgerApp',
  components: {
    NcContent,
    NcAppContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSpacer,
    NcAppNavigationSettings,
    NcButton,
    NcModal,
    NcSelect,
  },
  data() {
    return {
      activeCompanyName: '',
      companyContextVersion: 0,
      ownershipRecovery: {
        required: false,
        companies: [],
        userId: '',
        availableUsers: [],
        saving: false,
        error: '',
      },
    }
  },
  computed: {
    casesNavName() {
      const base = this.t('cases')
      return this.activeCompanyName
        ? `${base} · ${this.activeCompanyName}`
        : base
    },
    viewKey() {
      return `${this.$route.fullPath}:${this.companyContextVersion}`
    },
    ownershipUserOptions() {
      return this.ownershipRecovery.availableUsers.map((entry) => ({
        value: entry.userId,
        label: entry.label,
      }))
    },
  },
  async created() {
    await this.loadOwnershipRecovery()
    if (!this.ownershipRecovery.required) {
      await this.loadCompanyContext()
    }
    window.addEventListener('nextledger-company-changed', this.handleCompanyChanged)
  },
  beforeDestroy() {
    window.removeEventListener('nextledger-company-changed', this.handleCompanyChanged)
  },
  methods: {
    t(key) {
      return this.$tKey(`app.${key}`, key)
    },
    noop() {},
    async handleCompanyChanged() {
      if (this.ownershipRecovery.required) {
        return
      }
      await this.loadCompanyContext()
      this.companyContextVersion += 1
    },
    async loadOwnershipRecovery() {
      try {
        const data = await getCompanyOwnershipRecovery()
        this.ownershipRecovery.required = Boolean(data.required)
        this.ownershipRecovery.companies = Array.isArray(data.companies) ? data.companies : []
        this.ownershipRecovery.userId = String(data.currentUserId || '')
        this.ownershipRecovery.availableUsers = Array.isArray(data.availableUsers) ? data.availableUsers : []
        this.ownershipRecovery.error = ''
      } catch (e) {
        this.ownershipRecovery.required = false
        this.ownershipRecovery.companies = []
      }
    },
    async claimOwnershipRecovery() {
      this.ownershipRecovery.saving = true
      this.ownershipRecovery.error = ''
      try {
        const data = await claimCompanyOwnershipRecovery({
          userId: String(this.ownershipRecovery.userId || '').trim(),
        })
        this.ownershipRecovery.required = Boolean(data.required)
        this.ownershipRecovery.companies = Array.isArray(data.companies) ? data.companies : []
        this.ownershipRecovery.availableUsers = Array.isArray(data.availableUsers) ? data.availableUsers : []
        if (!this.ownershipRecovery.required) {
          await this.loadCompanyContext()
          this.companyContextVersion += 1
        }
      } catch (e) {
        this.ownershipRecovery.error = this.t('ownershipRecoveryError')
      } finally {
        this.ownershipRecovery.saving = false
      }
    },
    async loadCompanyContext() {
      try {
        const data = await getCompanies()
        if (data?.ownershipRecoveryRequired) {
          this.ownershipRecovery.required = true
          this.ownershipRecovery.companies = Array.isArray(data.legacyCompanies) ? data.legacyCompanies : []
          this.ownershipRecovery.userId = String(data.currentUserId || this.ownershipRecovery.userId || '')
          this.ownershipRecovery.availableUsers = Array.isArray(data.availableUsers) ? data.availableUsers : this.ownershipRecovery.availableUsers
          this.activeCompanyName = ''
          return
        }
        const activeId = Number(data.activeCompanyId)
        const companies = Array.isArray(data.companies) ? data.companies : []
        const active = companies.find((entry) => Number(entry.id) === activeId)
        this.activeCompanyName = active?.name ? String(active.name) : ''
      } catch (e) {
        this.activeCompanyName = ''
      }
    },
  },
}
</script>

<style scoped>
.nextledger-content {
  padding: 24px;
}

.ownership-recovery {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 12px;
}

.recovery-table {
  width: 100%;
}

.ownership-recovery__header {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ownership-recovery__header h2 {
  margin: 0;
}

.ownership-recovery__header .hint {
  margin: 0;
  line-height: 1.5;
}

.ownership-recovery__panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--color-border, #d8dee6);
  border-radius: 16px;
  background: var(--color-main-background, #fff);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
}

.ownership-recovery__panel-title {
  font-weight: 600;
}

.ownership-recovery__actions {
  align-items: center;
  gap: 12px;
  margin-top: 4px;
}

.recovery-table th,
.recovery-table td {
  padding: 10px 12px;
}

:global(.app-nextledger.content) {
  margin: 0;
  margin-top: 0;
  width: 100%;
  height: 100%;
  border-radius: 0;
}

:global(.app-nextledger.content:not(.with-sidebar--full)) {
  position: initial;
}

:global(.nextledger-content .header > div:first-child) {
  padding-left: 28px;
}

:global(.nextledger-content .header h1) {
  font-size: 28px;
  line-height: 1.2;
}

:global(.nextledger-content > section > h1:first-child) {
  padding-left: 28px;
  font-size: 28px;
  line-height: 1.2;
  margin-bottom: 12px;
}

:global(.nextledger-content > section > h1:first-child + .subline) {
  padding-left: 28px;
}

:global(.nextledger-content .header .subline) {
  margin-left: 0;
}

.app-navigation-spacer {
  border-top: 1px solid var(--color-border, #e5e7eb);
  margin: 8px 12px;
  height: 0;
}
</style>
