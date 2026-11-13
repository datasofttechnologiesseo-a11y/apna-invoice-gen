/** Expenses (P&L cost side) — Api\ExpenseController + ExpenseResource. */
import { del, get, post, put, qs } from './client';
import type { Paginated } from './types';

export type Expense = {
  id: number;
  entry_date: string;
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
  /** Set when the entry was written by a cash memo: read-only here, managed via the memo. */
  cash_memo_id: number | null;
  /** Written by a purchase bill — edit the bill instead. */
  purchase_bill_id?: number | null;
  itc_eligible?: boolean;
};

export type ExpenseCategory = { value: string; label: string; color: string };

export type ExpenseTotals = { count: number; amount: number; gst: number; outflow: number };

export type ExpensePage = Omit<Paginated<Expense>, 'meta'> & {
  meta: Paginated<Expense>['meta'] & { totals: ExpenseTotals; categories: ExpenseCategory[] };
};

export type ExpenseFilter = { category?: string; from?: string; to?: string; search?: string };

export type ExpenseInput = {
  entry_date: string;
  category: string;
  vendor_name?: string | null;
  description: string;
  amount: number;
  gst_amount?: number;
  is_interstate?: boolean;
  itc_eligible?: boolean;
  payment_method?: string | null;
  reference_number?: string | null;
  notes?: string | null;
};

/** Payment methods the API accepts (ExpenseController::validated). */
export const EXPENSE_METHODS = [
  { value: 'cash', label: 'Cash' },
  { value: 'upi', label: 'UPI' },
  { value: 'bank', label: 'Bank' },
  { value: 'card', label: 'Card' },
  { value: 'cheque', label: 'Cheque' },
  { value: 'other', label: 'Other' },
];

/** The web expense report takes ?period=custom&from&to; "all time" starts with GST (1 Jul 2017). */
function exportQuery(f: ExpenseFilter, today: string) {
  return qs({ period: 'custom', from: f.from || '2017-07-01', to: f.to || today, category: f.category, search: f.search });
}

export const expensesApi = {
  list: (f: ExpenseFilter & { page?: number; per_page?: number }) =>
    get<ExpensePage>(`/expenses${qs({ page: f.page, per_page: f.per_page ?? 30, category: f.category, from: f.from, to: f.to, search: f.search })}`),
  create: (input: ExpenseInput) => post<{ data: Expense }>('/expenses', input),
  show: (id: number) => get<{ data: Expense }>(`/expenses/${id}`),
  update: (id: number, input: ExpenseInput) => put<{ data: Expense }>(`/expenses/${id}`, input),
  remove: (id: number) => del<{ message: string }>(`/expenses/${id}`),
  csvPath: (f: ExpenseFilter, today: string) => `/finance/expenses/export/csv${exportQuery(f, today)}`,
  pdfPath: (f: ExpenseFilter, today: string) => `/finance/expenses/export/pdf${exportQuery(f, today)}`,
};
