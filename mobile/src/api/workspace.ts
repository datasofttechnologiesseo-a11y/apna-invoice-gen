/**
 * Workspace: team, notifications, search, reports, documents and company
 * settings (routes/api/workspace.php + Api\CompanyController). Rules stay on
 * the server (TeamManager, NotificationFeed, SearchSuggestions, CompanySettings,
 * InvoiceDuplicator); the app only shows what comes back.
 */
import { API_URL } from '@/config';

import { ApiError, authHeader, del, get, patch, post, put, qs, upload } from './client';
import type { Invoice } from './types';

/* ── Team ─────────────────────────────────────────────────────────────── */

export type TeamRole = { key: string; label: string; help: string };

export type TeamMember = {
  id: number;
  email: string;
  role: string;
  role_label: string;
  status: 'active' | 'pending';
  accepted_at: string | null;
  invited_at: string | null;
  user: { id: number; name: string; email: string } | null;
  /** Shareable join link while the invitation is pending. */
  invite_url: string | null;
};

export type ResendResult = { status: string; message: string; retry_after_minutes: number | null; ok: boolean };

export type TeamList = { data: TeamMember[]; meta: { company_id: number; roles: TeamRole[] } };

/* ── Notifications ────────────────────────────────────────────────────── */

export type NotificationFeed = {
  overdue_count: number;
  total_count: number;
  has_unseen: boolean;
  overdue: { invoice_id: number; invoice_number: string | null; customer_name: string | null; due_date: string | null; days_overdue: number | null; balance: number }[];
  recent_payments: { payment_id: number; invoice_id: number; invoice_number: string | null; amount: number; method: string; received_at: string | null; created_at: string | null }[];
  recent_invoices: { invoice_id: number; invoice_number: string | null; customer_name: string | null; grand_total: number; finalized_at: string | null }[];
};

/* ── Search ───────────────────────────────────────────────────────────── */

export type SearchScope = 'invoices' | 'customers';

export type SearchItem = {
  type: 'invoice' | 'customer';
  id: number;
  title: string;
  subtitle: string | null;
  meta: string | null;
  badge: string | null;
  tone: string | null;
};

export type SearchResult = { q: string; scope: SearchScope; groups: { label: string; items: SearchItem[] }[] };

/* ── Reports ──────────────────────────────────────────────────────────── */

export type ReportFormat = 'xlsx' | 'csv' | 'pdf';

export type ReportSummary = { key: string; title: string; document_title: string; group: string; uses_dates: boolean };

export type ReportPreset = { key: string; label: string; from: string; to: string };

export type ReportHub = { data: ReportSummary[]; meta: { presets: ReportPreset[]; formats: ReportFormat[] } };

export type ReportColumnType = 'text' | 'date' | 'money' | 'qty' | 'percent' | 'number';

export type ReportColumn = { key: string; label: string; type: ReportColumnType | string; total: boolean };

export type ReportCell = string | number | boolean | null;

export type Report = {
  key: string;
  title: string;
  document_title: string;
  subtitle: string | null;
  group: string;
  uses_dates: boolean;
  filters: { from: string | null; to: string | null };
  columns: ReportColumn[];
  rows: Record<string, ReportCell>[];
  totals: Record<string, number>;
  truncated: boolean;
  limit: number;
};

/* ── Documents ────────────────────────────────────────────────────────── */

export type InvoiceTemplate = {
  key: string;
  label: string;
  tagline: string | null;
  description: string | null;
  audience: string | null;
  tag: string | null;
  style: string;
  currency: string | null;
};

/** What an issued invoice still allows (Api\InvoiceController@update for a finalized invoice). */
export type IssuedInvoiceEdit = {
  due_date: string | null;
  notes: string | null;
  terms: string | null;
  transporter_name: string | null;
  transporter_id: string | null;
  vehicle_number: string | null;
  transport_mode: 'Road' | 'Rail' | 'Air' | 'Ship' | null;
  eway_bill_number: string | null;
};

