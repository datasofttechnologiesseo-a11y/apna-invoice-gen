import { api } from './client';
import type {
  ActivityLog,
  AuthResponse,
  BackupStatus,
  BlogListItem,
  BlogPost,
  CashMemo,
  CashMemoInput,
  CashMemoListItem,
  Company,
  CompanyInput,
  CreditNote,
  CreditNotesResponse,
  Customer,
  CustomerLedger,
  DashboardData,
  Expense,
  ExpenseInput,
  ExpensesResponse,
  AgingReport,
  Gstr1Report,
  Gstr3bReport,
  PnlReport,
  Invoice,
  InvoiceInput,
  InvoiceListItem,
  MeResponse,
  Paginated,
  Payment,
  Product,
  Quotation,
  QuotationInput,
  QuotationListItem,
  ReferralData,
  State,
} from './types';

// --- Auth ---
export async function register(input: {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/auth/register', { ...input, device_name: 'expo' });
  return data;
}

export async function login(input: { email: string; password: string }): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/auth/login', { ...input, device_name: 'expo' });
  return data;
}

export async function me(): Promise<MeResponse> {
  const { data } = await api.get<MeResponse>('/me');
  return data;
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout');
}

// --- Lookups ---
export async function getStates(): Promise<State[]> {
  const { data } = await api.get<{ data: State[] }>('/states');
  return data.data;
}

// --- Blog (public) ---
export async function getBlogPosts(search?: string): Promise<BlogListItem[]> {
  const { data } = await api.get<{ data: BlogListItem[] }>('/blog', { params: { search } });
  return data.data;
}

export async function getBlogPost(slug: string): Promise<BlogPost> {
  const { data } = await api.get<BlogPost>(`/blog/${slug}`);
  return data;
}

// --- Dashboard ---
export async function getDashboard(): Promise<DashboardData> {
  const { data } = await api.get<{ data: DashboardData }>('/dashboard');
  return data.data;
}

// --- Referrals ---
export async function getReferrals(): Promise<ReferralData> {
  const { data } = await api.get<ReferralData>('/referrals');
  return data;
}

// --- Profile / account ---
export async function updateProfile(input: { name: string; email: string }): Promise<void> {
  await api.patch('/profile', input);
}

export async function updatePassword(input: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  await api.put('/profile/password', input);
}

export async function getActivity(): Promise<ActivityLog[]> {
  const { data } = await api.get<{ data: ActivityLog[] }>('/profile/activity');
  return data.data;
}

export async function deleteAccount(password: string): Promise<void> {
  await api.delete('/profile', { data: { password } });
}

// --- Backups ---
export async function getBackupStatus(): Promise<BackupStatus> {
  const { data } = await api.get<BackupStatus>('/backup');
  return data;
}

export async function emailBackup(): Promise<{ message: string }> {
  const { data } = await api.post<{ message: string }>('/backup/email');
  return data;
}

export async function toggleBackup(enabled: boolean): Promise<BackupStatus> {
  const { data } = await api.post<BackupStatus>('/backup/toggle', { auto_backup_enabled: enabled });
  return data;
}

// --- Company ---
export async function getActiveCompany(): Promise<Company> {
  const { data } = await api.get<{ data: Company }>('/companies/active');
  return data.data;
}

export async function getCompanies(): Promise<{ companies: Company[]; activeCompanyId: number | null }> {
  const { data } = await api.get<{ data: Company[]; meta?: { active_company_id: number | null } }>('/companies');
  return { companies: data.data, activeCompanyId: data.meta?.active_company_id ?? null };
}

export async function createCompany(input: CompanyInput): Promise<Company> {
  const { data } = await api.post<{ data: Company }>('/companies', input);
  return data.data;
}

export async function switchCompany(id: number): Promise<Company> {
  const { data } = await api.post<{ data: Company }>(`/companies/${id}/switch`);
  return data.data;
}

export async function updateCompany(id: number, input: CompanyInput): Promise<Company> {
  const { data } = await api.patch<{ data: Company }>(`/companies/${id}`, input);
  return data.data;
}

