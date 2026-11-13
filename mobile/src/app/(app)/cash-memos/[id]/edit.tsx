import { useLocalSearchParams } from 'expo-router';

import { ErrorState, Loading } from '@/components/blocks';
import { Screen } from '@/components/screen';
import { useCashMemo } from '@/hooks/cash-memos';
import { MemoForm } from '@/screens/cash-memos/memo-form';

/** Edit a cash memo — same form as new, prefilled. */
export default function EditCashMemo() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useCashMemo(Number(id));
  if (q.isLoading)
    return (
      <Screen nav="close" title="Edit cash memo">
        <Loading />
      </Screen>
    );
  if (!q.data)
    return (
      <Screen nav="close" title="Edit cash memo">
        <ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} />
      </Screen>
    );
  return <MemoForm key={q.data.id} initial={q.data} />;
}
