import { router, useLocalSearchParams } from 'expo-router';

import { EmptyState, ErrorState, Loading } from '@/components/blocks';
import { Screen } from '@/components/screen';
import { useQuotation } from '@/hooks/queries';
import { QuoteForm } from '@/screens/quotations/quote-form';

/** Edit a draft quotation. Sent / accepted / converted ones are locked by the server. */
export default function EditQuotation() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useQuotation(Number(id));
  const qt = q.data;

  if (q.isLoading) return <Screen nav="close" title="Edit quotation"><Loading /></Screen>;
  if (q.error || !qt) return <Screen nav="close" title="Edit quotation"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;
  if (!qt.can.edit) {
    return (
      <Screen nav="close" title={qt.display_number}>
        <EmptyState icon="lock-closed-outline" title="This quotation is locked" body="Only drafts can be changed. Make a new quotation instead." action="Back to the quotation" onAction={() => router.back()} />
      </Screen>
    );
  }
  return <QuoteForm key={qt.id} initial={qt} onSaved={() => router.back()} />;
}
