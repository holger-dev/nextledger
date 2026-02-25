<template>
  <section>
    <h1>{{ t('title') }}</h1>
    <NcEmptyContent
      :name="t('emptyName')"
      :description="t('emptyDescription')"
    />
  </section>
</template>

<script>
import { NcEmptyContent } from '@nextcloud/vue'

export default {
  name: 'DailyDashboard',
  components: {
    NcEmptyContent,
  },
  methods: {
    t(key) {
      return this.$tKey(`dailyDashboard.${key}`, key)
    },
  },
}
</script>
