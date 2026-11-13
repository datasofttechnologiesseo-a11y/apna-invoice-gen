import { router, useLocalSearchParams, type Href } from 'expo-router';
import { useState } from 'react';
import { Modal, Pressable, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-controller';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ApiError } from '@/api/client';
import type { StockDirection, StockMovement } from '@/api/purchases';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, IconTile, Loading, Row, SectionTitle, Segmented } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { useAdjustStock, useStock } from '@/hooks/purchases';
import { DateField, Note } from '@/screens/purchases/parts';
import { colors, fonts, shadow } from '@/theme';
import { date, today } from '@/utils/format';

/** Same quick reasons as the web stock page. */
const REASONS: Record<StockDirection, string[]> = {
  in: ['Stock received', 'Customer returned goods', 'Opening stock correction'],
  out: ['Damaged / expired', 'Returned to supplier', 'Free sample given', 'Used in-house'],
  count: ['Physical count', 'Month-end count', 'Year-end count'],
};

const qty = (n: number) => String(0 + Math.round(n * 1000) / 1000);

/** Stock of one item: on hand, every movement with its running balance, and manual adjustments. */
export default function ItemStock() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productId = Number(id);
  const q = useStock(productId);
  const [adjusting, setAdjusting] = useState(false);
  const [done, setDone] = useState<string | null>(null);
  const s = q.data;

  if (q.isLoading) return <Screen hero nav="back" title="Stock"><Loading /></Screen>;
  if (q.error || !s) return <Screen hero nav="back" title="Stock"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;

  const p = s.product;
  const low = p.low_stock_level !== null && s.on_hand <= Number(p.low_stock_level);

  return (
    <Screen
      hero
      nav="back"
      title={p.name}
      subtitle={[p.sku, p.hsn_sac ? `HSN ${p.hsn_sac}` : null, p.unit].filter(Boolean).join(' · ')}
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      headerExtra={<View style={{ height: 30 }} />}
      footer={p.track_stock ? <Button label="Add, remove or count stock" icon="swap-vertical" onPress={() => setAdjusting(true)} /> : undefined}
    >
      <View style={[{ backgroundColor: colors.surface, borderRadius: 22, padding: 16, marginTop: -44, gap: 4 }, shadow.card]}>
        <AppText variant="small" color={colors.textMuted}>In stock now</AppText>
        <AppText variant="hero" style={{ fontSize: 30 }} color={s.on_hand < 0 ? colors.redText : low ? colors.amberText : colors.text}>
          {qty(s.on_hand)} <AppText style={{ fontFamily: fonts.semibold, fontSize: 16 }} color={colors.textMuted}>{p.unit}</AppText>
        </AppText>
        <AppText variant="small" color={colors.textMuted}>
          Opening stock {qty(s.opening_stock)} {p.unit}
          {p.low_stock_level !== null ? ` · low-stock alert at ${qty(Number(p.low_stock_level))}` : ''}
        </AppText>
      </View>

      {done ? <Note>{done}</Note> : null}
      {!p.track_stock ? <Note tone="warn">Stock is not tracked for this item. Turn on stock tracking on the item to record movements.</Note> : null}
      {s.on_hand < 0 ? <Note tone="danger">More sold than recorded. Enter the purchase or adjust stock.</Note> : low ? <Note tone="warn">Stock is at or below the low-stock level.</Note> : null}

      <SectionTitle title="Movements" action={p.track_stock ? 'Purchase invoice' : undefined} onAction={() => router.push('/purchases/new' as Href)} />
      <Card style={{ paddingVertical: 2, gap: 0 }}>
        {s.movements.length ? (
          s.movements.map((m, i) => <MovementRow key={m.id} m={m} unit={p.unit} divider={i < s.movements.length - 1} />)
        ) : (
          <AppText color={colors.textMuted} style={{ paddingVertical: 16 }}>
            No movements yet. Issued invoices and purchase invoices for this item are listed here.
          </AppText>
        )}
      </Card>

      <AdjustSheet
        visible={adjusting}
        productId={productId}
        unit={p.unit}
        onClose={() => setAdjusting(false)}
        onDone={(msg) => {
          setDone(msg);
          setAdjusting(false);
        }}
      />
    </Screen>
  );
}

