/**
 * Customers — mirrors app/Http/Controllers/Api/CustomerController.php.
 * GSTIN checksum / state-code match is checked by the server (App\Rules\ValidGstin).
 */
import { del, get, post, put } from './client';
import type { Customer } from './types';

export type CustomerInput = {
  name: string;
  gstin: string | null;
  phone: string | null;
  email: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state_id: number;
  postal_code: string | null;
  country: string | null;
};

export const partiesApi = {
  show: (id: number) => get<{ data: Customer }>(`/customers/${id}`),
  create: (input: CustomerInput) => post<{ data: Customer }>('/customers', input),
  update: (id: number, input: CustomerInput) => put<{ data: Customer }>(`/customers/${id}`, input),
  /** 422 with a message when the customer has invoices/quotations. */
  remove: (id: number) => del<{ message: string }>(`/customers/${id}`),
};

/** Shape-only GSTIN check (same pattern as the server); the checksum is the server's job. */
export const GSTIN_PATTERN = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;
