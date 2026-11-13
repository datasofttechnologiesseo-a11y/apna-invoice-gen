/** Credit notes (CGST s.34), always against one invoice — Api\CreditNoteController + CreditNoteResource. */
import { del, get, post } from './client';

export type CreditNote = {
  id: number;
  invoice_id: number;
  credit_note_number: string;
  credit_note_date: string;
  amount: number;
  taxable_value: number;
  total_cgst: number;
  total_sgst: number;
  total_igst: number;
  reason: string;
  reason_label: string;
  notes: string | null;
};

export type CreditNoteReason = { value: string; label: string; hint: string | null };

export type CreditNotesResponse = {
  data: CreditNote[];
  meta: {
    /** How much of the invoice can still be credited. */
    creditable: number;
    /** Section 34(2) deadline has passed. */
    window_closed: boolean;
    deadline: string | null;
    can_create: boolean;
    reasons: CreditNoteReason[];
  };
};

export type CreditNoteInput = {
  credit_note_date: string;
  reason: string;
  notes?: string | null;
  /** Amount-only note (price reduction): the server splits the tax pro rata. */
  amount?: number;
  /** Sales return: invoice item id → quantity returned. The note is the sum of these lines. */
  lines?: Record<string, number>;
};

export const creditNotesApi = {
  list: (invoiceId: number) => get<CreditNotesResponse>(`/invoices/${invoiceId}/credit-notes`),
  create: (invoiceId: number, input: CreditNoteInput) => post<{ data: CreditNote }>(`/invoices/${invoiceId}/credit-notes`, input),
  remove: (id: number) => del<{ message: string }>(`/credit-notes/${id}`),
  pdfPath: (id: number) => `/credit-notes/${id}/pdf`,
};
