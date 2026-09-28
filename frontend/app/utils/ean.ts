// EAN-13 / EAN-8 barcode encoder (GS1): returns the modules ("1" = bar, "0" = space), quiet zones excluded.
const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011']
const G = L.map(c => [...c].reverse().map(b => (b === '1' ? '0' : '1')).join(''))
const R = L.map(c => [...c].map(b => (b === '1' ? '0' : '1')).join(''))
// parity of the left half of an EAN-13, chosen by its first digit (L = odd, G = even)
const PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL']

export function eanCheckDigit(body: string): number {
  let sum = 0
  ;[...body].reverse().forEach((d, i) => { sum += Number(d) * (i % 2 === 0 ? 3 : 1) })
  return (10 - (sum % 10)) % 10
}

export function isValidEan(ean: string): boolean {
  return /^(\d{8}|\d{13})$/.test(ean) && eanCheckDigit(ean.slice(0, -1)) === Number(ean.slice(-1))
}

/** Modules of the barcode, or null when the code is not a valid EAN-13 / EAN-8. */
export function eanModules(ean: string): string | null {
  if (!isValidEan(ean)) return null
  const d = [...ean].map(Number)
  if (ean.length === 8) {
    return '101' + d.slice(0, 4).map(n => L[n]).join('') + '01010' + d.slice(4).map(n => R[n]).join('') + '101'
  }
  const parity = PARITY[d[0]!]!
  const left = d.slice(1, 7).map((n, i) => (parity[i] === 'L' ? L[n] : G[n])).join('')
  return '101' + left + '01010' + d.slice(7).map(n => R[n]).join('') + '101'
}

/** SVG path ("M x 0 h w v h h -w z" per bar) of the modules, one unit wide per module. */
export function eanPath(modules: string, height: number): string {
  let path = ''
  for (let i = 0; i < modules.length;) {
    if (modules[i] !== '1') { i++; continue }
    let w = 1
    while (modules[i + w] === '1') w++
    path += `M${i} 0h${w}v${height}h-${w}z`
    i += w
  }
  return path
}
