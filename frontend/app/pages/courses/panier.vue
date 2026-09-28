<script setup lang="ts">
import type { ShoppingCart } from '~/types/stock'

// "Panier" shortcut of the installed app: the current cart (the latest one not finished), or the list to make one.
// Offline, the last cart opened on this phone.
const api = useApi()
const carts = await api<ShoppingCart[]>('/api/shopping-carts').catch(() => null)
const current = carts
  ? [...carts].filter(c => c.status !== 'done').sort((a, b) => b.createdAt.localeCompare(a.createdAt))[0]?.id
  : offlineLoad<string>('cart:current')
await navigateTo(current ? `/courses/${current}` : '/courses', { replace: true })
</script>

<template>
  <div />
</template>
