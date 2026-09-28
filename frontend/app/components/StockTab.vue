<script setup lang="ts">
import type { Item, Level, LevelState } from '~/types/stock'

// Stock of a place, by sub-location: a quick OK / Bas / Vide per article (from a phone), the quantity when counted,
// "−1" to record a consumption, and tracking a new article (admins: thresholds and targets per level).
const props = defineProps<{ placeId: string }>()
const api = useApi()
const toast = useToast()

const { data: levels, refresh } = await useAsyncData(`stock-${props.placeId}`, () => api<Level[]>(`/api/places/${props.placeId}/stock`), { default: () => [] })
const { data: items } = await useAsyncData('stock-items', () => api<Item[]>('/api/stock-items'), { default: () => [] })
const groups = computed(() => {
  const out = new Map<string, Level[]>()
  for (const l of levels.value) out.set(l.location.name, [...(out.get(l.location.name) ?? []), l])
  return [...out.entries()].map(([name, rows]) => ({ name, rows }))
})
const replace = (l: Level) => levels.value = levels.value.map(x => x.id === l.id ? l : x)

async function patch(level: Level, body: Record<string, unknown>) {
  try {
    replace(await api<Level>(`/api/stock-levels/${level.id}`, { method: 'PATCH', body }))
  }
  catch (error) {
    toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })
  }
}
const setState = (level: Level, state: LevelState) => patch(level, { level: state })
function setQuantity(level: Level, value: string | number) {
  const n = Number(String(value).replace(',', '.'))
  if (value === '' || Number.isNaN(n)) return
  patch(level, { quantity: n })
}

async function consume(level: Level) {
  try {
    await api('/api/movements', { method: 'POST', body: { item: level.item, placeId: props.placeId, location: level.location.name, type: 'consume', quantity: 1 } })
    await refresh()
  }
  catch (error) {
    toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })
  }
}

const form = reactive({ item: '', location: '', quantity: '' })
const itemOptions = computed(() => items.value.map(i => ({ label: i.name, value: i.id })))
async function track() {
  if (!form.item) return
  try {
    await api(`/api/places/${props.placeId}/stock`, { method: 'POST', body: { item: form.item, location: form.location, ...(form.quantity !== '' ? { quantity: Number(form.quantity.replace(',', '.')) } : {}) } })
    form.item = ''
    form.quantity = ''
    await refresh()
  }
  catch (error) {
    toast.add({ title: 'Non ajouté', description: apiErrorMessage(error), color: 'error' })
  }
}

async function untrack(level: Level) {
  if (!confirm(`Ne plus suivre « ${level.name} » ici ?`)) return
  await api(`/api/stock-levels/${level.id}`, { method: 'DELETE' })
  await refresh()
}
</script>

<template>
  <div class="space-y-4">
    <UCard v-for="g in groups" :key="g.name">
      <template #header>
        <h3 class="font-semibold">{{ g.name || 'Général' }}</h3>
      </template>
      <ul class="divide-y divide-default">
        <li v-for="l in g.rows" :key="l.id" class="flex flex-wrap items-center gap-3 py-3">
          <div class="min-w-40 flex-1">
            <p class="font-medium">{{ l.name }}</p>
            <p class="text-xs text-muted">seuil {{ qtyFr(l.threshold) }} · cible {{ qtyFr(l.target) }} {{ l.unit }}</p>
          </div>
          <UFieldGroup>
            <UButton
v-for="s in (['ok', 'low', 'empty'] as const)" :key="s" size="lg" :label="STOCK_LEVEL_LABEL[s]"
              :color="l.level === s ? STOCK_LEVEL_COLOR[s] : 'neutral'" :variant="l.level === s ? 'solid' : 'outline'" @click="setState(l, s)" />
          </UFieldGroup>
          <UInput :model-value="l.quantity === null ? '' : String(l.quantity)" inputmode="decimal" class="w-24" :placeholder="l.unit" @change="(e: Event) => setQuantity(l, (e.target as HTMLInputElement).value)" />
          <UButton size="lg" variant="soft" icon="i-lucide-minus" label="1" aria-label="Consommer 1" @click="consume(l)" />
          <UButton size="sm" variant="ghost" color="error" icon="i-lucide-x" aria-label="Ne plus suivre" @click="untrack(l)" />
        </li>
      </ul>
    </UCard>
    <UCard v-if="!levels.length">
      <p class="text-sm text-muted">Aucun article suivi dans ce lieu.</p>
    </UCard>
    <UCard>
      <template #header>
        <h3 class="font-semibold">Suivre un article</h3>
      </template>
      <div class="flex flex-wrap items-end gap-3">
        <UFormField label="Article"><USelectMenu v-model="form.item" :items="itemOptions" value-key="value" class="w-56" /></UFormField>
        <UFormField label="Emplacement" hint="vide = général"><UInput v-model="form.location" placeholder="réserve, cuisine…" /></UFormField>
        <UFormField label="Quantité"><UInput v-model="form.quantity" inputmode="decimal" class="w-24" /></UFormField>
        <UButton icon="i-lucide-plus" label="Ajouter" :disabled="!form.item" @click="track" />
      </div>
    </UCard>
  </div>
</template>
