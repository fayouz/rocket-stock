<script setup lang="ts">
import type { Movement, Place } from '~/types/stock'

// The ledger of movements, filtered by usage (location / perso), and the CSV exports for the bilan.
const api = useApi()
const toast = useToast()
useHead({ title: `Mouvements · ${useAppConfig().rocket.name}` })

const ALL = 'all'
const usage = ref(ALL)
const usageItems = [{ value: ALL, label: 'Tous usages' }, { value: 'rental', label: 'Location' }, { value: 'personal', label: 'Perso' }]
const { data: places } = await useAsyncData('places', () => api<Place[]>('/api/places'), { default: () => [] })
const placeName = (id: string) => places.value.find(p => p.id === id)?.name ?? 'Lieu'
const query = computed(() => usage.value === ALL ? {} : { usage: usage.value })
const { data: movements } = await useAsyncData('movements', () => api<Movement[]>('/api/movements', { query: query.value }), { watch: [usage], default: () => [] })

async function download(kind: 'movements' | 'consumption') {
  try {
    const blob = await api<Blob>(`/api/export/${kind}`, { query: { ...query.value, format: 'csv' }, responseType: 'blob' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `stock-${kind === 'movements' ? 'mouvements' : 'consommation'}.csv`
    a.click()
    URL.revokeObjectURL(a.href)
  }
  catch (error) {
    toast.add({ title: 'Export impossible', description: apiErrorMessage(error), color: 'error' })
  }
}
</script>

<template>
  <UDashboardPanel id="mouvements">
    <template #header>
      <UDashboardNavbar title="Mouvements">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <USelect v-model="usage" :items="usageItems" class="w-36" />
          <UButton icon="i-lucide-download" variant="soft" label="Mouvements CSV" @click="download('movements')" />
          <UButton icon="i-lucide-receipt-euro" variant="soft" label="Consommation CSV" @click="download('consumption')" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <UCard>
        <ul class="divide-y divide-default text-sm">
          <li v-for="m in movements" :key="m.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
            <span>
              <UBadge variant="subtle" :label="MOVEMENT_LABEL[m.type]" class="mr-2" />
              <b>{{ m.type === 'adjust' ? '=' : (m.type === 'in' ? '+' : '−') }}{{ qtyFr(m.quantity) }} {{ m.unit }}</b> {{ m.itemName }}
              <span class="text-muted"> · {{ placeName(m.placeId) }}{{ m.location.name ? ` · ${m.location.name}` : '' }}{{ m.toLocation ? ` → ${placeName(m.toLocation.placeId)}${m.toLocation.name ? ` · ${m.toLocation.name}` : ''}` : '' }}</span>
            </span>
            <span class="text-muted">{{ USAGE_LABEL[m.usage] }} · {{ m.originApp ?? m.origin }} · {{ whenFr(m.occurredAt) }}{{ m.cost !== null ? ` · ${euroFr(m.cost)}` : '' }}</span>
          </li>
        </ul>
        <p v-if="!movements.length" class="text-sm text-muted">Aucun mouvement.</p>
      </UCard>
    </template>
  </UDashboardPanel>
</template>
