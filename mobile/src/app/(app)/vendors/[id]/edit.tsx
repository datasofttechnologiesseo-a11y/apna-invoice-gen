import { router, useLocalSearchParams } from 'expo-router';

import { ErrorState, Loading } from '@/components/blocks';
import { Screen } from '@/components/screen';
import { useVendor } from '@/hooks/purchases';
import { VendorFormScreen } from '@/screens/purchases/vendor-form';

export default function EditVendor() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useVendor(Number(id));
  if (q.isLoading) return <Screen nav="close" title="Edit vendor"><Loading /></Screen>;
  if (q.error || !q.data) return <Screen nav="close" title="Edit vendor"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;
  return <VendorFormScreen vendor={q.data} onSaved={() => router.back()} />;
}
