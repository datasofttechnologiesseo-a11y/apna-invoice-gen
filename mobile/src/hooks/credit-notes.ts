/** Credit note queries and mutations for one invoice. Keys start with 'credit-notes'. */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { CreditNoteInput, creditNotesApi } from '@/api/credit-notes';

export const creditNoteKeys = {
  forInvoice: (invoiceId: number) => ['credit-notes', invoiceId] as const,
};

export function useCreditNotes(invoiceId: number) {
  return useQuery({ queryKey: creditNoteKeys.forInvoice(invoiceId), queryFn: () => creditNotesApi.list(invoiceId), enabled: invoiceId > 0 });
}

/** A credit note changes the invoice balance/status, the customer ledger, stock (sales return) and GST. */
function useRefreshAfterCredit(invoiceId: number) {
  const qc = useQueryClient();
  return () => {
    qc.invalidateQueries({ queryKey: creditNoteKeys.forInvoice(invoiceId) });
    qc.invalidateQueries({ queryKey: ['invoice', invoiceId] });
    qc.invalidateQueries({ queryKey: ['invoices'] });
    qc.invalidateQueries({ queryKey: ['dashboard'] });
    qc.invalidateQueries({ queryKey: ['customers'] });
    qc.invalidateQueries({ queryKey: ['ledger'] });
    qc.invalidateQueries({ queryKey: ['finance'] });
  };
}

export function useCreateCreditNote(invoiceId: number) {
  const refresh = useRefreshAfterCredit(invoiceId);
  return useMutation({
    mutationFn: async (input: CreditNoteInput) => (await creditNotesApi.create(invoiceId, input)).data,
    onSuccess: refresh,
  });
}

export function useDeleteCreditNote(invoiceId: number) {
  const refresh = useRefreshAfterCredit(invoiceId);
  return useMutation({
    mutationFn: (id: number) => creditNotesApi.remove(id),
    onSuccess: refresh,
  });
}
