<script setup lang="ts">
import type { Supplier } from '~/types/stock'

// Stores (shops you go to: address, hours) and suppliers (websites, wholesalers). Admins edit them.
const api = useApi()
const toast = useToast()
const { isAdmin } = useAuth()
useHead({ title: `Magasins · ${useAppConfig().rocket.name}` })

const { data: suppliers, refresh } = await useAsyncData('suppliers', () => api<Supplier[]>('/api/suppliers'), { default: () => [] })
const kindItems = [{ value: 'store', label: 'Magasin' }, { value: 'supplier', label: 'Fournisseur' }]
const blank = () => ({ id: '', name: '', kind: 'store' as Supplier['kind'], address: '', lat: '', lng: '', openingHours: '', website: '', email: '', phone: '', notes: '', orderEmail: '', searchUrlTemplate: '', amazon: false, amazonDomain: 'amazon.fr' })
const form = reactive(blank())
const open = ref(false)
const num = (v: string) => v === '' ? null : Number(String(v).replace(',', '.'))

function edit(s?: Supplier) {
  Object.assign(form, blank(), s ? { ...Object.fromEntries(Object.entries(s).map(([k, v]) => [k, v === null ? '' : String(v)])), amazon: s.amazon } : {})
  open.value = true
}
async function save() {
  try {
    const body = { ...form, lat: num(form.lat), lng: num(form.lng) }
    await api(form.id ? `/api/suppliers/${form.id}` : '/api/suppliers', { method: form.id ? 'PATCH' : 'POST', body })
    open.value = false
    await refresh()
  }
  catch (error) {
    toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })
  }
}
async function remove(s: Supplier) {
  if (!confirm(`Supprimer « ${s.name} » ?`)) return
  await api(`/api/suppliers/${s.id}`, { method: 'DELETE' })
  await refresh()
}
const mapUrl = (s: Supplier) => s.lat !== null && s.lng !== null ? `https://www.openstreetmap.org/?mlat=${s.lat}&mlon=${s.lng}#map=17/${s.lat}/${s.lng}` : null
</script>

<template>
  <UDashboardPanel id="magasins">
    <template #header>
      <UDashboardNavbar title="Magasins et fournisseurs">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton v-if="isAdmin" icon="i-lucide-plus" label="Ajouter" @click="edit()" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <UCard v-for="s in suppliers" :key="s.id">
          <div class="flex items-start justify-between gap-2">
            <div class="space-y-1 text-sm">
              <p class="text-base font-semibold">{{ s.name }} <UBadge variant="subtle" :label="s.kind === 'store' ? 'Magasin' : 'Fournisseur'" /></p>
              <p v-if="s.address">{{ s.address }}</p>
              <p v-if="s.openingHours" class="text-muted">{{ s.openingHours }}</p>
              <p class="flex gap-3">
                <a v-if="s.website" :href="s.website" target="_blank" rel="noopener" class="text-primary hover:underline">Site</a>
                <a v-if="mapUrl(s)" :href="mapUrl(s)!" target="_blank" rel="noopener" class="text-primary hover:underline">Carte</a>
                <a v-if="s.phone" :href="`tel:${s.phone}`" class="text-primary hover:underline">{{ s.phone }}</a>
              </p>
              <p v-if="s.amazon || s.orderEmail || s.searchUrlTemplate" class="flex flex-wrap gap-1">
                <UBadge v-if="s.amazon" color="warning" variant="subtle" :label="`Panier ${s.amazonDomain}`" />
                <UBadge v-if="s.orderEmail" variant="subtle" icon="i-lucide-mail" :label="`Commandes : ${s.orderEmail}`" />
                <UBadge v-if="s.searchUrlTemplate" variant="subtle" icon="i-lucide-external-link" label="Lien produit" />
              </p>
            </div>
            <span v-if="isAdmin" class="flex gap-1">
              <UButton size="sm" variant="ghost" icon="i-lucide-pencil" aria-label="Modifier" @click="edit(s)" />
              <UButton size="sm" variant="ghost" color="error" icon="i-lucide-trash-2" aria-label="Supprimer" @click="remove(s)" />
            </span>
          </div>
        </UCard>
      </div>
      <UCard v-if="!suppliers.length"><p class="text-sm text-muted">Aucun magasin.</p></UCard>
    </template>

    <UModal v-model:open="open" :title="form.id ? 'Modifier' : 'Ajouter un magasin ou fournisseur'">
      <template #body>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="Nom"><UInput v-model="form.name" class="w-full" /></UFormField>
          <UFormField label="Type"><USelect v-model="form.kind" :items="kindItems" class="w-full" /></UFormField>
          <UFormField label="Adresse" class="sm:col-span-2"><UInput v-model="form.address" class="w-full" /></UFormField>
          <UFormField label="Latitude"><UInput v-model="form.lat" inputmode="decimal" class="w-full" /></UFormField>
          <UFormField label="Longitude"><UInput v-model="form.lng" inputmode="decimal" class="w-full" /></UFormField>
          <UFormField label="Horaires" class="sm:col-span-2"><UInput v-model="form.openingHours" placeholder="lun-sam 8h30-20h" class="w-full" /></UFormField>
          <UFormField label="Site web"><UInput v-model="form.website" class="w-full" /></UFormField>
          <UFormField label="Téléphone"><UInput v-model="form.phone" class="w-full" /></UFormField>
          <UFormField label="E-mail de commande" help="Bons de commande envoyés depuis un panier, après confirmation." class="sm:col-span-2"><UInput v-model="form.orderEmail" type="email" class="w-full" /></UFormField>
          <UFormField label="Recherche produit sur le site" help="{ean} ou {name} est remplacé : https://www.carrefour.fr/s?q={ean}" class="sm:col-span-2"><UInput v-model="form.searchUrlTemplate" class="w-full" /></UFormField>
          <UCheckbox v-model="form.amazon" label="Amazon (panier pré-rempli)" />
          <UFormField v-if="form.amazon" label="Domaine Amazon"><UInput v-model="form.amazonDomain" placeholder="amazon.fr" class="w-full" /></UFormField>
          <UFormField label="Notes" class="sm:col-span-2"><UTextarea v-model="form.notes" class="w-full" /></UFormField>
        </div>
      </template>
      <template #footer>
        <UButton variant="ghost" label="Annuler" @click="open = false" />
        <UButton label="Enregistrer" :disabled="!form.name" @click="save" />
      </template>
    </UModal>
  </UDashboardPanel>
</template>