// --- Customer ledger ---
export async function getCustomerLedger(customerId: number): Promise<CustomerLedger> {
  const { data } = await api.get<CustomerLedger>(`/customers/${customerId}/ledger`);
  return data;
}

// --- Credit notes (against an invoice) ---
export async function getCreditNotes(invoiceId: number): Promise<CreditNotesResponse> {
  const { data } = await api.get<CreditNotesResponse>(`/invoices/${invoiceId}/credit-notes`);
  return data;
}

export async function createCreditNote(
  invoiceId: number,
  input: { credit_note_date: string; amount: number; reason: string; notes?: string },
): Promise<CreditNote> {
  const { data } = await api.post<{ data: CreditNote }>(`/invoices/${invoiceId}/credit-notes`, input);
  return data.data;
}

export async function deleteCreditNote(id: number): Promise<void> {
  await api.delete(`/credit-notes/${id}`);
}

// --- Cash memos (purchase vouchers) ---
export async function getCashMemos(search?: string): Promise<CashMemoListItem[]> {
  const { data } = await api.get<Paginated<CashMemoListItem>>('/cash-memos', {
    params: { search, per_page: 50 },
  });
  return data.data;
}

export async function getCashMemo(id: number): Promise<CashMemo> {
  const { data } = await api.get<{ data: CashMemo }>(`/cash-memos/${id}`);
  return data.data;
}

export async function createCashMemo(input: CashMemoInput): Promise<CashMemo> {
  const { data } = await api.post<{ data: CashMemo }>('/cash-memos', input);
  return data.data;
}

export async function deleteCashMemo(id: number): Promise<void> {
  await api.delete(`/cash-memos/${id}`);
}

// --- Expenses ---
export async function getExpenses(params?: { category?: string; search?: string }): Promise<ExpensesResponse> {
  const { data } = await api.get<ExpensesResponse>('/expenses', { params: { ...params, per_page: 100 } });
  return data;
}

export async function createExpense(input: ExpenseInput): Promise<Expense> {
  const { data } = await api.post<{ data: Expense }>('/expenses', input);
  return data.data;
}

export async function updateExpense(id: number, input: ExpenseInput): Promise<Expense> {
  const { data } = await api.patch<{ data: Expense }>(`/expenses/${id}`, input);
  return data.data;
}

export async function deleteExpense(id: number): Promise<void> {
  await api.delete(`/expenses/${id}`);
}

// --- Finance reports ---
export async function getPnl(period: string): Promise<PnlReport> {
  const { data } = await api.get<PnlReport>('/finance/pnl', { params: { period } });
  return data;
}

export async function getAging(): Promise<AgingReport> {
  const { data } = await api.get<AgingReport>('/finance/aging');
  return data;
}

export async function getGstr3b(month?: string): Promise<Gstr3bReport> {
  const { data } = await api.get<Gstr3bReport>('/finance/gstr3b', { params: { month } });
  return data;
}

export async function getGstr1(from: string, to: string): Promise<Gstr1Report> {
  const { data } = await api.get<Gstr1Report>('/finance/gstr1', { params: { from, to } });
  return data;
}

// --- Customers ---
export async function getCustomers(search?: string): Promise<Customer[]> {
  const { data } = await api.get<Paginated<Customer>>('/customers', { params: { search, per_page: 100 } });
  return data.data;
}

export async function createCustomer(input: Partial<Customer>): Promise<Customer> {
  const { data } = await api.post<{ data: Customer }>('/customers', input);
  return data.data;
}

export async function updateCustomer(id: number, input: Partial<Customer>): Promise<Customer> {
  const { data } = await api.patch<{ data: Customer }>(`/customers/${id}`, input);
  return data.data;
}

export async function deleteCustomer(id: number): Promise<void> {
  await api.delete(`/customers/${id}`);
}

// --- Products ---
export async function getProducts(search?: string): Promise<Product[]> {
  const { data } = await api.get<Paginated<Product>>('/products', { params: { search, per_page: 100 } });
  return data.data;
}

export async function createProduct(input: Partial<Product>): Promise<Product> {
  const { data } = await api.post<{ data: Product }>('/products', input);
  return data.data;
}

