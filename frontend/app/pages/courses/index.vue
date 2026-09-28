<script setup lang="ts">
import type { Place, ShoppingCart, ShoppingList } from '~/types/stock'

// What to buy (computed from the levels below their threshold), and the shopping carts made from it.
const api = useApi()
const toast = useToast()
const route = useRoute()
useHead({ title: `Courses · ${useAppConfig().rocket.name}` })

const ALL = 'all'
const place = ref(typeof route.query.place === 'string' ? route.query.place : ALL)
const { data: places } = await useAsyncData('places', () => api<Place[]>('/api/places'), { default: () => [] })
const placeItems = computed(() => [{ label: 'Tous les lieux', value: ALL }, ...places.value.map(p => ({ label: p.name, value: p.id }))])
const query = computed(() => place.value === ALL ? {} : { place: place.value })
const { data: list } = await useAsyncData('shopping-list', () => api<ShoppingList>('/api/shopping-list', { query: query.value }), { watch: [place] })
const { data: carts } = await useAsyncData('shopping-carts', () => api<ShoppingCart[]>('/api/shopping-carts'), { default: () => [] })

const creating = ref(false)
async function createCart() {
  creating.value = true
  try {
    const cart = await api<ShoppingCart>('/api/shopping-carts', { method: 'POST', query: query.value })
    await navigateTo(`/courses/${cart.id}`)
  }
  catch (error) {
    toast.add({ title: 'Panier non créé', description: apiErrorMessage(error), color: 'error' })
  }
  creating.value = false
}
</script>

<template>
  <UDashboardPanel id="courses">
    <template #header>
      <UDashboardNavbar title="Courses">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <PwaInstallButton />
          <USelect v-model="place" :items="placeItems" class="w-44" />
          <UButton icon="i-lucide-shopping-cart" label="Créer un panier" :loading="creating" :disabled="!list?.lines.length" @click="createCart" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <UCard>
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="font-semibold">À acheter</h3>
            <span class="text-sm text-muted">≈ {{ euroFr(list?.estimatedTotal) }}</span>
          </div>
        </template>
        <ul class="divide-y divide-default text-sm">
          <li v-for="l in list?.lines ?? []" :key="l.levelId" class="flex flex-wrap items-center justify-between gap-2 py-2">
            <span>
              <UBadge :color="STOCK_LEVEL_COLOR[l.level]" variant="subtle" :label="STOCK_LEVEL_LABEL[l.level]" class="mr-2" />
              <b>{{ qtyFr(l.suggestedQty) }} {{ l.item.unit }}</b> {{ l.item.name }}
              <span class="text-muted"> · {{ l.placeName }}{{ l.location ? ` · ${l.location}` : '' }}</span>
            </span>
            <span class="text-muted">{{ l.store?.name ?? 'Sans magasin' }}</span>
          </li>
        </ul>
        <p v-if="!list?.lines.length" class="text-sm text-muted">Tout est en stock.</p>
      </UCard>
      <UCard>
        <template #header>
          <h3 class="font-semibold">Paniers</h3>
        </template>
        <ul class="divide-y divide-default text-sm">
          <li v-for="c in carts" :key="c.id" class="flex items-center justify-between gap-2 py-2">
            <NuxtLink :to="`/courses/${c.id}`" class="hover:underline">Panier du {{ whenFr(c.createdAt) }} · {{ c.checkedCount }}/{{ c.lineCount }}</NuxtLink>
            <UBadge :color="CART_STATUS_COLOR[c.status]" variant="subtle" :label="CART_STATUS_LABEL[c.status]" />
          </li>
        </ul>
        <p v-if="!carts.length" class="text-sm text-muted">Aucun panier.</p>
      </UCard>
    </template>
  </UDashboardPanel>
</template>
