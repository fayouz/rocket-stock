<script setup lang="ts">
import type { Category, Item, Supplier } from '~/types/stock'

// The catalogue (admins edit it): articles with unit, category, threshold, cost, and where to buy them (offers).
const api = useApi()
const toast = useToast()
const { isAdmin } = useAuth()
useHead({ title: `Catalogue · ${useAppConfig().rocket.name}` })

const { data: items, refresh } = await useAsyncData('stock-items', () => api<Item[]>('/api/stock-items'), { default: () => [] })
const { data: stores } = await useAsyncData('suppliers', () => api<Supplier[]>('/api/suppliers'), { default: () => [] })
const categoryItems = Object.entries(CATEGORY_LABEL).map(([value, label]) => ({ value, label }))
const storeItems = computed(() => stores.value.map(s => ({ label: s.name, value: s.id })))

interface OfferForm { store: string, preferred: boolean, price: string, packSize: string }
const blank = () => ({ id: '', name: '', unit: 'unité', category: 'consumable' as Category, sku: '', reorderThreshold: '1', reorderQty: '1', unitCost: '', notes: '', offers: [] as OfferForm[] })
const form = reactive(blank())
const open = ref(false)
const num = (v: string) => v === '' ? null : Number(String(v).replace(',', '.'))

function edit(item?: Item) {
  Object.assign(form, blank(), item
    ? { id: item.id, name: item.name, unit: item.unit, category: item.category, sku: item.sku ?? '', reorderThreshold: String(item.reorderThreshold), reorderQty: String(item.reorderQty), unitCost: item.unitCost === null ? '' : String(item.unitCost), notes: item.notes ?? '',
        offers: (item.offers ?? []).map(o => ({ store: o.store.id, preferred: o.preferred, price: o.price === null ? '' : String(o.price), packSize: String(o.packSize) })) }
    : {})
  open.value = true
}

async function save() {
  try {
    const body = { name: form.name, unit: form.unit, category: form.category, sku: form.sku, reorderThreshold: num(form.reorderThreshold) ?? 0, reorderQty: num(form.reorderQty) ?? 1, unitCost: num(form.unitCost), notes: form.notes }
    const item = await api<Item>(form.id ? `/api/stock-items/${form.id}` : '/api/stock-items', { method: form.id ? 'PATCH' : 'POST', body })
    await api(`/api/stock-items/${item.id}/offers`, { method: 'PUT', body: form.offers.filter(o => o.store).map(o => ({ store: o.store, preferred: o.preferred, price: num(o.price), packSize: num(o.packSize) ?? 1 })) })
    open.value = false
    await refresh()
  }
  catch (error) {
    toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })
  }
}

async function remove(item: Item) {
  if (!confirm(`Supprimer « ${item.name} » du catalogue, avec son stock et ses mouvements ?`)) return
  await api(`/api/stock-items/${item.id}`, { method: 'DELETE' })
  await refresh()
}
</script>

<template>
  <UDashboardPanel id="catalogue">
    <template #header>
      <UDashboardNavbar title="Catalogue">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton v-if="isAdmin" icon="i-lucide-plus" label="Nouvel article" @click="edit()" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <UCard>
        <ul class="divide-y divide-default text-sm">
          <li v-for="i in items" :key="i.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
            <span>
              <b>{{ i.name }}</b>
              <span class="text-muted"> · {{ CATEGORY_LABEL[i.category] }} · seuil {{ qtyFr(i.reorderThreshold) }} {{ i.unit }}{{ i.unitCost !== null ? ` · ${euroFr(i.unitCost)}/${i.unit}` : '' }}</span>
              <span v-if="i.offers?.length" class="text-muted"> · {{ i.offers.map(o => o.store.name + (o.preferred ? ' ★' : '')).join(', ') }}</span>
            </span>
            <span v-if="isAdmin" class="flex gap-1">
              <UButton size="sm" variant="ghost" icon="i-lucide-pencil" aria-label="Modifier" @click="edit(i)" />
              <UButton size="sm" variant="ghost" color="error" icon="i-lucide-trash-2" aria-label="Supprimer" @click="remove(i)" />
            </span>
          </li>
        </ul>
        <p v-if="!items.length" class="text-sm text-muted">Catalogue vide.</p>
      </UCard>
    </template>

    <UModal v-model:open="open" :title="form.id ? 'Modifier l’article' : 'Nouvel article'">
      <template #body>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="Nom" class="sm:col-span-2"><UInput v-model="form.name" class="w-full" /></UFormField>
          <UFormField label="Catégorie"><USelect v-model="form.category" :items="categoryItems" class="w-full" /></UFormField>
          <UFormField label="Unité"><UInput v-model="form.unit" class="w-full" /></UFormField>
          <UFormField label="Seuil de réassort"><UInput v-model="form.reorderThreshold" inputmode="decimal" class="w-full" /></UFormField>
          <UFormField label="Coût unitaire (€)"><UInput v-model="form.unitCost" inputmode="decimal" class="w-full" /></UFormField>
          <UFormField label="Référence (SKU)"><UInput v-model="form.sku" class="w-full" /></UFormField>
          <UFormField label="Quantité d’achat habituelle"><UInput v-model="form.reorderQty" inputmode="numeric" class="w-full" /></UFormField>
          <UFormField label="Notes" class="sm:col-span-2"><UTextarea v-model="form.notes" class="w-full" /></UFormField>
        </div>
        <h4 class="mt-4 mb-2 text-sm font-semibold">Où l’acheter</h4>
        <div v-for="(o, n) in form.offers" :key="n" class="mb-2 flex flex-wrap items-end gap-2">
          <UFormField label="Magasin"><USelect v-model="o.store" :items="storeItems" class="w-40" /></UFormField>
          <UFormField label="Prix du paquet"><UInput v-model="o.price" inputmode="decimal" class="w-24" /></UFormField>
          <UFormField label="Paquet de"><UInput v-model="o.packSize" inputmode="decimal" class="w-20" /></UFormField>
          <UCheckbox v-model="o.preferred" label="Préféré" />
          <UButton size="sm" variant="ghost" color="error" icon="i-lucide-x" aria-label="Retirer" @click="form.offers.splice(n, 1)" />
        </div>
        <UButton size="sm" variant="soft" icon="i-lucide-plus" label="Ajouter un magasin" :disabled="!storeItems.length" @click="form.offers.push({ store: '', preferred: !form.offers.length, price: '', packSize: '1' })" />
      </template>
      <template #footer>
        <UButton variant="ghost" label="Annuler" @click="open = false" />
        <UButton label="Enregistrer" :disabled="!form.name" @click="save" />
      </template>
    </UModal>
  </UDashboardPanel>
</template>
