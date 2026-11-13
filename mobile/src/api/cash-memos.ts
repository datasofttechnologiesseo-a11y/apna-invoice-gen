/** Cash memos (self-prepared purchase vouchers) — Api\CashMemoController + CashMemo*Resource. */
import { del, get, post, put, qs } from './client';
import type { Paginated } from './types';

export type CashMemoListItem = {
  id: number;
  memo_number: string;
  memo_date: string;
  seller_name: string;
  grand_total: number;
  payment_mode: string;
};

export type CashMemoItem = {
  id: number;
  description: string;
  hsn_sac: string | null;
  quantity: number;
  unit: string | null;
  rate: number;
  amount: number;
  sort_order: number;
};

export type CashMemo = CashMemoListItem & {
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
  is_interstate: boolean;
  reference_number: string | null;
  expense_category: string | null;
  notes: string | null;
  gst_rate?: number;
  itc_eligible?: boolean;
  items: CashMemoItem[];
};

export type CashMemoLine = { description: string; hsn_sac?: string | null; quantity: number; unit?: string | null; rate: number };

export type CashMemoInput = {
  memo_date: string;
  memo_number?: string | null;
  seller_name: string;
  seller_address?: string | null;
  seller_gstin?: string | null;
  seller_phone?: string | null;
  seller_state?: string | null;
  discount?: number;
  gst_rate?: number;
  is_interstate?: boolean;
  payment_mode: string;
  reference_number?: string | null;
  expense_category?: string | null;
  notes?: string | null;
  items: CashMemoLine[];
};

/** Payment modes the API accepts (CashMemoController::validated). */
export const MEMO_MODES = [
  { value: 'cash', label: 'Cash' },
  { value: 'upi', label: 'UPI' },
  { value: 'bank', label: 'Bank' },
  { value: 'card', label: 'Card' },
  { value: 'cheque', label: 'Cheque' },
  { value: 'other', label: 'Other' },
];

/** config/gst.php allowed_values — the memo-level GST rate. */
export const MEMO_GST_RATES = [0, 5, 12, 18, 28, 40];

export const cashMemosApi = {
  list: (p: { page?: number; search?: string; from?: string; to?: string }) =>
    get<Paginated<CashMemoListItem>>(`/cash-memos${qs({ page: p.page, per_page: 25, search: p.search, from: p.from, to: p.to })}`),
  show: (id: number) => get<{ data: CashMemo }>(`/cash-memos/${id}`),
  update: (id: number, input: CashMemoInput) => put<{ data: CashMemo }>(`/cash-memos/${id}`, input),
  create: (input: CashMemoInput) => post<{ data: CashMemo }>('/cash-memos', input),
  remove: (id: number) => del<{ message: string }>(`/cash-memos/${id}`),
  pdfPath: (id: number) => `/cash-memos/${id}/pdf`,
};
