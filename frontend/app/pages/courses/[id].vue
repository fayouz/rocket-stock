<script setup lang="ts">
import type { CartLine, PurchaseOrder, PurchaseOrderPreview, ShoppingCart, StoreCart } from '~/types/stock'

// A shopping cart, made for a phone in the shop: lines grouped by store, big checkboxes; "Terminer" brings the
// ticked lines into stock (idempotent), "Partager" copies the list as text. Ordering: EAN + barcode per line and a
// link to the product on the store's website; Amazon stores open a pre-filled Amazon cart (the user checks out and
// pays there, nothing is bought here); suppliers with an order e-mail get a purchase order, previewed then sent only
// after an explicit confirmation (admins).
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
const { isAdmin } = useAuth()
const { data: storeCarts } = await useAsyncData(`cart-${id}-store-carts`, () => api<StoreCart[]>(`/api/shopping-carts/${id}/store-carts`), { default: () => [] })
const { data: orders, refresh: refreshOrders } = await useAsyncData(`cart-${id}-orders`, () => api<PurchaseOrder[]>(`/api/shopping-carts/${id}/purchase-orders`), { default: () => [] })
const storeCartOf = (storeId?: string) => storeCarts.value.find(s => s.store.id === storeId)
const orderOf = (storeId?: string) => orders.value.find(o => o.supplier.id === storeId)
const barcodes = ref(new Set<string>())
function toggleBarcode(lineId: string) {
  const next = new Set(barcodes.value)
  if (next.has(lineId)) next.delete(lineId)
  else next.add(lineId)
  barcodes.value = next
}

function openAmazon(url: string) {
  window.open(url, '_blank', 'noopener')
}

const preview = ref<PurchaseOrderPreview | null>(null)
const previewStore = ref<{ id: string, name: string } | null>(null)
const previewOpen = ref(false)
const sending = ref(false)
async function showOrder(store: { id: string, name: string }) {
  try {
    preview.value = await api<PurchaseOrderPreview>(`/api/shopping-carts/${id}/purchase-orders/${store.id}/preview`)
    previewStore.value = store
    previewOpen.value = true
  }
  catch (error) {
    toast.add({ title: 'Aperçu impossible', description: apiErrorMessage(error), color: 'error' })
  }
}
async function sendOrder() {
  if (!previewStore.value || !preview.value) return
  if (!confirm(`Envoyer le bon de commande à ${preview.value.to.join(', ')} ?`)) return
  sending.value = true
  try {
    const order = await api<PurchaseOrder>(`/api/shopping-carts/${id}/purchase-orders/${previewStore.value.id}`, { method: 'POST', body: { confirm: true } })
    toast.add({ title: order.status === 'demo' ? 'Bon de commande enregistré (démo : rien n’est parti)' : 'Bon de commande envoyé', color: 'success' })
    previewOpen.value = false
    await refreshOrders()
  }
  catch (error) {
    toast.add({ title: 'Non envoyé', description: apiErrorMessage(error), color: 'error' })
  }
  finally {
    sending.value = false
  }
}

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
            <div v-if="storeCartOf(g.store?.id) || (g.store?.orderEmail && isAdmin)" class="mt-2 flex flex-wrap items-center gap-2">
              <template v-if="storeCartOf(g.store?.id)">
                <UButton
                  v-for="(url, n) in storeCartOf(g.store?.id)!.urls" :key="url" size="sm" color="warning" icon="i-lucide-shopping-cart"
                  :label="`Ouvrir le panier Amazon${storeCartOf(g.store?.id)!.urls.length > 1 ? ` (${n + 1}/${storeCartOf(g.store?.id)!.urls.length})` : ''}`"
                  @click="openAmazon(url)"
                />
                <span class="text-xs text-muted">Vous validez et payez sur Amazon.<template v-if="storeCartOf(g.store?.id)!.skipped.length"> Sans ASIN : {{ storeCartOf(g.store?.id)!.skipped.join(', ') }}.</template></span>
              </template>
              <template v-if="g.store?.orderEmail && isAdmin">
                <UBadge v-if="orderOf(g.store?.id)" color="success" variant="subtle" icon="i-lucide-mail-check" :label="`Bon de commande envoyé le ${whenFr(orderOf(g.store?.id)!.sentAt)}${orderOf(g.store?.id)!.status === 'demo' ? ' (démo)' : ''}`" />
                <UButton v-else size="sm" variant="soft" icon="i-lucide-file-text" label="Bon de commande…" @click="showOrder(g.store!)" />
              </template>
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
              <div v-if="l.item.ean || l.productUrl" class="flex flex-wrap items-center gap-3 pb-3 pl-12 text-xs">
                <button v-if="l.item.ean" type="button" class="font-mono text-muted hover:underline" :aria-expanded="barcodes.has(l.id)" @click="toggleBarcode(l.id)">
                  EAN {{ l.item.ean }}
                </button>
                <a v-if="l.productUrl" :href="l.productUrl" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Voir sur le site</a>
                <EanBarcode v-if="l.item.ean && barcodes.has(l.id)" :ean="l.item.ean" class="basis-full" />
              </div>
            </li>
          </ul>
        </UCard>
      </div>
    </template>

    <UModal v-model:open="previewOpen" :title="`Bon de commande · ${previewStore?.name ?? ''}`">
      <template #body>
        <div v-if="preview" class="space-y-2 text-sm">
          <p><span class="text-muted">À :</span> {{ preview.to.join(', ') }}</p>
          <p><span class="text-muted">Objet :</span> {{ preview.subject }}</p>
          <pre class="max-h-80 overflow-auto whitespace-pre-wrap rounded bg-elevated p-3 text-xs">{{ preview.text }}</pre>
          <p class="text-xs text-muted">Envoyé en votre nom par Rocket Mailer (tableau HTML), une seule fois pour ce panier et ce fournisseur.</p>
        </div>
      </template>
      <template #footer>
        <UButton variant="ghost" label="Annuler" @click="previewOpen = false" />
        <UButton icon="i-lucide-send" label="Envoyer le bon de commande" :loading="sending" @click="sendOrder" />
      </template>
    </UModal>
  </UDashboardPanel>
</template>
