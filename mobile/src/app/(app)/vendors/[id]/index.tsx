import { router, useLocalSearchParams, type Href } from 'expo-router';
import { Alert, Linking, View } from 'react-native';

import { ApiError } from '@/api/client';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, Loading, SectionTitle } from '@/components/blocks';
import { Button } from '@/components/button';
import { BarAction, Screen } from '@/components/screen';
import { useDeleteVendor, usePurchases, useVendor } from '@/hooks/purchases';
import { canDelete, useRole } from '@/hooks/role';
import { PurchaseRow } from '@/screens/purchases/purchase-row';
import { colors, shadow } from '@/theme';
import { money } from '@/utils/format';

/** Vendor: what is still to pay, contact buttons and their purchase invoices. */
export default function VendorDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const vendorId = Number(id);
  const q = useVendor(vendorId);
  const bills = usePurchases({ vendor: vendorId });
  const remove = useDeleteVendor();
  const role = useRole();
  const v = q.data;

  if (q.isLoading) return <Screen hero nav="back" title="Vendor"><Loading /></Screen>;
  if (q.error || !v) return <Screen hero nav="back" title="Vendor"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;

  const phone = v.phone?.replace(/\D/g, '').slice(-10);
  const list = bills.data?.pages.flatMap((p) => p.data) ?? [];
  const address = [v.address_line1, v.address_line2, v.city, v.state_name, v.postal_code].filter(Boolean).join(', ');

  const confirmDelete = () =>
    Alert.alert(`Delete ${v.name}?`, 'This cannot be undone.', [
      { text: 'Keep', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () =>
          remove.mutate(vendorId, {
            onSuccess: () => router.back(),
            onError: (e) => Alert.alert('Could not delete', (e as ApiError).message),
          }),
      },
    ]);

  return (
    <Screen
      hero
      nav="back"
      title={v.name}
      subtitle={[v.gstin ?? 'No GSTIN', v.state_name].filter(Boolean).join(' · ')}
      onRefresh={() => {
        q.refetch();
        bills.refetch();
      }}
      refreshing={q.isRefetching}
      right={<BarAction hero icon="create-outline" label="Edit vendor" onPress={() => router.push(`/vendors/${v.id}/edit` as Href)} />}
      headerExtra={
        phone ? (
          <View style={{ flexDirection: 'row', gap: 8, paddingBottom: 30 }}>
            <Button label="Call" icon="call-outline" kind="glass" size="sm" onPress={() => Linking.openURL(`tel:+91${phone}`)} />
            <Button label="WhatsApp" icon="logo-whatsapp" kind="glass" size="sm" onPress={() => Linking.openURL(`https://wa.me/91${phone}`)} />
          </View>
        ) : (
          <View style={{ height: 30 }} />
        )
      }
      footer={<Button label="New purchase bill" icon="add" onPress={() => router.push(`/purchases/new?vendor=${v.id}` as Href)} />}
    >
      <View style={[{ backgroundColor: colors.surface, borderRadius: 22, padding: 16, marginTop: -44 }, shadow.card]}>
        <AppText variant="small" color={colors.textMuted}>To pay</AppText>
        <AppText variant="hero" style={{ fontSize: 28 }}>{money(v.payable ?? 0)}</AppText>
        <AppText variant="small" color={colors.textMuted}>
          {v.purchase_bills_count ?? 0} purchase invoice{v.purchase_bills_count === 1 ? '' : 's'} on record
        </AppText>
      </View>

      {address || v.email || v.notes ? (
        <Card style={{ gap: 6 }}>
          {address ? <AppText variant="small" color={colors.textBody}>{address}</AppText> : null}
          {v.email ? <AppText variant="small" color={colors.textBody}>{v.email}</AppText> : null}
          {v.notes ? <AppText variant="small" color={colors.textMuted}>{v.notes}</AppText> : null}
        </Card>
      ) : null}

      <SectionTitle title="Purchase invoices" />
      <Card style={{ paddingVertical: 2, gap: 0 }}>
        {bills.isLoading ? (
          <Loading />
        ) : bills.error ? (
          <ErrorState message={(bills.error as Error).message} onRetry={() => bills.refetch()} />
        ) : list.length ? (
          list.map((b, i) => <PurchaseRow key={b.id} bill={b} showVendor={false} divider={i < list.length - 1} />)
        ) : (
          <AppText color={colors.textMuted} style={{ paddingVertical: 16 }}>
            No purchase invoices from {v.name} yet.
          </AppText>
        )}
      </Card>
      {bills.hasNextPage ? <Button label="Show more" kind="ghost" size="md" loading={bills.isFetchingNextPage} onPress={() => bills.fetchNextPage()} /> : null}

      {canDelete(role) && !v.purchase_bills_count ? (
        <Button label="Delete vendor" kind="danger" icon="trash-outline" size="md" loading={remove.isPending} onPress={confirmDelete} />
      ) : null}
    </Screen>
  );
}
