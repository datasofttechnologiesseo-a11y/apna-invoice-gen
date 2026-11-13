/**
 * Calendar helpers for finance screens (date ranges only — no business rules).
 * All dates are "YYYY-MM-DD" / months "YYYY-MM" in the device's time zone.
 */
import { today } from '@/utils/format';

const MONTHS_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const p2 = (n: number) => String(n).padStart(2, '0');

export const isMonth = (v: unknown): v is string => typeof v === 'string' && /^\d{4}-(0[1-9]|1[0-2])$/.test(v);
export const isDate = (v: unknown): v is string => typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v);

export function thisMonth(): string {
  return today().slice(0, 7);
}

/** Last month: the GST return whose filing window is open now (the server's default too). */
export function lastMonth(): string {
  return shiftMonth(thisMonth(), -1);
}

export function shiftMonth(ym: string, by: number): string {
  const [y, m] = ym.split('-').map(Number);
  const t = new Date(y, m - 1 + by, 1);
  return `${t.getFullYear()}-${p2(t.getMonth() + 1)}`;
}

export function monthLabel(ym: string): string {
  const [y, m] = ym.split('-').map(Number);
  return `${MONTHS_LONG[m - 1]} ${y}`;
}

export function monthRange(ym: string): { from: string; to: string } {
  const [y, m] = ym.split('-').map(Number);
  const last = new Date(y, m, 0).getDate();
  return { from: `${ym}-01`, to: `${ym}-${p2(last)}` };
}

/** The Indian FY quarter (Apr–Jun, Jul–Sep, Oct–Dec, Jan–Mar) holding this month. */
export function quarterRange(ym: string): { from: string; to: string } {
  const [, m] = ym.split('-').map(Number);
  const startMonth = shiftMonth(ym, -((m - 1) % 3));
  return { from: monthRange(startMonth).from, to: monthRange(shiftMonth(startMonth, 2)).to };
}

/** Start of the Indian financial year (1 April) holding this date. */
export function fyStart(iso: string = today()): string {
  const [y, m] = iso.split('-').map(Number);
  return `${m >= 4 ? y : y - 1}-04-01`;
}

export type ExpensePeriod = 'this_month' | 'last_month' | 'this_quarter' | 'this_fy' | 'all' | 'custom';

export const EXPENSE_PERIODS: { value: ExpensePeriod; label: string }[] = [
  { value: 'this_month', label: 'This month' },
  { value: 'last_month', label: 'Last month' },
  { value: 'this_quarter', label: 'This quarter' },
  { value: 'this_fy', label: 'This FY' },
  { value: 'all', label: 'All' },
];

export function expensePeriodRange(p: ExpensePeriod, custom?: { from?: string; to?: string }): { from?: string; to?: string } {
  switch (p) {
    case 'this_month':
      return monthRange(thisMonth());
    case 'last_month':
      return monthRange(lastMonth());
    case 'this_quarter':
      return quarterRange(thisMonth());
    case 'this_fy':
      return { from: fyStart(), to: today() };
    case 'custom':
      return { from: custom?.from, to: custom?.to };
    default:
      return {};
  }
}

export function toIso(d: Date): string {
  return `${d.getFullYear()}-${p2(d.getMonth() + 1)}-${p2(d.getDate())}`;
}

export function fromIso(iso: string): Date {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d);
}

/** Safe file-name part: "CM/26-27/0002" → "CM-26-27-0002". */
export const fileSafe = (s: string) => s.replace(/[^A-Za-z0-9._-]+/g, '-');
