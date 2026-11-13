import { Href, router, useLocalSearchParams } from 'expo-router';
import { Alert, View } from 'react-native';

import { ApiError } from '@/api/client';
import type { ItemDetail } from '@/api/items';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, IconTile, Loading } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { Pill } from '@/components/status-pill';
import { useDeleteItem, useItem, useRestoreItem } from '@/hooks/items';
import { canDelete, useRole } from '@/hooks/role';
import { ItemForm } from '@/screens/items/item-form';
import { colors, fonts } from '@/theme';

/** Edit an item, see its stock, delete (or archive) / restore it. */
export default function ItemScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useItem(Number(id));
  const remove = useDeleteItem();
  const restore = useRestoreItem();
  const role = useRole();
  const item = q.data;

  if (q.isLoading) return <Screen nav="close" title="Item"><Loading /></Screen>;
  if (q.error || !item) return <Screen nav="close" title="Item"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;

  const fail = (e: unknown) => Alert.alert('Could not finish', (e as ApiError).message);

  const confirmDelete = () =>
    Alert.alert(`Delete ${item.name}?`, 'If it is on an invoice, quote or bill it is archived instead, so your records stay intact.', [
      { text: 'Keep', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () =>
          remove.mutate(item.id, {
            onSuccess: (r) => {
              if (r.archived) Alert.alert('Archived', r.message);
              router.back();
            },
            onError: fail,
          }),
      },
    ]);

  return (
    <ItemForm
      key={item.id}
      initial={item}
      title={item.name}
      subtitle={[item.kind === 'service' ? 'Service' : 'Goods', item.hsn_sac ? `HSN/SAC ${item.hsn_sac}` : null].filter(Boolean).join(' · ')}
      onSaved={() => router.back()}
      top={
        <>
          {!item.is_active ? (
            <Card style={{ backgroundColor: colors.amberTint }}>
              <AppText variant="small" color={colors.amberText}>
                This item is archived. It does not appear on new invoices until you restore it.
              </AppText>
              <Button label="Restore item" icon="refresh" kind="accent" size="md" loading={restore.isPending} onPress={() => restore.mutate(item, { onError: fail })} />
            </Card>
          ) : null}
          <StockCard item={item} />
        </>
      }
      bottom={
        item.is_active && canDelete(role) ? (
          <Button label="Delete item" icon="trash-outline" kind="danger" size="md" loading={remove.isPending} onPress={confirmDelete} />
        ) : null
      }
    />
  );
}

function StockCard({ item }: { item: ItemDetail }) {
  if (item.kind === 'service') return null;
  if (!item.track_stock || item.stock === null) {
    return (
      <Card style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
        <IconTile icon="cube-outline" bg={colors.slateTint} fg={colors.slateText} />
        <AppText variant="small" color={colors.textMuted} style={{ flex: 1 }}>
          Stock is not tracked for this item. Turn on stock tracking on the web to see stock in hand here.
        </AppText>
      </Card>
    );
  }
  const low = item.low_stock_level !== null && item.stock <= item.low_stock_level;
  const out = item.stock <= 0;
  return (
    <Card>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
        <IconTile icon="cube" bg={out ? colors.redTint : low ? colors.amberTint : colors.greenTint} fg={out ? colors.redText : low ? colors.amberText : colors.greenText} />
        <View style={{ flex: 1 }}>
          <AppText variant="small" color={colors.textMuted}>In stock</AppText>
          <AppText style={{ fontFamily: fonts.bold, fontSize: 22 }}>
            {item.stock} {item.unit}
          </AppText>
          {item.low_stock_level !== null ? (
            <AppText variant="small" color={colors.textMuted}>Alert below {item.low_stock_level} {item.unit}</AppText>
          ) : null}
        </View>
        {out ? <Pill label="Out of stock" tone="late" /> : low ? <Pill label="Low stock" tone="due" /> : null}
      </View>
      <Button label="Stock history & adjust" icon="swap-vertical" kind="soft" size="md" onPress={() => router.push(`/items/${item.id}/stock` as Href)} />
    </Card>
  );
}
