import { Href, router, useLocalSearchParams } from 'expo-router';
import { Alert } from 'react-native';

import { ApiError } from '@/api/client';
import { ErrorState, Loading } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { useCustomer, useDeleteCustomer } from '@/hooks/parties';
import { canDelete, useRole } from '@/hooks/role';
import { CustomerForm } from '@/screens/parties/customer-form';

/** Edit a customer; delete is offered only when the role allows (the server refuses if they have documents). */
export default function EditCustomer() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useCustomer(Number(id));
  const remove = useDeleteCustomer();
  const role = useRole();
  const c = q.data;

  if (q.isLoading) return <Screen nav="close" title="Edit customer"><Loading /></Screen>;
  if (q.error || !c) return <Screen nav="close" title="Edit customer"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;

  const confirmDelete = () =>
    Alert.alert(`Delete ${c.name}?`, 'A customer with invoices or quotations cannot be deleted.', [
      { text: 'Keep', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () =>
          remove.mutate(c.id, {
            onSuccess: () => router.dismissTo('/parties' as Href),
            onError: (e) => Alert.alert('Could not delete', (e as ApiError).message),
          }),
      },
    ]);

  return (
    <CustomerForm
      key={c.id}
      initial={c}
      title="Edit customer"
      onSaved={() => router.back()}
      bottom={canDelete(role) ? <Button label="Delete customer" icon="trash-outline" kind="danger" size="md" loading={remove.isPending} onPress={confirmDelete} /> : null}
    />
  );
}