/** Invoice resource fields the shared Invoice type leaves out. */
export type InvoiceTransport = Partial<Pick<IssuedInvoiceEdit, 'transporter_name' | 'transporter_id' | 'vehicle_number' | 'transport_mode' | 'eway_bill_number'>>;

export type ThermalPrint = { format: 'thermal'; paper_width_mm: number; content_width_mm: number; html: string };

/* ── Company ──────────────────────────────────────────────────────────── */

export type Company = {
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
  bank_account_name: string | null;
  bank_account_number: string | null;
  bank_account_type: 'savings' | 'current' | null;
  bank_ifsc: string | null;
  bank_branch: string | null;
  upi_id: string | null;
  default_currency: string;
  default_terms: string | null;
  declaration: string | null;
  lut_number: string | null;
  invoice_prefix: string;
  invoice_number_padding: number;
  invoice_number_format: string | null;
  /** The number the next issued invoice gets, already formatted (e.g. "INV-0010"). */
  next_invoice_number: string;
  books_locked_until: string | null;
  logo_url: string | null;
  signature_url: string | null;
  is_onboarded: boolean;
  is_business_complete: boolean;
  customers_count?: number;
  invoices_count?: number;
};

export type Membership = { member_id: number; company_id: number; name: string; gstin: string | null; role: string; role_label: string };

export type CompanyList = { data: Company[]; meta: { active_company_id: number | null; memberships: Membership[] } };

/** Everything PUT /companies/{id} accepts (CompanySettings::validate) — the whole form is sent each time. */
export type CompanyInput = {
  name: string;
  gstin: string | null;
  composition_dealer: boolean;
  books_locked_until: string | null;
  lut_number: string | null;
  pan: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state_id: number | null;
  postal_code: string | null;
  country: string;
  phone: string | null;
  email: string | null;
  website: string | null;
  default_currency: string;
  default_terms: string | null;
  declaration: string | null;
  bank_name: string | null;
  bank_account_name: string | null;
  bank_account_number: string | null;
  bank_account_type: 'savings' | 'current' | null;
  bank_ifsc: string | null;
  bank_branch: string | null;
  upi_id: string | null;
  invoice_prefix: string;
  invoice_number_padding: number;
  invoice_number_format: string | null;
  /** Optional one-shot "next running number"; a bad value comes back in meta.warnings. */
  next_invoice_number?: number | null;
};

/** The saved company as a full settings form, so a screen can change a few fields and send the rest unchanged. */
export function companyInput(c: Company): CompanyInput {
  return {
    name: c.name,
    gstin: c.gstin,
    composition_dealer: c.composition_dealer,
    books_locked_until: c.books_locked_until,
    lut_number: c.lut_number,
    pan: c.pan,
    address_line1: c.address_line1,
    address_line2: c.address_line2,
    city: c.city,
    state_id: c.state_id,
    postal_code: c.postal_code,
    country: c.country || 'India',
    phone: c.phone,
    email: c.email,
    website: c.website,
    default_currency: c.default_currency || 'INR',
    default_terms: c.default_terms,
    declaration: c.declaration,
    bank_name: c.bank_name,
    bank_account_name: c.bank_account_name,
    bank_account_number: c.bank_account_number,
    bank_account_type: c.bank_account_type,
    bank_ifsc: c.bank_ifsc,
    bank_branch: c.bank_branch,
    upi_id: c.upi_id,
    invoice_prefix: c.invoice_prefix,
    invoice_number_padding: c.invoice_number_padding,
    invoice_number_format: c.invoice_number_format,
  };
}

export type CompanySaved = { data: Company; meta: { warnings: { next_invoice_number?: string } } };

export type PickedFile = { uri: string; name: string; type: string };

/* ── Calls ────────────────────────────────────────────────────────────── */

