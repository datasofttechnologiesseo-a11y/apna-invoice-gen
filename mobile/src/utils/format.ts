/** ₹ with Indian lakh/crore grouping: 124500 → "₹1,24,500". */
export function money(value: number | string | null | undefined, opts: { paise?: boolean } = {}): string {
  const n = Number(value ?? 0);
  if (!Number.isFinite(n)) return '₹0';
  const negative = n < 0;
  const abs = Math.abs(n);
  const showPaise = opts.paise ?? !Number.isInteger(Math.round(abs * 100) / 100);
  const fixed = abs.toFixed(showPaise ? 2 : 0);
  const [whole, frac] = fixed.split('.');
  const last3 = whole.slice(-3);
  const rest = whole.slice(0, -3);
  const grouped = rest ? `${rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',')},${last3}` : last3;
  return `${negative ? '−' : ''}₹${grouped}${frac ? `.${frac}` : ''}`;
}

/** Compact ₹ for tight spots: 286400 → "₹2.9L", 12345678 → "₹1.2Cr". */
export function moneyShort(value: number | null | undefined): string {
  const n = Number(value ?? 0);
  const a = Math.abs(n);
  const f = (x: number, unit: string) => `${n < 0 ? '−' : ''}₹${x >= 100 ? Math.round(x) : x.toFixed(x >= 10 ? 1 : 2).replace(/\.?0+$/, '')}${unit}`;
  if (a >= 1e7) return f(a / 1e7, 'Cr');
  if (a >= 1e5) return f(a / 1e5, 'L');
  if (a >= 1e3) return f(a / 1e3, 'K');
  return money(n, { paise: false });
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** "2026-11-13" → "13 Nov 2026" (or "13 Nov" with short). */
export function date(iso: string | null | undefined, short = false): string {
  if (!iso) return '';
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  if (!y || !m || !d) return iso;
  return short ? `${d} ${MONTHS[m - 1]}` : `${d} ${MONTHS[m - 1]} ${y}`;
}

/** Today as "YYYY-MM-DD" in the device's time zone. */
export function today(): string {
  const t = new Date();
  const p = (n: number) => String(n).padStart(2, '0');
  return `${t.getFullYear()}-${p(t.getMonth() + 1)}-${p(t.getDate())}`;
}

export function addDays(iso: string, days: number): string {
  const [y, m, d] = iso.split('-').map(Number);
  const t = new Date(y, m - 1, d + days);
  const p = (n: number) => String(n).padStart(2, '0');
  return `${t.getFullYear()}-${p(t.getMonth() + 1)}-${p(t.getDate())}`;
}

/** Days from today until iso (negative = past). */
export function daysFromToday(iso: string | null | undefined): number | null {
  if (!iso) return null;
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  const now = new Date();
  const a = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());
  return Math.round((Date.UTC(y, m - 1, d) - a) / 86400000);
}

export function initials(name: string): string {
  return name
    .replace(/&/g, ' ')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]!.toUpperCase())
    .join('');
}

export function greeting(): string {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 17) return 'Good afternoon';
  return 'Good evening';
}
