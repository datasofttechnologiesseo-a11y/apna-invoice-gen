// Mirrors the Laravel API Resources (app/Http/Resources/*).

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  email_verified: boolean;
  is_super_admin: boolean;
  active_company_id: number | null;
}

export interface ActiveCompanySummary {
  id: number;
  name: string;
  gstin?: string | null;
  is_onboarded: boolean;
  composition_dealer?: boolean;
  default_currency?: string;
}

export interface AuthResponse {
  token: string;
  user: AuthUser;
  active_company: ActiveCompanySummary | null;
}

export interface MeResponse {
  user: AuthUser;
  active_company: ActiveCompanySummary | null;
}

export interface State {
  id: number;
  code: string;
  name: string;
  gst_code: string;
  is_union_territory: boolean;
}

export interface Company {
  id: number;
  name: string;
  gstin: string | null;
  composition_dealer: boolean;
  pan: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state_id: number | null;
  state?: { id: number; name: string; gst_code: string } | null;
  postal_code: string | null;
  country: string | null;
  phone: string | null;
  email: string | null;
  website: string | null;
  bank_name: string | null;
  bank_account_number: string | null;
  bank_ifsc: string | null;
  bank_branch: string | null;
  upi_id: string | null;
  default_currency: string;
  default_terms: string | null;
  declaration: string | null;
  invoice_prefix: string | null;
  next_invoice_number: string;
  is_onboarded: boolean;
  is_business_complete: boolean;
  customers_count?: number;
  invoices_count?: number;
}

// One row in a customer ledger (debit = invoice, credit = payment/credit note).
export interface LedgerEntry {
  date: string | null;
  type: 'invoice' | 'payment' | 'credit_note';
  ref: string | null;
  particulars: string;
  debit: number;
  credit: number;
  balance: number;
}

export interface CustomerLedger {
  customer: Customer;
  totals: { invoiced: number; received: number; credited: number; outstanding: number };
  entries: LedgerEntry[];
}

export interface CreditNote {
  id: number;
  invoice_id: number;
  credit_note_number: string | null;
  credit_note_date: string | null;
  amount: number;
  taxable_value: number;
  total_cgst: number;
  total_sgst: number;
  total_igst: number;
  reason: string;
  reason_label: string;
  notes: string | null;
}

export interface CreditNoteReason {
  value: string;
  label: string;
  hint: string | null;
}

export interface CreditNotesResponse {
  data: CreditNote[];
  meta: {
    creditable: number;
    window_closed: boolean;
    deadline: string | null;
    can_create: boolean;
    reasons: CreditNoteReason[];
  };
}

// Fields the mobile company-edit form sends. Mirrors the API validation.
export interface CompanyInput {
  name: string;
  state_id: number;
  country: string;
  default_currency: string;
  invoice_prefix: string;
  invoice_number_padding: number;
  gstin?: string | null;
  pan?: string | null;
  composition_dealer?: boolean;
  address_line1?: string | null;
  address_line2?: string | null;
  city?: string | null;
  postal_code?: string | null;
  phone?: string | null;
  email?: string | null;
  website?: string | null;
  bank_name?: string | null;
  bank_account_number?: string | null;
  bank_ifsc?: string | null;
  bank_branch?: string | null;
  upi_id?: string | null;
  default_terms?: string | null;
}

export interface Customer {
  id: number;
  name: string;
  gstin: string | null;
  has_gstin: boolean;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state_id: number | null;
  state?: { id: number; name: string; gst_code: string } | null;
  postal_code: string | null;
  country: string | null;
  phone: string | null;
  email: string | null;
}

export interface Product {
  id: number;
  name: string;
  sku: string | null;
  kind: 'goods' | 'service';
  kind_label: string;
  hsn_sac: string | null;
  unit: string | null;
  rate: number;
  gst_rate: number;
  is_active: boolean;
  description: string | null;
}

export type InvoiceStatus = 'draft' | 'final' | 'paid' | 'partially_paid' | 'cancelled';

export interface InvoiceListItem {
  id: number;
  invoice_number: string | null;
  display_number: string;
  status: InvoiceStatus;
  invoice_date: string | null;
  due_date: string | null;
  customer_name: string | null;
  grand_total: number;
  paid_amount: number;
  balance: number;
  effective_balance: number;
  currency: string;
}

export interface InvoiceItem {
  id: number;
  product_id: number | null;
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
  sort_order: number;
}

