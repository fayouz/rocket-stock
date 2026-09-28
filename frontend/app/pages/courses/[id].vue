<script setup lang="ts">
import type { CartLine, ShoppingCart } from '~/types/stock'

// A shopping cart, made for a phone in the shop: lines grouped by store, big checkboxes; "Terminer" brings the
// ticked lines into stock (idempotent), "Partager" copies the list as text.
const route = useRoute()
const api = useApi()
const toast = useToast()
const id = String(route.params.id)
const { data: cart } = await useAsyncData(`cart-${id}`, () => api<ShoppingCart>(`/api/shopping-carts/${id}`))
useHead({ title: `Panier · ${useAppConfig().rocket.name}` })
const done = computed(() => cart.value?.status === 'done')

async function toggle(line: CartLine) {
  if (done.value) return
  try {
    cart.value = await api<ShoppingCart>(`/api/shopping-carts/${id}/lines/${line.id}`, { method: 'PATCH', body: { checked: !line.checked } })
  }
  catch (error) {
    toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })
  }
}

async function finish() {
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