export async function updateProduct(id: number, input: Partial<Product>): Promise<Product> {
  const { data } = await api.patch<{ data: Product }>(`/products/${id}`, input);
  return data.data;
}

export async function deleteProduct(id: number): Promise<void> {
  await api.delete(`/products/${id}`);
}

// --- Invoices ---
export async function getInvoices(params?: { status?: string; search?: string }): Promise<InvoiceListItem[]> {
  const { data } = await api.get<Paginated<InvoiceListItem>>('/invoices', {
    params: { ...params, per_page: 50 },
  });
  return data.data;
}

export async function getInvoice(id: number): Promise<Invoice> {
  const { data } = await api.get<{ data: Invoice }>(`/invoices/${id}`);
  return data.data;
}

export async function createInvoice(input: InvoiceInput): Promise<Invoice> {
  const { data } = await api.post<{ data: Invoice }>('/invoices', input);
  return data.data;
}

export async function updateInvoice(id: number, input: Partial<InvoiceInput>): Promise<Invoice> {
  const { data } = await api.patch<{ data: Invoice }>(`/invoices/${id}`, input);
  return data.data;
}

export async function deleteInvoice(id: number): Promise<void> {
  await api.delete(`/invoices/${id}`);
}

export async function finalizeInvoice(id: number): Promise<Invoice> {
  const { data } = await api.post<{ data: Invoice }>(`/invoices/${id}/finalize`);
  return data.data;
}

export async function cancelInvoice(id: number, reason: string): Promise<Invoice> {
  const { data } = await api.post<{ data: Invoice }>(`/invoices/${id}/cancel`, {
    cancellation_reason: reason,
  });
  return data.data;
}

export async function getShareLink(id: number): Promise<{ url: string; whatsapp_link: string | null }> {
  const { data } = await api.get<{ url: string; whatsapp_link: string | null }>(`/invoices/${id}/share-link`);
  return data;
}

export async function sendInvoiceReminder(
  id: number,
  channel: 'email' | 'whatsapp' | 'sms',
): Promise<{ status: string; message: string }> {
  const { data } = await api.post<{ status: string; message: string }>(`/invoices/${id}/remind`, { channel });
  return data;
}

export async function recordPayment(
  invoiceId: number,
  input: { amount: number; method: string; received_at: string; reference_number?: string; notes?: string },
): Promise<Payment> {
  const { data } = await api.post<{ data: Payment }>(`/invoices/${invoiceId}/payments`, input);
  return data.data;
}

export async function deletePayment(paymentId: number): Promise<void> {
  await api.delete(`/payments/${paymentId}`);
}

// --- Quotations ---
export async function getQuotations(params?: { status?: string; search?: string }): Promise<QuotationListItem[]> {
  const { data } = await api.get<Paginated<QuotationListItem>>('/quotations', {
    params: { ...params, per_page: 50 },
  });
  return data.data;
}

export async function getQuotation(id: number): Promise<Quotation> {
  const { data } = await api.get<{ data: Quotation }>(`/quotations/${id}`);
  return data.data;
}

export async function createQuotation(input: QuotationInput): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>('/quotations', input);
  return data.data;
}

export async function updateQuotation(id: number, input: Partial<QuotationInput>): Promise<Quotation> {
  const { data } = await api.patch<{ data: Quotation }>(`/quotations/${id}`, input);
  return data.data;
}

export async function deleteQuotation(id: number): Promise<void> {
  await api.delete(`/quotations/${id}`);
}

export async function sendQuotation(id: number): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>(`/quotations/${id}/send`);
  return data.data;
}

export async function acceptQuotation(id: number): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>(`/quotations/${id}/accept`);
  return data.data;
}

export async function declineQuotation(id: number, reason?: string): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>(`/quotations/${id}/decline`, {
    decline_reason: reason,
  });
  return data.data;
}

export async function convertQuotation(id: number): Promise<Invoice> {
  const { data } = await api.post<{ data: Invoice }>(`/quotations/${id}/convert`);
  return data.data;
}

export async function getQuotationShareLink(id: number): Promise<{ url: string; whatsapp_link: string | null }> {
  const { data } = await api.get<{ url: string; whatsapp_link: string | null }>(`/quotations/${id}/share-link`);
  return data;
}
