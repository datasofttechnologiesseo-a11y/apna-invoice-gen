/** Shapes returned by the Laravel API (app/Http/Resources/*). Keep field-for-field in sync. */

export type Paginated<T> = {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};

export type State = { id: number; code: string; name: string; gst_code: string; is_union_territory: boolean };

export type User = {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  email_verified: boolean;
  is_super_admin: boolean;
  active_company_id: number | null;
};

export type ActiveCompany = {
  id: number;
  name: string;
  gstin?: string | null;
  is_onboarded: boolean;
  composition_dealer?: boolean;
  default_currency?: string;
};

/** App areas the user's team role opens (from /me; owner = all true). */
export type Can = Partial<Record<'invoices_delete' | 'invoices_cancel' | 'quotations_delete' | 'customers_delete' | 'products_delete' | 'purchases' | 'vendors' | 'expenses' | 'cash_memos' | 'gst' | 'reports' | 'dashboard' | 'company_settings' | 'team', boolean>>;
export type Me = { user: User; active_company: ActiveCompany | null; role?: string | null; can?: Can };
export type AuthResponse = { token: string; user: User; active_company: ActiveCompany | null };

export type Customer = {
  id: number;
  name: string;
  gstin: string | null;
  has_gstin: boolean;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state_id: number;
  state: { id: number; name: string; gst_code: string } | null;
  postal_code: string | null;
  country: string | null;
  phone: string | null;
  email: string | null;
};

export type Product = {
  id: number;
  name: string;
  sku: string | null;
  kind: 'goods' | 'service';
  hsn_sac: string | null;
  unit: string;
  rate: number;
  gst_rate: number;
  track_stock: boolean;
  stock: number | null;
  low_stock_level: number | null;
  opening_stock?: number | null;
  purchase_rate?: number | null;
  is_active: boolean;
  description: string | null;
};

export type InvoiceStatus = 'draft' | 'final' | 'partially_paid' | 'paid' | 'cancelled';

export type InvoiceListItem = {
  id: number;
  invoice_number: string | null;
  display_number: string;
  status: InvoiceStatus;
  invoice_date: string;
  due_date: string | null;
  customer_name: string;
  grand_total: number;
  paid_amount: number;
  balance: number;
  effective_balance: number;
  currency: string;
};

export type LineItem = {
  id?: number;
  product_id: number | null;
  description: string;
  hsn_sac: string | null;
  quantity: number;
  unit: string | null;
  rate: number;
  discount: number;
  amount?: number;
  gst_rate: number;
  cgst_amount?: number;
  sgst_amount?: number;
  igst_amount?: number;
  total?: number;
};

export type Payment = {
  id: number;
  receipt_number: string;
  received_at: string;
  amount: number;
  net_received: number;
  method: string;
  method_label: string;
  reference_number: string | null;
};

export type Invoice = InvoiceListItem & {
  document_title: string;
  customer_id: number;
  customer: Customer;
  is_interstate: boolean;
  reverse_charge: boolean;
  subtotal: number;
  total_cgst: number;
  total_sgst: number;
  total_igst: number;
  total_tax: number;
  round_off: number;
  credited_amount: number;
  notes: string | null;
  terms: string | null;
  style: string;
  can: { edit: boolean; soft_edit: boolean; finalize: boolean; cancel: boolean; delete: boolean };
  cancelled_at: string | null;
  cancellation_reason: string | null;
  items: LineItem[];
  payments: Payment[];
  whatsapp_link: string | null;
};

export type QuotationStatus = 'draft' | 'sent' | 'accepted' | 'declined' | 'converted' | 'expired';

export type QuotationListItem = {
  id: number;
  quote_number: string | null;
  display_number: string;
  status: QuotationStatus;
  effective_status: QuotationStatus;
  quote_date: string;
  valid_until: string | null;
  customer_name: string;
  grand_total: number;
  currency: string;
};

export type Quotation = QuotationListItem & {
  customer_id: number;
  customer: Customer;
  subject: string | null;
  reference: string | null;
  delivery_period: string | null;
  is_interstate: boolean;
  subtotal: number;
  total_cgst: number;
  total_sgst: number;
  total_igst: number;
  total_tax: number;
  notes: string | null;
  terms: string | null;
  converted_to_invoice_id: number | null;
  decline_reason: string | null;
  can: { edit: boolean; send: boolean; accept: boolean; decline: boolean; convert: boolean; delete: boolean };
  items: LineItem[];
  whatsapp_link: string | null;
};

type Window = { label: string; full: string; from: string; to: string };

export type DashboardTarget = { screen: 'invoices' | 'invoice' | 'quotations' | 'gstr1' | 'gstr3b' | 'gstr2b' | 'expenses' | 'items' | 'customers' | 'settings' | 'team' | 'purchases'; params?: Record<string, string | number> };

export type Dashboard = {
  company: { id: number; name: string; gstin?: string | null };
  kpis: {
    outstanding: number;
    overdue: number;
    revenue_fy: number;
    revenue_this_month: number;
    collected_this_month?: number;
    expenses_this_month?: number;
    today_issued?: number;
    today_collected?: number;
  };
  counts: { invoices: number; drafts: number; customers: number; products: number; quotations_awaiting: number };
  status_breakdown?: { status: 'paid' | 'partially_paid' | 'unpaid' | 'overdue' | 'draft' | 'cancelled'; count: number; amount: number }[];
  sales?: { monthly: (Window & { billed: number; collected: number })[]; yearly: (Window & { billed: number; collected: number })[] };
  purchases?: { monthly: (Window & { spend: number; gst: number })[]; yearly: (Window & { spend: number; gst: number })[] };
  spend_by_category?: { key: string; label: string; amount: number; color: string }[];
  aging?: { bucket: string; label: string; amount: number; count: number }[];
  top_debtors?: { customer_id: number; name: string; amount: number; oldest_due_date: string | null; overdue_days?: number }[];
  gst?: { label: string; month_query: Record<string, string> | null; gstr1_due: string; gstr3b_due: string; gstr2b_uploaded: boolean; cash: number; invoices: number } | null;
  action_items?: { tone: 'danger' | 'accent' | 'info'; icon: string; title: string; sub: string; cta: string; target: DashboardTarget }[];
  recent_invoices: InvoiceListItem[];
};

export type LedgerEntry = {
  date: string;
  type: 'invoice' | 'payment' | 'credit_note' | string;
  ref: string;
  particulars: string;
  debit: number;
  credit: number;
  balance: number;
};

export type Ledger = {
  customer: Customer;
  totals: { invoiced: number; received: number; credited: number; outstanding: number };
  entries: LedgerEntry[];
};