export const teamApi = {
  list: () => get<TeamList>('/team'),
  invite: (email: string, role: string) => post<{ data: TeamMember; meta: { email_sent: boolean; message: string } }>('/team', { email, role }),
  /**
   * Send a pending invitation again. The server answers 429 when it was sent a
   * moment ago (with `retry_after_minutes`) and 409 once accepted — its own
   * message is kept here (the generic client would replace a 429's text).
   */
  resend: async (id: number): Promise<ResendResult> => {
    let res: Response;
    try {
      res = await fetch(`${API_URL}/team/${id}/resend`, { method: 'POST', headers: { Accept: 'application/json', ...authHeader() } });
    } catch {
      throw new ApiError(0, 'Cannot reach Apna Invoice. Check your internet connection and try again.');
    }
    const json = (await res.json().catch(() => null)) as Partial<ResendResult> & { message?: string } | null;
    if (res.ok || res.status === 429 || res.status === 409) {
      return { status: json?.status ?? (res.ok ? 'sent' : 'throttled'), message: json?.message ?? '', retry_after_minutes: json?.retry_after_minutes ?? null, ok: res.ok };
    }
    throw new ApiError(res.status, json?.message || `Something went wrong (${res.status}). Please try again.`);
  },
  changeRole: (id: number, role: string) => put<{ data: TeamMember; message: string }>(`/team/${id}`, { role }),
  remove: (id: number) => del<{ message: string }>(`/team/${id}`),
  /** A member leaves a business they joined (member id from companies meta.memberships). */
  leave: (memberId: number) => post<{ message: string; active_company_id: number | null }>(`/team/leave/${memberId}`),
};

export const notificationsApi = {
  feed: () => get<NotificationFeed>('/notifications'),
  seen: () => post<{ ok: boolean }>('/notifications/seen'),
};

export const searchApi = {
  suggest: (q: string, scope: SearchScope) => get<SearchResult>(`/search/suggest${qs({ q, scope })}`),
};

export const reportsApi = {
  hub: () => get<ReportHub>('/reports'),
  show: (key: string, p: { from?: string; to?: string }) => get<Report>(`/reports/${encodeURIComponent(key)}${qs(p)}`),
  exportPath: (key: string, format: ReportFormat, p: { from?: string; to?: string }) => `/reports/${encodeURIComponent(key)}/export/${format}${qs(p)}`,
};

export const documentsApi = {
  duplicate: (invoiceId: number) => post<{ data: Invoice; message: string }>(`/invoices/${invoiceId}/duplicate`),
  /** Due date, notes, terms and transport of an issued invoice (amounts and lines never change once issued). */
  editIssued: (invoiceId: number, input: IssuedInvoiceEdit) => put<{ data: Invoice }>(`/invoices/${invoiceId}`, input),
  thermal: (invoiceId: number) => get<ThermalPrint>(`/invoices/${invoiceId}/print`),
  templates: () => get<{ data: InvoiceTemplate[]; meta: { styles: { key: string; label: string; accent: string | null }[] } }>('/invoice-templates'),
  templatePreviewPath: (key: string) => `/invoice-templates/${encodeURIComponent(key)}/preview`,
  receiptPath: (paymentId: number) => `/payments/${paymentId}/receipt`,
  quotationPdfPath: (quotationId: number) => `/quotations/${quotationId}/pdf`,
};

export const companyApi = {
  list: () => get<CompanyList>('/companies'),
  active: () => get<{ data: Company }>('/companies/active'),
  create: (input: CompanyInput) => post<{ data: Company }>('/companies', input),
  update: (id: number, input: CompanyInput) => patch<CompanySaved>(`/companies/${id}`, input),
  switchTo: (id: number) => post<{ data: Company }>(`/companies/${id}/switch`),
  /** Logo or signature on its own (multipart). */
  uploadImage: (id: number, field: 'logo' | 'signature', file: PickedFile) => upload<{ data: Company }>(`/companies/${id}/branding`, field, file),
  removeImage: (id: number, field: 'logo' | 'signature') => post<{ data: Company }>(`/companies/${id}/branding`, { [`remove_${field}`]: true }),
};
