<template>
  <section>
    <h1>Tägliches</h1>
    <NcEmptyContent
      name="Noch keine Daten"
      description="Hier erscheinen offene Rechnungen und aktuelle Vorgänge."
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
}
</script>
