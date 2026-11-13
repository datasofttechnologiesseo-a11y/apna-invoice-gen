/** Cash memo queries and mutations. Keys start with 'cash-memos'. A memo also writes an expense entry. */
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { CashMemoInput, cashMemosApi } from '@/api/cash-memos';
import type { Paginated } from '@/api/types';

export const cashMemoKeys = {
  all: ['cash-memos'] as const,
  list: (search: string, from?: string, to?: string) => ['cash-memos', 'list', search, from ?? '', to ?? ''] as const,
  one: (id: number) => ['cash-memos', 'one', id] as const,
};

export function useCashMemos(search: string, from?: string, to?: string) {
  return useInfiniteQuery({
    queryKey: cashMemoKeys.list(search, from, to),
    queryFn: ({ pageParam }) => cashMemosApi.list({ page: pageParam, search, from, to }),
    initialPageParam: 1,
    getNextPageParam: <T,>(last: Paginated<T>) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  });
}

export function useCashMemo(id: number) {
  return useQuery({ queryKey: cashMemoKeys.one(id), queryFn: async () => (await cashMemosApi.show(id)).data, enabled: id > 0 });
}

function useRefreshMemos() {
  const qc = useQueryClient();
  return () => {
    qc.invalidateQueries({ queryKey: ['cash-memos', 'list'] });
    qc.invalidateQueries({ queryKey: ['expenses', 'list'] });
    qc.invalidateQueries({ queryKey: ['dashboard'] });
    qc.invalidateQueries({ queryKey: ['finance'] });
  };
}

export function useCreateCashMemo() {
  const qc = useQueryClient();
  const refresh = useRefreshMemos();
  return useMutation({
    mutationFn: async (input: CashMemoInput) => (await cashMemosApi.create(input)).data,
    onSuccess: (memo) => {
      qc.setQueryData(cashMemoKeys.one(memo.id), memo);
      refresh();
    },
  });
}

/** Create, or update when `id` is given (PUT /cash-memos/{id} rewrites the linked expense too). */
export function useSaveCashMemo() {
  const qc = useQueryClient();
  const refresh = useRefreshMemos();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: CashMemoInput }) => (id ? await cashMemosApi.update(id, input) : await cashMemosApi.create(input)).data,
    onSuccess: (memo) => {
      qc.setQueryData(cashMemoKeys.one(memo.id), memo);
      refresh();
    },
  });
}

export function useDeleteCashMemo() {
  const refresh = useRefreshMemos();
  return useMutation({
    mutationFn: (id: number) => cashMemosApi.remove(id),
    onSuccess: refresh,
  });
}
