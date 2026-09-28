<script setup lang="ts">
import type { CartLine, ShoppingCart } from '~/types/stock'

// A shopping cart, made for a phone in the shop: lines grouped by store, big checkboxes; "Terminer" brings the
// ticked lines into stock (idempotent), "Partager" copies the list as text.
const route = useRoute()
const api = useApi()
const toast = useToast()
const id = String(route.params.id)
// Offline (shop without network): the last loaded cart stays on the phone (7 days, only this cart, never the
// credentials) with a "Hors ligne" badge; ticks are shown at once, queued, and sent back in order when the network
// returns ("checked" is set, not toggled: replaying is harmless). Finishing and sharing need the network.
const cacheKey = `cart:${id}`
const stale = ref(false)
const { data: cart, refresh } = await useAsyncData(`cart-${id}`, async () => {
  try {
    const fresh = await api<ShoppingCart>(`/api/shopping-carts/${id}`)
    stale.value = false
    return fresh
  }
  catch (e) {
    const kept = import.meta.client && isNetworkError(e) ? offlineLoad<ShoppingCart>(cacheKey) : null
    if (!kept) throw e
    stale.value = true
    return kept
  }
})
watch(cart, (c) => {
  if (!c) return
  offlineSave(cacheKey, c)
  if (c.status !== 'done') offlineSave('cart:current', c.id)
}, { immediate: true })
const pwa = usePwa()
watch(pwa.synced, () => refresh())
useHead({ title: `Panier · ${useAppConfig().rocket.name}` })
const done = computed(() => cart.value?.status === 'done')

async function toggle(line: CartLine) {
  if (done.value) return
  const body = { checked: !line.checked }
  const path = `/api/shopping-carts/${id}/lines/${line.id}`
  try {
    cart.value = await api<ShoppingCart>(path, { method: 'PATCH', body })
  }
  catch (error) {
    if (!isNetworkError(error) || !cart.value) {
      toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })
      return
    }
    offlineEnqueue({ kind: 'api', path, method: 'PATCH', body, key: `cart:${id}:${line.id}` })
    const copy = JSON.parse(JSON.stringify(cart.value)) as ShoppingCart
    const lines = copy.stores.flatMap(g => g.lines)
    const target = lines.find(l => l.id === line.id)
    if (target) target.checked = body.checked
    copy.checkedCount = lines.filter(l => l.checked).length
    cart.value = copy
  }
}

async function finish() {
  if (!pwa.online.value) {
    toast.add({ title: 'Hors ligne', description: 'Terminer les courses demande le réseau ; les coches sont gardées.', color: 'warning' })
    return
  }
  if (!confirm('Terminer les courses ? Les lignes cochées entrent en stock.')) return
  try {
    cart.value = await api<ShoppingCart>(`/api/shopping-carts/${id}`, { method: 'PATCH', body: { status: 'done' } })
    toast.add({ title: 'Stock mis à jour', color: 'success' })
  }
  catch (error) {
    toast.add({ title: 'Non terminé', description: apiErrorMessage(error), color: 'error' })
  }
}

async function share() {
  if (!pwa.online.value) return toast.add({ title: 'Hors ligne', description: 'Le partage demande le réseau.', color: 'warning' })
  const text = await api<string>(`/api/shopping-carts/${id}/text`, { responseType: 'text' })
  if (navigator.share) await navigator.share({ title: 'Courses', text }).catch(() => {})
  else {
    await navigator.clipboard.writeText(text)
    toast.add({ title: 'Liste copiée', color: 'success' })
  }
}
</script>

<template>
  <UDashboardPanel id="cart">
    <template #header>
      <UDashboardNavbar :title="cart ? `Panier · ${CART_STATUS_LABEL[cart.status]}` : 'Panier'">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <OfflineBadge :stale="stale" />
          <UButton icon="i-lucide-share-2" variant="soft" label="Partager" @click="share" />
          <UButton v-if="!done" icon="i-lucide-check-check" label="Terminer" @click="finish" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div v-if="cart" class="mx-auto w-full max-w-2xl space-y-4">
        <p class="text-sm text-muted">{{ cart.checkedCount }} / {{ cart.lineCount }} dans le panier · ≈ {{ euroFr(cart.estimatedTotal) }}</p>
        <UCard v-for="g in cart.stores" :key="g.store?.id ?? 'none'">
          <template #header>
            <div class="flex items-start justify-between gap-2">
              <div>
                <h3 class="text-lg font-semibold">{{ g.store?.name ?? 'Sans magasin' }}</h3>
                <p v-if="g.store?.address || g.store?.openingHours" class="text-xs text-muted">{{ [g.store?.address, g.store?.openingHours].filter(Boolean).join(' · ') }}</p>
              </div>
              <span class="text-sm text-muted">≈ {{ euroFr(g.estimatedTotal) }}</span>
            </div>
          </template>
          <ul class="divide-y divide-default">
            <li v-for="l in g.lines" :key="l.id">
              <button type="button" class="flex min-h-14 w-full items-center gap-4 py-3 text-left" :disabled="done" @click="toggle(l)">
                <UIcon :name="l.checked ? 'i-lucide-square-check-big' : 'i-lucide-square'" class="size-8 shrink-0" :class="l.checked ? 'text-success' : 'text-muted'" />
                <span class="flex-1" :class="l.checked ? 'line-through text-muted' : ''">
                  <span class="text-base font-medium">{{ qtyFr(l.quantity) }} {{ l.item.unit }} · {{ l.item.name }}</span>
                  <span class="block text-xs text-muted">
                    {{ l.packSize > 1 ? `${qtyFr(l.packs)} paquet(s) de ${qtyFr(l.packSize)} · ` : '' }}{{ l.location || 'général' }}{{ l.estimatedCost !== null ? ` · ≈ ${euroFr(l.estimatedCost)}` : '' }}
                  </span>
                </span>
              </button>
            </li>
          </ul>
        </UCard>
      </div>
    </template>
  </UDashboardPanel>
</template>
