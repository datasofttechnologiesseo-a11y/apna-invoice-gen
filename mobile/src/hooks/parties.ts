import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { CustomerInput, partiesApi } from '@/api/parties';
import { keys } from '@/hooks/queries';

export const partyKeys = {
  detail: (id: number) => [...keys.customers, 'detail', id] as const,
};

export function useCustomer(id: number) {
  return useQuery({ queryKey: partyKeys.detail(id), queryFn: async () => (await partiesApi.show(id)).data, enabled: id > 0 });
}

/** Customer name/state shows on lists, ledgers, invoices and quotes — refresh them all. */
function useRefreshParties() {
  const qc = useQueryClient();
  return (id?: number) => {
    qc.invalidateQueries({ queryKey: keys.customers });
    qc.invalidateQueries({ queryKey: keys.dashboard });
    qc.invalidateQueries({ queryKey: keys.invoices });
    qc.invalidateQueries({ queryKey: keys.quotations });
    if (id) {
      qc.invalidateQueries({ queryKey: keys.ledger(id) });
      qc.invalidateQueries({ queryKey: ['invoice'] });
      qc.invalidateQueries({ queryKey: ['quotation'] });
    }
  };
}

export function useSaveCustomer() {
  const refresh = useRefreshParties();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: CustomerInput }) =>
      (id ? await partiesApi.update(id, input) : await partiesApi.create(input)).data,
    onSuccess: (c) => refresh(c.id),
  });
}

export function useDeleteCustomer() {
  const refresh = useRefreshParties();
  return useMutation({ mutationFn: (id: number) => partiesApi.remove(id), onSuccess: () => refresh() });
}
