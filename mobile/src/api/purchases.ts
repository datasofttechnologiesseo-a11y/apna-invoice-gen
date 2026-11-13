/**
 * Vendors, purchase invoices, GSTR-2B match and item stock — mirrors
 * routes/api/purchases.php (Api\VendorController, PurchaseBillController,
 * Gstr2bController, ProductController@stock/adjustStock/restore).
 * Tax split, numbering checks, ITC rules and stock maths stay on the server
 * (App\Services\PurchaseBook, VendorBook, Gstr2bBook, StockLedger).
 */
import { del, get, post, put, qs, upload } from './client';
import type { Paginated, Product } from './types';

// ---- Vendors -----------------------------------------------------------------

export type Vendor = {
  id: number;
  name: string;
  gstin: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state_id: number | null;
  state_name?: string | null;
  postal_code: string | null;
  country: string | null;
  phone: string | null;
  email: string | null;
  notes: string | null;
  /** Present on the list and the detail. */
  payable?: number;
  purchase_bills_count?: number;
};

export type VendorInput = {
  name: string;
  gstin: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state_id: number | null;
  postal_code: string | null;
  country: string | null;
  phone: string | null;
  email: string | null;
  notes: string | null;
};

export const vendorsApi = {
  list: (p: { page?: number; search?: string }) => get<Paginated<Vendor>>(`/vendors${qs({ page: p.page, per_page: 30, search: p.search })}`),
  show: (id: number) => get<{ data: Vendor }>(`/vendors/${id}`),
  create: (input: VendorInput) => post<{ data: Vendor }>('/vendors', input),
  update: (id: number, input: VendorInput) => put<{ data: Vendor }>(`/vendors/${id}`, input),
  /** 422 with a message when the vendor has purchase invoices. */
  remove: (id: number) => del<{ message: string }>(`/vendors/${id}`),
};

export function vendorInputOf(v: Vendor): VendorInput {
  return {
    name: v.name,
    gstin: v.gstin,
    address_line1: v.address_line1,
    address_line2: v.address_line2,
    city: v.city,
    state_id: v.state_id,
    postal_code: v.postal_code,
    country: v.country,
    phone: v.phone,
    email: v.email,
    notes: v.notes,
  };
}

// ---- Purchase invoices -------------------------------------------------------

export type PurchaseStatus = 'unpaid' | 'partial' | 'paid' | string;
export type PurchaseFilter = 'all' | 'unpaid' | 'overdue' | 'paid';

export type PurchaseListItem = {
  id: number;
  bill_number: string;
  bill_date: string;
  booked_on: string | null;
  due_date: string | null;
  vendor_id: number;
  vendor_name: string | null;
  vendor_gstin: string | null;
  status: PurchaseStatus;
  status_label: string;
  is_overdue: boolean;
  subtotal: number;
  total_tax: number;
  grand_total: number;
  paid_amount: number;
  balance: number;
  itc_eligible: boolean;
  reverse_charge: boolean;
};

export type PurchaseItem = {
  id: number;
  product_id: number | null;
  product_name?: string | null;
  description: string;
  hsn_sac: string | null;
  quantity: number;
  unit: string | null;
  rate: number;
  discount: number;
  amount: number;
  gst_rate: number;
  cgst_amount: number;
  sgst_amount: number;
  igst_amount: number;
  total: number;
};

export type PurchasePayment = {
  id: number;
  purchase_bill_id: number;
  paid_on: string;
  amount: number;
  method: string;
  method_label: string;
  reference_number: string | null;
  notes: string | null;
};

export type PurchaseBill = PurchaseListItem & {
  vendor: Vendor;
  is_interstate: boolean;
  /** "SGST" or "UTGST" (Union Territory). */
  state_tax_label: string;
  total_cgst: number;
  total_sgst: number;
  total_igst: number;
  round_off: number;
  expense_category: string | null;
  expense_category_label: string | null;
  reference_number: string | null;
  notes: string | null;
  items: PurchaseItem[];
  payments: PurchasePayment[];
  /** Input-credit warnings: s.16(4) time bar, Rule 37 reversal. */
  credit_notes: { time_bar?: string; rule37?: string };
};

export type Option = { value: string; label: string };

export type PurchaseSummary = { payable: number; open_count: number; overdue: number; due_7_days: number; itc_this_month: number };

export type PurchasePage = Paginated<PurchaseListItem> & {
  meta: Paginated<PurchaseListItem>['meta'] & { summary: PurchaseSummary; categories: Option[]; methods: Option[] };
};

export type PurchaseLineInput = {
  product_id: number | null;
  description: string;
  hsn_sac: string | null;
  quantity: number;
  unit: string | null;
  rate: number;
  discount: number;
  gst_rate: number;
};

export type PurchaseInput = {
  vendor_id: number;
  bill_number: string;
  bill_date: string;
  due_date: string | null;
  /** Leave out to let the server decide from the vendor's state. */
  is_interstate?: boolean;
  reverse_charge: boolean;
  itc_eligible: boolean;
  expense_category: string | null;
  reference_number: string | null;
  notes: string | null;
  bill_discount?: number;
  /** New bills only: money already paid when the bill is entered. */
  paid_now?: number;
  paid_on?: string;
  payment_method?: string;
  items: PurchaseLineInput[];
};

export type PaymentInput = { amount: number; method: string; paid_on: string; reference_number?: string; notes?: string };

export type PurchaseListParams = { page?: number; search?: string; status?: PurchaseFilter; vendor?: number; from?: string; to?: string; per_page?: number };