function MovementRow({ m, unit, divider }: { m: StockMovement; unit: string; divider: boolean }) {
  const inward = m.quantity >= 0;
  const href = m.source?.type === 'invoice' ? `/invoices/${m.source.id}` : m.source?.type === 'purchase_bill' ? `/purchases/${m.source.id}` : null;
  return (
    <Row
      divider={divider}
      onPress={href ? () => router.push(href as Href) : undefined}
      leading={<IconTile icon={inward ? 'arrow-down' : 'arrow-up'} bg={inward ? colors.greenTint : colors.redTint} fg={inward ? colors.greenText : colors.redText} size={34} />}
      title={`${m.kind_label}${m.source?.number ? ` ${m.source.number}` : ''}`}
      subtitle={[date(m.moved_on), m.note].filter(Boolean).join(' · ')}
      accessibilityLabel={`${m.kind_label} ${m.source?.number ?? ''}, ${inward ? 'in' : 'out'} ${qty(Math.abs(m.quantity))} ${unit}, ${date(m.moved_on)}${m.balance !== null ? `, balance ${qty(m.balance)}` : ''}`}
      trailing={
        <View style={{ alignItems: 'flex-end' }}>
          <AppText variant="money" color={inward ? colors.greenText : colors.redText}>
            {inward ? '+' : '−'}
            {qty(Math.abs(m.quantity))}
          </AppText>
          {m.balance !== null ? (
            <AppText variant="small" color={colors.textMuted}>
              bal {qty(m.balance)}
            </AppText>
          ) : null}
        </View>
      }
    />
  );
}

function AdjustSheet({ visible, productId, unit, onClose, onDone }: { visible: boolean; productId: number; unit: string; onClose: () => void; onDone: (message: string) => void }) {
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.ground }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 16, paddingTop: 20 }}>
          <AppText variant="h2" style={{ flex: 1 }} accessibilityRole="header">
            Adjust stock
          </AppText>
          <Button kind="outline" size="md" icon="close" accessibilityLabel="Close" onPress={onClose} />
        </View>
        <KeyboardAwareScrollView bottomOffset={24} keyboardShouldPersistTaps="handled" contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}>
          {/* Remounts on open, so every adjustment starts clean. */}
          {visible ? <AdjustBody productId={productId} unit={unit} onDone={onDone} /> : null}
        </KeyboardAwareScrollView>
      </View>
    </Modal>
  );
}

function AdjustBody({ productId, unit, onDone }: { productId: number; unit: string; onDone: (message: string) => void }) {
  const save = useAdjustStock(productId);
  const [direction, setDirection] = useState<StockDirection>('in');
  const [quantity, setQuantity] = useState('');
  const [movedOn, setMovedOn] = useState(today());
  const [reason, setReason] = useState('');
  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};
  const value = Number(quantity);
  const valid = quantity.trim() !== '' && Number.isFinite(value) && (direction === 'count' ? value >= 0 : value > 0) && !!reason.trim();

  return (
    <View style={{ paddingHorizontal: 16, gap: 14 }}>
      <Segmented
        value={direction}
        onChange={(d) => {
          setDirection(d);
          if (Object.values(REASONS).flat().includes(reason)) setReason('');
        }}
        options={[
          { value: 'in', label: 'Add' },
          { value: 'out', label: 'Remove' },
          { value: 'count', label: 'Count' },
        ]}
      />
      <TextField
        label={direction === 'count' ? `Counted on the shelf (${unit})` : `Quantity (${unit})`}
        value={quantity}
        onChangeText={(t) => setQuantity(t.replace(/[^\d.]/g, ''))}
        keyboardType="decimal-pad"
        placeholder="0"
        error={errors.quantity}
        hint={direction === 'count' ? 'Books are set to this number; the difference is posted as one adjustment.' : undefined}
      />
      <DateField label="Date" value={movedOn} onChange={setMovedOn} error={errors.moved_on} />
      <TextField label="Reason" value={reason} onChangeText={setReason} maxLength={200} error={errors.reason} />
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
        {REASONS[direction].map((r) => {
          const on = r === reason;
          return (
            <Pressable
              key={r}
              accessibilityRole="button"
              accessibilityState={{ selected: on }}
              onPress={() => setReason(r)}
              style={{ minHeight: 40, paddingHorizontal: 12, borderRadius: 999, justifyContent: 'center', backgroundColor: on ? colors.tealSoft : colors.surface, borderWidth: 1, borderColor: on ? colors.tealLight : colors.border }}
            >
              <AppText style={{ fontFamily: fonts.medium, fontSize: 13 }} color={on ? colors.tealDark : colors.textBody}>
                {r}
              </AppText>
            </Pressable>
          );
        })}
      </View>
      <Button
        label={direction === 'in' ? 'Add to stock' : direction === 'out' ? 'Remove from stock' : 'Save count'}
        loading={save.isPending}
        disabled={!valid}
        onPress={() => save.mutate({ direction, quantity: value, moved_on: movedOn, reason: reason.trim() }, { onSuccess: (r) => onDone(r.message) })}
      />
      {save.error && !Object.keys(errors).length ? <Note tone="danger">{(save.error as Error).message}</Note> : null}
    </View>
  );
}
