/**
 * Quotation create/edit — mirrors QuotationController::store/update (drafts only).
 * Totals and the GST split are recalculated by the server (InvoiceCalculator).
 */
import { post, put } from './client';
import type { LineItem, Quotation } from './types';

export type QuotationInput = {
  customer_id: number;
  quote_date: string;
  valid_until: string | null;
  subject: string | null;
  reference: string | null;
  delivery_period: string | null;
  notes: string | null;
  terms: string | null;
  items: LineItem[];
};

export const quotesApi = {
  create: (input: QuotationInput) => post<{ data: Quotation }>('/quotations', input),
  update: (id: number, input: QuotationInput) => put<{ data: Quotation }>(`/quotations/${id}`, input),
  send: (id: number) => post<{ data: Quotation }>(`/quotations/${id}/send`),
};