export interface Payment {
  id: number;
  invoice_id: number;
  receipt_number: string;
  received_at: string | null;
  amount: number;
  tds_amount: number;
  tds_section: string | null;
  tds_rate: number | null;
  net_received: number;
  method: string;
  method_label: string;
  reference_number: string | null;
  notes: string | null;
}

export interface Invoice {
  id: number;
  invoice_number: string | null;
  display_number: string;
  document_title: string;
  status: InvoiceStatus;
  invoice_date: string | null;
  due_date: string | null;
  customer_id: number;
  customer?: Customer;
  is_interstate: boolean;
  reverse_charge: boolean;
  currency: string;
  subtotal: number;
  total_cgst: number;
  total_sgst: number;
  total_igst: number;
  total_tax: number;
  round_off: number;
  grand_total: number;
  paid_amount: number;
  credited_amount: number;
  balance: number;
  effective_balance: number;
  notes: string | null;
  terms: string | null;
  style: string | null;
  can: {
    edit: boolean;
    soft_edit: boolean;
    finalize: boolean;
    cancel: boolean;
    delete: boolean;
  };
  items?: InvoiceItem[];
  payments?: Payment[];
  whatsapp_link: string | null;
}

export type QuotationStatus = 'draft' | 'sent' | 'accepted' | 'declined' | 'converted';

export interface QuotationListItem {
  id: number;
  quote_number: string | null;
  display_number: string;
  status: QuotationStatus;
  effective_status: string;
  quote_date: string | null;
  valid_until: string | null;
  customer_name: string | null;
  grand_total: number;
  currency: string;
}

export interface QuotationItem {
  id: number;
  product_id: number | null;
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
}

export interface Quotation {
  id: number;
  quote_number: string | null;
  display_number: string;
  status: QuotationStatus;
  effective_status: string;
  is_expired: boolean;
  days_until_expiry: number | null;
  quote_date: string | null;
  valid_until: string | null;
  customer_id: number;
  customer?: Customer;
  subject: string | null;
  reference: string | null;
  delivery_period: string | null;
  is_interstate: boolean;
  currency: string;
  subtotal: number;
  total_cgst: number;
  total_sgst: number;
  total_igst: number;
  total_tax: number;
  round_off: number;
  grand_total: number;
  notes: string | null;
  terms: string | null;
  style: string | null;
  converted_to_invoice_id: number | null;
  decline_reason: string | null;
  can: {
    edit: boolean;
    send: boolean;
    accept: boolean;
    decline: boolean;
    convert: boolean;
    delete: boolean;
  };
  items?: QuotationItem[];
  whatsapp_link: string | null;
}

export interface QuotationInput {
  customer_id: number;
  quote_date: string;
  valid_until?: string | null;
  subject?: string | null;
  items: InvoiceItemInput[];
}

export interface CashMemoListItem {
  id: number;
  memo_number: string | null;
  memo_date: string | null;
  seller_name: string;
  grand_total: number;
  payment_mode: string;
}

export interface CashMemoItem {
  id: number;
  description: string;
  hsn_sac: string | null;
  quantity: number;
  unit: string | null;
  rate: number;
  amount: number;
  sort_order: number;
}

export interface CashMemo {
  id: number;
  memo_number: string | null;
  memo_date: string | null;
  seller_name: string;
  seller_address: string | null;
  seller_gstin: string | null;
  seller_phone: string | null;
  seller_state: string | null;
  subtotal: number;
  discount: number;
  taxable_value: number;
  total_cgst: number;
  total_sgst: number;
  total_igst: number;
  round_off: number;
  grand_total: number;
  is_interstate: boolean;
  payment_mode: string;
  reference_number: string | null;
  expense_category: string | null;
  notes: string | null;
  items: CashMemoItem[];
}

export interface CashMemoItemInput {
  description: string;
  hsn_sac?: string;
  quantity: number;
  unit?: string;
  rate: number;
}

export interface CashMemoInput {
  memo_date: string;
  memo_number?: string;
  seller_name: string;
  seller_gstin?: string;
  seller_state?: string;
  discount?: number;
  gst_rate?: number;
  is_interstate?: boolean;
  payment_mode: string;
  reference_number?: string;
  notes?: string;
  items: CashMemoItemInput[];
}

