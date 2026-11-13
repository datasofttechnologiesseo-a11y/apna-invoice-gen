/**
 * Every API call the app makes, grouped by module (mirrors routes/api.php).
 * Screens never call fetch directly — they use the hooks in src/hooks/queries.ts.
 */
import { del, get, post, put, qs } from './client';
import type {
  AuthResponse,
  Customer,
  Dashboard,
  Invoice,
  InvoiceListItem,
  Ledger,
  LineItem,
  Me,
  Paginated,
  Payment,
  Product,
  Quotation,
  QuotationListItem,
  State,
} from './types';

const DEVICE = 'Apna Invoice app';

export const authApi = {
  login: (email: string, password: string) =>
    post<AuthResponse>('/auth/login', { email, password, device_name: DEVICE }),
  register: (input: { name: string; email: string; phone: string; password: string; password_confirmation: string; referral_code?: string }) =>
    post<AuthResponse>('/auth/register', { ...input, device_name: DEVICE }),
  me: () => get<Me>('/me'),
  logout: () => post<unknown>('/auth/logout'),
  deleteAccount: (password: string) => del<{ message: string; mode: string }>('/profile', { password }),
};

export const lookupApi = {
  states: () => get<{ data: State[] }>('/states'),
};

export const dashboardApi = {
  get: () => get<{ data: Dashboard }>('/dashboard'),
};

export type InvoiceFilter = 'all' | 'outstanding' | 'draft' | 'paid' | 'partially_paid' | 'cancelled';

export type InvoiceInput = {
  customer_id: number;
  invoice_date: string;
  due_date?: string | null;
  items: LineItem[];
  notes?: string | null;
  terms?: string | null;
  transporter_name?: string | null;
  vehicle_number?: string | null;
  eway_bill_number?: string | null;
  reverse_charge?: boolean;
};

export const invoicesApi = {
  list: (p: { page?: number; status?: InvoiceFilter; search?: string }) =>
    get<Paginated<InvoiceListItem>>(
      `/invoices${qs({ page: p.page, per_page: 20, status: p.status === 'all' ? undefined : p.status, search: p.search })}`,
    ),
  show: (id: number) => get<{ data: Invoice }>(`/invoices/${id}`),
  create: (input: InvoiceInput) => post<{ data: Invoice }>('/invoices', input),
  update: (id: number, input: InvoiceInput) => put<{ data: Invoice }>(`/invoices/${id}`, input),
  finalize: (id: number) => post<{ data: Invoice }>(`/invoices/${id}/finalize`),
  cancel: (id: number, reason: string) => post<{ data: Invoice }>(`/invoices/${id}/cancel`, { cancellation_reason: reason }),
  remove: (id: number) => del<unknown>(`/invoices/${id}`),
  shareLink: (id: number) => get<{ url: string; whatsapp_link?: string }>(`/invoices/${id}/share-link`),
  remind: (id: number, channel: 'whatsapp' | 'email' | 'sms') => post<{ message?: string; whatsapp_link?: string }>(`/invoices/${id}/remind`, { channel }),
  recordPayment: (id: number, input: { amount: number; method: string; received_at: string; reference_number?: string }) =>
    post<{ data: Payment }>(`/invoices/${id}/payments`, input),
  pdfPath: (id: number) => `/invoices/${id}/pdf`,
};

export const quotationsApi = {
  list: (p: { page?: number; status?: string; search?: string }) =>
    get<Paginated<QuotationListItem>>(`/quotations${qs({ page: p.page, per_page: 20, status: p.status === 'all' ? undefined : p.status, search: p.search })}`),
  show: (id: number) => get<{ data: Quotation }>(`/quotations/${id}`),
  action: (id: number, action: 'send' | 'accept' | 'decline' | 'convert', body: object = {}) =>
    post<{ data: any }>(`/quotations/${id}/${action}`, body),
  remove: (id: number) => del<unknown>(`/quotations/${id}`),
};

export const customersApi = {
  list: (p: { page?: number; search?: string }) => get<Paginated<Customer>>(`/customers${qs({ page: p.page, per_page: 30, search: p.search })}`),
  create: (input: Partial<Customer> & { name: string; state_id: number }) => post<{ data: Customer }>('/customers', input),
  ledger: (id: number) => get<Ledger>(`/customers/${id}/ledger`),
};

export const productsApi = {
  list: (p: { page?: number; search?: string }) => get<Paginated<Product>>(`/products${qs({ page: p.page, per_page: 30, search: p.search })}`),
  /** Returns a bare array; numbers arrive as strings ("1500.00"). */
  search: (q: string) =>
    get<{ id: number; name: string; hsn_sac: string | null; unit: string; rate: string; gst_rate: string; stock: number | null }[]>(
      `/products/search${qs({ q })}`,
    ),
};
