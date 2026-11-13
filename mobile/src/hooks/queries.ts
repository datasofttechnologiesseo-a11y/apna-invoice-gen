/**
 * Server state lives here, one hook per read. Screens import these — never `fetch`.
 * Mutations invalidate the keys they affect so every screen stays in step.
 */
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  customersApi,
  dashboardApi,
  InvoiceFilter,
  invoicesApi,
  InvoiceInput,
  lookupApi,
  productsApi,
  quotationsApi,
} from '@/api/endpoints';
import type { Paginated } from '@/api/types';

export const keys = {
  dashboard: ['dashboard'] as const,
  invoices: ['invoices'] as const,
  invoice: (id: number) => ['invoice', id] as const,
  quotations: ['quotations'] as const,
  quotation: (id: number) => ['quotation', id] as const,
  customers: ['customers'] as const,
  ledger: (id: number) => ['ledger', id] as const,
  products: ['products'] as const,
  states: ['states'] as const,
};

const nextPage = <T,>(last: Paginated<T>) =>
  last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined;

export function useDashboard() {
  return useQuery({ queryKey: keys.dashboard, queryFn: async () => (await dashboardApi.get()).data });
}

export function useInvoices(status: InvoiceFilter, search: string) {
  return useInfiniteQuery({
    queryKey: [...keys.invoices, status, search],
    queryFn: ({ pageParam }) => invoicesApi.list({ page: pageParam, status, search }),
    initialPageParam: 1,
    getNextPageParam: nextPage,
  });
}

export function useInvoice(id: number) {
  return useQuery({ queryKey: keys.invoice(id), queryFn: async () => (await invoicesApi.show(id)).data, enabled: id > 0 });
}

export function useQuotations(status: string, search: string) {
  return useInfiniteQuery({
    queryKey: [...keys.quotations, status, search],
    queryFn: ({ pageParam }) => quotationsApi.list({ page: pageParam, status, search }),
    initialPageParam: 1,
    getNextPageParam: nextPage,
  });
}

export function useQuotation(id: number) {
  return useQuery({ queryKey: keys.quotation(id), queryFn: async () => (await quotationsApi.show(id)).data });
}

export function useCustomers(search: string) {
  return useInfiniteQuery({
    queryKey: [...keys.customers, search],
    queryFn: ({ pageParam }) => customersApi.list({ page: pageParam, search }),
    initialPageParam: 1,
    getNextPageParam: nextPage,
  });
}

export function useLedger(id: number) {
  return useQuery({ queryKey: keys.ledger(id), queryFn: () => customersApi.ledger(id) });
}

export function useProductSearch(q: string) {
  return useQuery({ queryKey: [...keys.products, 'search', q], queryFn: () => productsApi.search(q), staleTime: 60_000 });
}

export function useStates() {
  return useQuery({ queryKey: keys.states, queryFn: async () => (await lookupApi.states()).data, staleTime: Infinity });
}

/** After anything that changes money, refresh every view that shows money. */
function useInvalidateMoney() {
  const qc = useQueryClient();
  return (invoiceId?: number) => {
    qc.invalidateQueries({ queryKey: keys.dashboard });
    qc.invalidateQueries({ queryKey: keys.invoices });
    qc.invalidateQueries({ queryKey: keys.customers });
    qc.invalidateQueries({ queryKey: ['ledger'] });
    if (invoiceId) qc.invalidateQueries({ queryKey: keys.invoice(invoiceId) });
  };
}

export function useSaveInvoice() {
  const refresh = useInvalidateMoney();
  return useMutation({
    mutationFn: async ({ id, input, issue }: { id?: number; input: InvoiceInput; issue: boolean }) => {
      const saved = id ? (await invoicesApi.update(id, input)).data : (await invoicesApi.create(input)).data;
      return issue ? (await invoicesApi.finalize(saved.id)).data : saved;
    },
    onSuccess: (inv) => refresh(inv.id),
  });
}

export function useInvoiceAction(id: number) {
  const refresh = useInvalidateMoney();
  return useMutation({
    mutationFn: async (a: { type: 'finalize' } | { type: 'cancel'; reason: string } | { type: 'delete' }) => {
      if (a.type === 'finalize') return invoicesApi.finalize(id);
      if (a.type === 'cancel') return invoicesApi.cancel(id, a.reason);
      return invoicesApi.remove(id);
    },
    onSuccess: () => refresh(id),
  });
}

export function useRecordPayment(id: number) {
  const refresh = useInvalidateMoney();
  return useMutation({
    mutationFn: (input: { amount: number; method: string; received_at: string; reference_number?: string }) =>
      invoicesApi.recordPayment(id, input),
    onSuccess: () => refresh(id),
  });
}

export function useQuotationAction(id: number) {
  const qc = useQueryClient();
  const refresh = useInvalidateMoney();
  return useMutation({
    mutationFn: (a: { type: 'send' | 'accept' | 'convert' } | { type: 'decline'; reason: string }) =>
      quotationsApi.action(id, a.type, a.type === 'decline' ? { decline_reason: a.reason } : {}),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: keys.quotations });
      qc.invalidateQueries({ queryKey: keys.quotation(id) });
      refresh();
    },
  });
}

export function useCreateCustomer() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: customersApi.create,
    onSuccess: () => qc.invalidateQueries({ queryKey: keys.customers }),
  });
}
