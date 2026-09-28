<script setup lang="ts">
// A scannable EAN-13 / EAN-8 barcode drawn as SVG (black on white, quiet zones), shown in the shop to the cashier
// or a scanner. Nothing when the code is invalid.
const props = defineProps<{ ean: string }>()
const modules = computed(() => eanModules(props.ean))
const QUIET = 9
const HEIGHT = 50
const width = computed(() => (modules.value?.length ?? 0) + 2 * QUIET)
</script>

<template>
  <svg v-if="modules" :viewBox="`0 0 ${width} ${HEIGHT + 10}`" class="h-16 w-auto rounded bg-white" role="img" :aria-label="`Code-barres ${ean}`">
    <path :d="eanPath(modules, HEIGHT)" :transform="`translate(${QUIET} 2)`" fill="#000" />
    <text :x="width / 2" :y="HEIGHT + 9" text-anchor="middle" font-size="8" font-family="monospace" fill="#000">{{ ean }}</text>
  </svg>
</template>