export interface Expense {
  id: number;
  entry_date: string | null;
  category: string;
  category_label: string;
  category_color: string;
  vendor_name: string | null;
  description: string;
  amount: number;
  gst_amount: number;
  total: number;
  is_interstate: boolean;
  payment_method: string | null;
  reference_number: string | null;
  notes: string | null;
  cash_memo_id: number | null;
}

export interface ExpenseCategory {
  value: string;
  label: string;
  color: string;
}

export interface ExpensesResponse {
  data: Expense[];
  meta: {
    totals: { count: number; amount: number; gst: number; outflow: number };
    categories: ExpenseCategory[];
  };
}

export interface ExpenseInput {
  entry_date: string;
  category: string;
  vendor_name?: string;
  description: string;
  amount: number;
  gst_amount?: number;
  is_interstate?: boolean;
  payment_method?: string;
  reference_number?: string;
  notes?: string;
}

export interface PnlReport {
  period: { start: string; end: string; label: string; key: string };
  revenue: { taxable: number; gst_collected: number; grand_total: number; received: number; outstanding: number };
  expense: { taxable: number; gst_itc: number; cash_out: number };
  net_profit: number;
  margin: number;
  cash_in_hand: number;
  gst_payable: number;
  by_category: { category: string; label: string; color: string; total: number; count: number; share: number }[];
  trend: { label: string; ym: string; revenue: number; expenses: number }[];
}

export interface AgingCustomerRow {
  name: string;
  gstin: string | null;
  invoice_count: number;
  oldest_days: number;
  total: number;
  current: number;
  b30_60: number;
  b60_90: number;
  b90_plus: number;
}

export interface AgingReport {
  as_on: string;
  summary: { customers: number; invoices: number; total: number; current: number; b30_60: number; b60_90: number; b90_plus: number };
  by_customer: AgingCustomerRow[];
}

export interface Gstr3bReport {
  period: { start: string; end: string; label: string; month: string };
  outward: { taxable: number; igst: number; cgst: number; sgst: number };
  rcm_outward: { taxable: number; igst: number; cgst: number; sgst: number };
  itc: { igst: number; cgst: number; sgst: number; total: number };
  net_cash: { igst: number; cgst: number; sgst: number; total: number };
  invoice_count: number;
  expense_count: number;
  cash_memo_count: number;
}

export interface Gstr1Summary {
  count: number;
  taxable: number;
  cgst: number;
  sgst: number;
  igst: number;
  total: number;
}

export interface Gstr1Report {
  period: { from: string; to: string };
  b2b: Gstr1Summary;
  b2c: Gstr1Summary;
  all: Gstr1Summary;
}

export interface BlogListItem {
  title: string;
  slug: string;
  excerpt: string | null;
  published_at: string | null;
  reading_minutes: number;
  featured_image_url: string | null;
}

export interface BlogPost {
  title: string;
  slug: string;
  excerpt: string | null;
  published_at: string | null;
  reading_minutes: number;
  author: string | null;
  featured_image_url: string | null;
  body_html: string;
}

export interface ActivityLog {
  id: number;
  action: string;
  description: string;
  user_name: string | null;
  created_at: string | null;
}

export interface BackupStatus {
  auto_backup_enabled: boolean;
  last_backup_sent_at: string | null;
}

export interface ReferralData {
  code: string;
  share_url: string;
  share_text: string;
  wa_share: string;
  stats: { total: number; pending: number; rewarded: number };
  referrals: { name: string | null; email: string | null; signed_up_at: string | null; reward_status: string }[];
}

export interface DashboardData {
  company: { id: number; name: string };
  kpis: {
    outstanding: number;
    overdue: number;
    revenue_fy: number;
    revenue_this_month: number;
  };
  counts: {
    invoices: number;
    drafts: number;
    customers: number;
    products: number;
    quotations_awaiting: number;
  };
  recent_invoices: InvoiceListItem[];
}

// Laravel paginated collection envelope.
export interface Paginated<T> {
  data: T[];
  links: { first: string; last: string; prev: string | null; next: string | null };
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// New invoice payload sent to POST /invoices.
export interface InvoiceItemInput {
  product_id?: number | null;
  description?: string;
  hsn_sac?: string;
  quantity: number;
  unit?: string;
  rate: number;
  discount?: number;
  gst_rate: number;
}

export interface InvoiceInput {
  customer_id: number;
  invoice_date: string;
  due_date?: string | null;
  reverse_charge?: boolean;
  notes?: string | null;
  terms?: string | null;
  paid_amount?: number;
  items: InvoiceItemInput[];
}
