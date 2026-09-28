<script setup lang="ts">
import type { Equipment, Place } from '~/types/stock'

// A place: its stock by sub-location, and its equipment.
const route = useRoute()
const api = useApi()
const id = computed(() => String(route.params.id))
const { data: place } = await useAsyncData(`place-${id.value}`, () => api<Place>(`/api/places/${id.value}`))
const { data: equipment } = await useAsyncData(`place-equipment-${id.value}`, () => api<Equipment[]>(`/api/places/${id.value}/equipment`), { default: () => [] })
useHead({ title: () => `${place.value?.name ?? 'Lieu'} · ${useAppConfig().rocket.name}` })
</script>

<template>
  <UDashboardPanel id="place">
    <template #header>
      <UDashboardNavbar :title="place?.name ?? 'Lieu'">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton icon="i-lucide-shopping-cart" variant="soft" label="Courses de ce lieu" :to="`/courses?place=${id}`" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <StockTab :place-id="id" />
      <UCard v-if="equipment.length">
        <template #header>
          <h3 class="font-semibold">Équipements</h3>
        </template>
        <ul class="divide-y divide-default text-sm">
          <li v-for="e in equipment" :key="e.id" class="flex flex-wrap justify-between gap-2 py-2">
            <span>{{ e.name }}<span v-if="e.room" class="text-muted"> · {{ e.room }}</span></span>
            <UBadge v-if="e.warrantyEnd" :color="e.underWarranty ? 'success' : 'neutral'" variant="subtle" :label="`${e.underWarranty ? 'Garantie jusqu’au' : 'Garantie finie le'} ${dayFr(e.warrantyEnd)}`" />
          </li>
        </ul>
      </UCard>
    </template>
  </UDashboardPanel>
</template>
