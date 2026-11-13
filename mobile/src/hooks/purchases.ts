/**
 * Vendors, purchase invoices, GSTR-2B and stock — TanStack Query hooks.
 * Every mutation refreshes its own keys plus ['dashboard'] and ['products']
 * (a purchase moves stock and the purchase price).
 */
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  gstr2bApi,
  hsnApi,
  PaymentInput,
  PickedFile,
  PurchaseInput,
  PurchaseListParams,
  purchasesApi,
  StockAdjustInput,
  stockApi,
  VendorInput,
  vendorsApi,
} from '@/api/purchases';
import type { Paginated } from '@/api/types';
import { keys } from '@/hooks/queries';

const nextPage = <T,>(last: Paginated<T>) =>
  last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined;

export const purchaseKeys = {
  vendors: ['vendors'] as const,
  vendorList: (search: string) => ['vendors', 'list', search] as const,
  vendor: (id: number) => ['vendors', 'detail', id] as const,
  purchases: ['purchases'] as const,
  purchaseList: (p: Omit<PurchaseListParams, 'page'>) => ['purchases', 'list', p] as const,
  purchase: (id: number) => ['purchases', 'detail', id] as const,
  gstr2b: ['gstr2b'] as const,
  gstr2bMonth: (month: string) => ['gstr2b', month] as const,
  hsn: (from: string, to: string) => ['gst', 'hsn', from, to] as const,
  /** Under ['products'] so item screens refresh with it. */
  stock: (id: number) => [...keys.products, 'stock', id] as const,
};

// ---- Vendors -----------------------------------------------------------------

export function useVendors(search: string) {
  return useInfiniteQuery({
    queryKey: purchaseKeys.vendorList(search),
    queryFn: ({ pageParam }) => vendorsApi.list({ page: pageParam, search }),
    initialPageParam: 1,
    getNextPageParam: nextPage,
  });
}

export function useVendor(id: number) {
  return useQuery({ queryKey: purchaseKeys.vendor(id), queryFn: async () => (await vendorsApi.show(id)).data, enabled: id > 0 });
}

export function useSaveVendor() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: VendorInput }) => (id ? await vendorsApi.update(id, input) : await vendorsApi.create(input)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: purchaseKeys.vendors });
      qc.invalidateQueries({ queryKey: purchaseKeys.purchases });
    },
  });
}

export function useDeleteVendor() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => vendorsApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: purchaseKeys.vendors }),
  });
}

// ---- Purchases ---------------------------------------------------------------

export function usePurchases(p: Omit<PurchaseListParams, 'page'>) {
  return useInfiniteQuery({
    queryKey: purchaseKeys.purchaseList(p),
    queryFn: ({ pageParam }) => purchasesApi.list({ ...p, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: nextPage,
  });
}

/** Form options (expense categories, payment methods) ride on the list's meta. */
export function usePurchaseOptions() {
  return useQuery({
    queryKey: [...purchaseKeys.purchases, 'options'],
    queryFn: async () => {
      const r = await purchasesApi.list({ per_page: 1 });
      return { categories: r.meta.categories, methods: r.meta.methods };
    },
    staleTime: 60 * 60_000,
  });
}

export function usePurchase(id: number) {
  return useQuery({ queryKey: purchaseKeys.purchase(id), queryFn: async () => (await purchasesApi.show(id)).data, enabled: id > 0 });
}

function useRefreshPurchases() {
  const qc = useQueryClient();
  return () => {
    qc.invalidateQueries({ queryKey: purchaseKeys.purchases });
    qc.invalidateQueries({ queryKey: purchaseKeys.vendors });
    qc.invalidateQueries({ queryKey: purchaseKeys.gstr2b });
    qc.invalidateQueries({ queryKey: keys.dashboard });
    qc.invalidateQueries({ queryKey: keys.products });
    qc.invalidateQueries({ queryKey: ['expenses'] });
  };
}

export function useSavePurchase() {
  const refresh = useRefreshPurchases();
  return useMutation({
    mutationFn: ({ id, input }: { id?: number; input: PurchaseInput }) => (id ? purchasesApi.update(id, input) : purchasesApi.create(input)),
    onSuccess: refresh,
  });
}

export function useDeletePurchase() {
  const refresh = useRefreshPurchases();
  return useMutation({ mutationFn: (id: number) => purchasesApi.remove(id), onSuccess: refresh });
}

export function usePayPurchase(id: number) {
  const refresh = useRefreshPurchases();
  return useMutation({ mutationFn: (input: PaymentInput) => purchasesApi.pay(id, input), onSuccess: refresh });
}

export function useRemovePurchasePayment(id: number) {
  const refresh = useRefreshPurchases();
  return useMutation({ mutationFn: (paymentId: number) => purchasesApi.removePayment(id, paymentId), onSuccess: refresh });
}

// ---- GSTR-2B / HSN -----------------------------------------------------------

export function useGstr2b(month: string) {
  return useQuery({ queryKey: purchaseKeys.gstr2bMonth(month), queryFn: () => gstr2bApi.show(month) });
}

export function useUploadGstr2b() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ file, month }: { file: PickedFile; month: string }) => gstr2bApi.upload(file, month),
    onSuccess: (r) => {
      qc.setQueryData(purchaseKeys.gstr2bMonth(r.period), r);
      qc.invalidateQueries({ queryKey: purchaseKeys.gstr2b });
      qc.invalidateQueries({ queryKey: keys.dashboard });
    },
  });
}

export function useHsn(from: string, to: string) {
  return useQuery({ queryKey: purchaseKeys.hsn(from, to), queryFn: async () => (await hsnApi.show(from, to)).hsn });
}

// ---- Stock -------------------------------------------------------------------

export function useStock(productId: number) {
  return useQuery({ queryKey: purchaseKeys.stock(productId), queryFn: () => stockApi.show(productId), enabled: productId > 0 });
}

export function useAdjustStock(productId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: StockAdjustInput) => stockApi.adjust(productId, input),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: keys.products });
      qc.invalidateQueries({ queryKey: keys.dashboard });
    },
  });
}

export function useRestoreProduct() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (productId: number) => stockApi.restore(productId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: keys.products });
      qc.invalidateQueries({ queryKey: keys.dashboard });
    },
  });
}