export const purchasesApi = {
  list: (p: PurchaseListParams) =>
    get<PurchasePage>(
      `/purchases${qs({ page: p.page, per_page: p.per_page ?? 20, search: p.search, status: p.status === 'all' ? undefined : p.status, vendor: p.vendor, from: p.from, to: p.to })}`,
    ),
  show: (id: number) => get<{ data: PurchaseBill }>(`/purchases/${id}`),
  /** `message` explains a late booking (locked period) or time-barred ITC. */
  create: (input: PurchaseInput) => post<{ data: PurchaseBill; message?: string }>('/purchases', input),
  update: (id: number, input: PurchaseInput) => put<{ data: PurchaseBill; message?: string }>(`/purchases/${id}`, input),
  remove: (id: number) => del<{ message: string }>(`/purchases/${id}`),
  pay: (id: number, input: PaymentInput) => post<{ data: PurchasePayment; bill: PurchaseListItem }>(`/purchases/${id}/payments`, input),
  removePayment: (id: number, paymentId: number) => del<{ message: string; bill: PurchaseListItem }>(`/purchases/${id}/payments/${paymentId}`),
};

// ---- GSTR-2B ---------------------------------------------------------------

export type Gstr2bBucket = 'matched' | 'mismatch' | 'missing' | 'not_in_2b' | 'no_gstin' | 'notes' | 'no_itc';

export type Gstr2bEntry = {
  id: number;
  kind: string;
  supplier_gstin: string | null;
  supplier_name: string | null;
  document_number: string;
  document_date: string | null;
  document_value: number;
  taxable_value: number;
  igst: number;
  cgst: number;
  sgst: number;
  cess: number;
  tax: number;
  reverse_charge: boolean;
  itc_available: boolean;
  place_of_supply: string | null;
};

export type Gstr2bBill = {
  id: number;
  bill_number: string;
  bill_date: string | null;
  vendor_id: number;
  vendor_name: string | null;
  vendor_gstin: string | null;
  subtotal: number;
  total_tax: number;
  grand_total: number;
};

export type Gstr2bRow = { entry: Gstr2bEntry | null; bill: Gstr2bBill | null; taxable_diff: number | null; tax_diff: number | null };

export type Gstr2b = {
  period: string;
  period_label: string;
  uploaded_periods: string[];
  labels: Record<Gstr2bBucket, string>;
  summary: {
    period: string;
    uploaded: boolean;
    uploaded_at: string | null;
    documents: number;
    itc_in_2b: number;
    counts: Record<Exclude<Gstr2bBucket, 'no_itc'>, number>;
    tax: Partial<Record<Gstr2bBucket, number>>;
    no_itc: { count: number; tax: number };
  };
  buckets: Record<Gstr2bBucket, Gstr2bRow[]>;
};

export type PickedFile = { uri: string; name: string; type: string };

export const gstr2bApi = {
  show: (month: string) => get<Gstr2b>(`/finance/gstr2b${qs({ month })}`),
  upload: (file: PickedFile, month: string) => upload<Gstr2b & { message: string; documents: number }>('/finance/gstr2b', 'file', file, { month }),
  exportPath: (month: string) => `/finance/gstr2b/export${qs({ month })}`,
};

// ---- HSN summary (from the GSTR-1 report) ----------------------------------

export type HsnRow = {
  key: string;
  /** "B2B" or "B2C" — GSTR-1 Table 12 is split by these. */
  tab: string;
  hsn: string;
  description: string | null;
  uqc: string;
  qty: number;
  value: number;
  rate: number;
  taxable: number;
  igst: number;
  cgst: number;
  sgst: number;
  cess: number;
};

export const hsnApi = {
  /** GET /finance/gstr1 also returns the HSN summary; only that part is read here. */
  show: (from: string, to: string) => get<{ period: { from: string; to: string }; hsn: HsnRow[] }>(`/finance/gstr1${qs({ from, to })}`),
};

// ---- Stock -------------------------------------------------------------------

export type StockMovement = {
  id: number;
  moved_on: string;
  kind: string;
  kind_label: string;
  quantity: number;
  balance: number | null;
  note: string | null;
  source: { type: 'invoice' | 'purchase_bill' | 'credit_note' | string; id: number; number: string | null } | null;
};

export type StockLedger = {
  product: Product & { kind_label?: string };
  opening_stock: number;
  on_hand: number;
  movements: StockMovement[];
};

export type StockDirection = 'in' | 'out' | 'count';
export type StockAdjustInput = { direction: StockDirection; quantity: number; moved_on: string; reason: string };

export const stockApi = {
  show: (productId: number) => get<StockLedger>(`/products/${productId}/stock`),
  adjust: (productId: number, input: StockAdjustInput) =>
    post<{ message: string; changed: boolean; movement: StockMovement | null; on_hand: number }>(`/products/${productId}/stock`, input),
  restore: (productId: number) => post<{ message: string; data: Product }>(`/products/${productId}/restore`),
};

/** Month helpers for "YYYY-MM" pickers. */
export function shiftMonth(month: string, by: number): string {
  const [y, m] = month.split('-').map(Number);
  const d = new Date(y, m - 1 + by, 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export function monthLabel(month: string): string {
  const [y, m] = month.split('-').map(Number);
  const names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  return `${names[m - 1] ?? ''} ${y}`;
}

export function monthRange(month: string): { from: string; to: string } {
  const [y, m] = month.split('-').map(Number);
  const last = new Date(y, m, 0).getDate();
  return { from: `${month}-01`, to: `${month}-${String(last).padStart(2, '0')}` };
}

export function currentMonth(offset = 0): string {
  const t = new Date();
  return shiftMonth(`${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}`, offset);
}
