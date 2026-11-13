import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { ItemFilter, ItemInput, itemInputOf, itemsApi, ItemDetail } from '@/api/items';
import type { Paginated } from '@/api/types';
import { keys } from '@/hooks/queries';

const nextPage = <T,>(last: Paginated<T>) =>
  last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined;

/** All item queries sit under ['products'] so the invoice item picker refreshes too. */
export const itemKeys = {
  list: (filter: ItemFilter, search: string) => [...keys.products, 'list', filter, search] as const,
  detail: (id: number) => [...keys.products, 'detail', id] as const,
};

export function useItems(filter: ItemFilter, search: string) {
  return useInfiniteQuery({
    queryKey: itemKeys.list(filter, search),
    queryFn: ({ pageParam }) => itemsApi.list({ page: pageParam, filter, search }),
    initialPageParam: 1,
    getNextPageParam: nextPage,
  });
}

export function useItem(id: number) {
  return useQuery({ queryKey: itemKeys.detail(id), queryFn: async () => (await itemsApi.show(id)).data, enabled: id > 0 });
}

function useRefreshItems() {
  const qc = useQueryClient();
  return () => {
    qc.invalidateQueries({ queryKey: keys.products });
    qc.invalidateQueries({ queryKey: keys.dashboard });
  };
}

export function useSaveItem() {
  const refresh = useRefreshItems();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: ItemInput }) =>
      (id ? await itemsApi.update(id, input) : await itemsApi.create(input)).data,
    onSuccess: refresh,
  });
}

export function useDeleteItem() {
  const refresh = useRefreshItems();
  return useMutation({ mutationFn: (id: number) => itemsApi.remove(id), onSuccess: refresh });
}

/**
 * Put an archived item back. The API's restore route is not registered, so this
 * re-saves the item with is_active: true (the update endpoint accepts it).
 */
export function useRestoreItem() {
  const refresh = useRefreshItems();
  return useMutation({
    mutationFn: async (item: ItemDetail) => (await itemsApi.update(item.id, { ...itemInputOf(item), is_active: true })).data,
    onSuccess: refresh,
  });
}
