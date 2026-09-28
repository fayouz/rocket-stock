<script setup lang="ts">
import type { Equipment, Place } from '~/types/stock'

// Equipment of the places: serial number, purchase, warranty and manual (a Rocket Cloud reference). Admins edit.
const api = useApi()
const toast = useToast()
const { isAdmin } = useAuth()
useHead({ title: `Équipements · ${useAppConfig().rocket.name}` })

const { data: equipment, refresh } = await useAsyncData('equipment', () => api<Equipment[]>('/api/equipment'), { default: () => [] })
const { data: places } = await useAsyncData('places', () => api<Place[]>('/api/places'), { default: () => [] })
const placeItems = computed(() => places.value.map(p => ({ label: p.name, value: p.id })))
const placeName = (id: string | null) => places.value.find(p => p.id === id)?.name ?? '—'

const blank = () => ({ id: '', name: '', placeId: '', room: '', serial: '', purchaseDate: '', warrantyEnd: '', manualDocumentRef: '', notes: '' })
const form = reactive(blank())
const open = ref(false)
function edit(e?: Equipment) {
  Object.assign(form, blank(), e ? { id: e.id, name: e.name, placeId: e.placeId ?? '', room: e.room ?? '', serial: e.serial ?? '', purchaseDate: e.purchaseDate ?? '', warrantyEnd: e.warrantyEnd ?? '', manualDocumentRef: e.manualDocumentRef ?? '', notes: e.notes ?? '' } : {})
  open.value = true
}
async function save() {
  try {
    const { id, ...body } = form
    await api(id ? `/api/equipment/${id}` : '/api/equipment', { method: id ? 'PATCH' : 'POST', body: { ...body, placeId: body.placeId || null } })
    open.value = false
    await refresh()
  }
  catch (error) {
    toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })
  }
}
async function remove(e: Equipment) {
  if (!confirm(`Supprimer « ${e.name} » ?`)) return
  await api(`/api/equipment/${e.id}`, { method: 'DELETE' })
  await refresh()
}
</script>

<template>
  <UDashboardPanel id="equipements">
    <template #header>
      <UDashboardNavbar title="Équipements">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton v-if="isAdmin" icon="i-lucide-plus" label="Ajouter" @click="edit()" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <UCard>
        <ul class="divide-y divide-default text-sm">
          <li v-for="e in equipment" :key="e.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
            <span>
              <b>{{ e.name }}</b>
              <span class="text-muted"> · {{ placeName(e.placeId) }}{{ e.room ? ` · ${e.room}` : '' }}{{ e.serial ? ` · n° ${e.serial}` : '' }}{{ e.manualDocumentRef ? ` · notice : ${e.manualDocumentRef}` : '' }}</span>
            </span>
            <span class="flex items-center gap-1">
              <UBadge v-if="e.warrantyEnd" :color="e.underWarranty ? 'success' : 'neutral'" variant="subtle" :label="`Garantie ${e.underWarranty ? 'jusqu’au' : 'finie le'} ${dayFr(e.warrantyEnd)}`" />
              <template v-if="isAdmin">
                <UButton size="sm" variant="ghost" icon="i-lucide-pencil" aria-label="Modifier" @click="edit(e)" />
                <UButton size="sm" variant="ghost" color="error" icon="i-lucide-trash-2" aria-label="Supprimer" @click="remove(e)" />
              </template>
            </span>
          </li>
        </ul>
        <p v-if="!equipment.length" class="text-sm text-muted">Aucun équipement.</p>
      </UCard>
    </template>

    <UModal v-model:open="open" :title="form.id ? 'Modifier l’équipement' : 'Nouvel équipement'">
      <template #body>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="Nom" class="sm:col-span-2"><UInput v-model="form.name" class="w-full" /></UFormField>
          <UFormField label="Lieu"><USelect v-model="form.placeId" :items="placeItems" class="w-full" /></UFormField>
          <UFormField label="Pièce"><UInput v-model="form.room" class="w-full" /></UFormField>
          <UFormField label="N° de série"><UInput v-model="form.serial" class="w-full" /></UFormField>
          <UFormField label="Notice (Rocket Cloud)"><UInput v-model="form.manualDocumentRef" class="w-full" /></UFormField>
          <UFormField label="Achat"><UInput v-model="form.purchaseDate" type="date" class="w-full" /></UFormField>
          <UFormField label="Fin de garantie"><UInput v-model="form.warrantyEnd" type="date" class="w-full" /></UFormField>
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
