import { useLocalSearchParams } from 'expo-router';

import { ErrorState, Loading } from '@/components/blocks';
import { Screen } from '@/components/screen';
import { usePurchase } from '@/hooks/purchases';
import { PurchaseForm } from '@/screens/purchases/purchase-form';

export default function EditPurchase() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = usePurchase(Number(id));
  if (q.isLoading) return <Screen nav="close" title="Edit purchase invoice"><Loading /></Screen>;
  if (q.error || !q.data) return <Screen nav="close" title="Edit purchase invoice"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;
  return <PurchaseForm key={q.data.id} bill={q.data} />;
}
