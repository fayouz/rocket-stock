export interface Place {
  id: string
  name: string
  /** "local": created in Rocket Stock (standalone); "place": a place of Rocket Place. */
  source: 'local' | 'place'
}

export type LevelState = 'ok' | 'low' | 'empty'
export type Category = 'consumable' | 'linen' | 'equipment'

export interface Supplier {
  id: string
  name: string
  kind: 'supplier' | 'store'
  address: string | null
  lat: number | null
  lng: number | null
  openingHours: string | null
  website: string | null
  email: string | null
  phone: string | null
  notes: string | null
}

export interface Offer { id: string, item: string, store: { id: string, name: string, kind: string }, preferred: boolean, price: number | null, packSize: number }

export interface Item {
  id: string
  name: string
  asin: string | null
  reorderQty: number
  subscription: boolean
  unit: string
  category: Category
  sku: string | null
  reorderThreshold: number
  unitCost: number | null
  supplier: { id: string, name: string } | null
  notes: string | null
  offers?: Offer[]
}

export interface Level {
  id: string
  place: string
  item: string
  level: LevelState
  placeId: string
  location: { id: string, placeId: string, name: string }
  name: string
  unit: string
  category: Category
  quantity: number | null
  threshold: number
  thresholdOverride: number | null
  targetQuantity: number | null
  target: number
}

export interface Movement {
  id: string
  type: 'in' | 'out' | 'consume' | 'transfer' | 'adjust'
  quantity: number
  itemId: string
  itemName: string
  unit: string
  placeId: string
  location: { name: string }
  toLocation: { placeId: string, name: string } | null
  reason: string | null
  externalRef: string | null
  origin: string
  originApp: string | null
  usage: 'rental' | 'personal'
  cost: number | null
  occurredAt: string
  createdBy: string | null
}

export interface Equipment {
  id: string
  name: string
  placeId: string | null
  room: string | null
  item: { id: string, name: string } | null
  supplier: { id: string, name: string } | null
  serial: string | null
  purchaseDate: string | null
  warrantyEnd: string | null
  underWarranty: boolean | null
  manualDocumentRef: string | null
  notes: string | null
}

export interface CartLine {
  id: string
  item: { id: string, name: string, unit: string, category: Category }
  placeId: string
  location: string
  quantity: number
  packSize: number
  packs: number
  packPrice: number | null
  estimatedCost: number | null
  checked: boolean
}

export interface ShoppingCart {
  id: string
  placeId: string | null
  status: 'draft' | 'in_progress' | 'done'
  createdAt: string
  completedAt: string | null
  lineCount: number
  checkedCount: number
  estimatedTotal: number
  stores: { store: { id: string, name: string, address: string | null, openingHours: string | null } | null, lines: CartLine[], estimatedTotal: number }[]
}

export interface ShoppingList {
  lines: { levelId: string, item: { id: string, name: string, unit: string }, placeId: string, placeName: string, location: string, level: LevelState, quantity: number | null, threshold: number, suggestedQty: number, store: { id: string, name: string } | null, estimatedCost: number | null }[]
  byStore: { store: { id: string, name: string } | null, lines: number, estimatedCost: number }[]
  estimatedTotal: number
}
