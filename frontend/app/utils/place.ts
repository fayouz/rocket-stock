import { FetchError } from 'ofetch'

/** Message of an API error (API Platform "detail", Symfony "detail" or HttpException message). */
export function apiErrorMessage(error: unknown): string {
  if (error instanceof FetchError) {
    const data = error.data as { detail?: string, message?: string, 'hydra:description'?: string } | undefined
    return data?.detail || data?.['hydra:description'] || data?.message || error.statusMessage || error.message
  }
  return error instanceof Error ? error.message : String(error)
}

export const dayFr = (d: string) => new Date(d).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })
export const whenFr = (d: string) => new Date(d).toLocaleString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
export const qtyFr = (n: number | null | undefined) => n === null || n === undefined ? '—' : n.toLocaleString('fr-FR', { maximumFractionDigits: 3 })
export const euroFr = (n: number | null | undefined) => n === null || n === undefined ? '—' : n.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
export const STOCK_LEVEL_LABEL: Record<string, string> = { ok: 'OK', low: 'Bas', empty: 'Vide' }
export const STOCK_LEVEL_COLOR: Record<string, 'success' | 'warning' | 'error'> = { ok: 'success', low: 'warning', empty: 'error' }
export const CATEGORY_LABEL: Record<string, string> = { consumable: 'Consommable', linen: 'Linge', equipment: 'Équipement' }
export const MOVEMENT_LABEL: Record<string, string> = { in: 'Entrée', out: 'Sortie', consume: 'Consommation', transfer: 'Transfert', adjust: 'Inventaire' }
export const USAGE_LABEL: Record<string, string> = { rental: 'Location', personal: 'Perso' }
export const CART_STATUS_LABEL: Record<string, string> = { draft: 'Brouillon', in_progress: 'En cours', done: 'Terminé' }
export const CART_STATUS_COLOR: Record<string, 'neutral' | 'info' | 'success'> = { draft: 'neutral', in_progress: 'info', done: 'success' }
