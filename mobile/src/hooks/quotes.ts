import { useMutation, useQueryClient } from '@tanstack/react-query';

import { QuotationInput, quotesApi } from '@/api/quotes';
import { keys } from '@/hooks/queries';

/** Create or edit a draft quotation; `send` also marks it sent (which gives it a number). */
export function useSaveQuotation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input, send }: { id?: number; input: QuotationInput; send: boolean }) => {
      const saved = (id ? await quotesApi.update(id, input) : await quotesApi.create(input)).data;
      return send ? (await quotesApi.send(saved.id)).data : saved;
    },
    onSuccess: (qt) => {
      qc.invalidateQueries({ queryKey: keys.quotations });
      qc.invalidateQueries({ queryKey: keys.quotation(qt.id) });
      qc.invalidateQueries({ queryKey: keys.dashboard });
    },
  });
}
