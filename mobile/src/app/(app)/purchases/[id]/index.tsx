import { router, useLocalSearchParams, type Href } from 'expo-router';
import { Alert, Pressable, StyleSheet, View } from 'react-native';

import { ApiError } from '@/api/client';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, IconTile, KeyValue, Loading, Row, SectionTitle } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon, IconName } from '@/components/icon';
import { Screen } from '@/components/screen';
import { Pill } from '@/components/status-pill';
import { useDeletePurchase, usePurchase, useRemovePurchasePayment } from '@/hooks/purchases';
import { canDelete, useRole } from '@/hooks/role';
import { Note, PurchasePill } from '@/screens/purchases/parts';
import { colors, fonts, shadow } from '@/theme';
import { date, money } from '@/utils/format';

function Action({ icon, label, onPress, primary, busy }: { icon: IconName; label: string; onPress: () => void; primary?: boolean; busy?: boolean }) {
  return (
    <View style={{ flex: 1, alignItems: 'center', gap: 6 }}>
      <Button kind={primary ? 'accent' : 'soft'} icon={icon} accessibilityLabel={label} onPress={onPress} loading={busy} style={{ width: 52, height: 52, borderRadius: 16 }} />
      <AppText style={{ fontFamily: fonts.semibold, fontSize: 12 }}>{label}</AppText>
    </View>
  );
}

/** One purchase invoice: balance, tax split, lines, payments and input-credit notes. */
export default function PurchaseDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const billId = Number(id);
  const q = usePurchase(billId);
  const remove = useDeletePurchase();
  const unpay = useRemovePurchasePayment(billId);
  const role = useRole();
  const b = q.data;

  if (q.isLoading) return <Screen hero nav="back" title="Purchase invoice"><Loading /></Screen>;
  if (q.error || !b) return <Screen hero nav="back" title="Purchase invoice"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;

  const open = b.balance > 0;
  const paidPct = b.grand_total > 0 ? Math.min(100, Math.round((b.paid_amount / b.grand_total) * 100)) : 0;
  const mayDelete = canDelete(role);

  const confirmDelete = () =>
    Alert.alert(`Delete purchase invoice ${b.bill_number}?`, 'Its payments, stock entries and expense are removed too. This cannot be undone.', [
      { text: 'Keep', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () =>
          remove.mutate(b.id, {
            onSuccess: () => router.back(),
            onError: (e) => Alert.alert('Could not delete', (e as ApiError).message),
          }),
      },
    ]);

  const confirmRemovePayment = (paymentId: number, label: string) =>
    Alert.alert('Remove this payment?', `${label}. The balance goes back up by this amount.`, [
      { text: 'Keep', style: 'cancel' },
      { text: 'Remove', style: 'destructive', onPress: () => unpay.mutate(paymentId, { onError: (e) => Alert.alert('Could not remove', (e as ApiError).message) }) },
    ]);

  return (
    <Screen
      hero
      nav="back"
      title={b.bill_number}
      subtitle="Purchase invoice"
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      headerExtra={
        <View style={{ gap: 6, paddingBottom: 30 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <AppText color={colors.onDarkMuted} style={{ fontSize: 14, flexShrink: 1 }} numberOfLines={1}>
              {b.vendor.name}
            </AppText>
            <PurchasePill bill={b} />
          </View>
          <AppText variant="hero" color={colors.onDark}>
            {money(open ? b.balance : b.grand_total)}
            <AppText style={{ fontFamily: fonts.semibold, fontSize: 15 }} color={colors.onDarkMuted}>
              {open ? '  to pay' : '  paid'}
            </AppText>
          </AppText>
          <View style={styles.track} accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: 100, now: paidPct }}>
            <View style={[styles.fill, { width: `${paidPct}%` }]} />
          </View>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
            <AppText variant="small" color={colors.onDarkMuted}>
              {money(b.paid_amount)} of {money(b.grand_total)} paid
            </AppText>
            {b.due_date && open ? <AppText variant="small" color={colors.onDarkMuted}>Due {date(b.due_date, true)}</AppText> : null}
          </View>
        </View>
      }
    >
      <View style={[styles.actions, shadow.card]}>
        {open ? <Action icon="wallet-outline" label="Pay" primary onPress={() => router.push(`/purchases/${b.id}/payment` as Href)} /> : null}
        <Action icon="create-outline" label="Edit" onPress={() => router.push(`/purchases/${b.id}/edit` as Href)} />
        <Action icon="storefront-outline" label="Vendor" onPress={() => router.push(`/vendors/${b.vendor_id}` as Href)} />
        {mayDelete ? <Action icon="trash-outline" label="Delete" busy={remove.isPending} onPress={confirmDelete} /> : null}
      </View>

      {b.credit_notes?.time_bar ? <Note tone="danger">{b.credit_notes.time_bar}</Note> : null}
      {b.credit_notes?.rule37 ? <Note tone="warn">{b.credit_notes.rule37}</Note> : null}
      {b.booked_on ? <Note>Invoice date {date(b.bill_date)} is in a locked period, so this bill is booked on {date(b.booked_on)}.</Note> : null}

      <Card>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
          <AppText variant="h3">
            {b.items.length} item{b.items.length === 1 ? '' : 's'}
          </AppText>
          <AppText variant="small" color={colors.textMuted}>
            {date(b.bill_date)} · {b.is_interstate ? 'Inter-state' : 'Within state'}
          </AppText>
        </View>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
          <Pill label={b.itc_eligible ? 'ITC claimable' : 'ITC blocked'} tone={b.itc_eligible ? 'paid' : 'draft'} />
          {b.reverse_charge ? <Pill label="Reverse charge" tone="due" /> : null}
          {b.expense_category_label ? <Pill label={b.expense_category_label} tone="info" /> : null}
        </View>
        {b.items.map((it) => (
          <View key={it.id} style={{ flexDirection: 'row', gap: 10 }}>
            <View style={{ flex: 1 }}>
              <AppText style={{ fontFamily: fonts.medium, fontSize: 14 }}>{it.description}</AppText>
              <AppText variant="small" color={colors.textMuted}>
                {it.quantity} {it.unit ?? ''} × {money(it.rate)} · GST {it.gst_rate}%{it.hsn_sac ? ` · HSN ${it.hsn_sac}` : ''}
                {it.discount ? ` · less ${money(it.discount)}` : ''}
              </AppText>
            </View>
            <AppText style={{ fontFamily: fonts.medium, fontSize: 14 }}>{money(it.amount)}</AppText>
          </View>
        ))}
        <View style={styles.dash} />
        <KeyValue label="Taxable value" value={money(b.subtotal, { paise: true })} />
        {b.is_interstate ? (
          <KeyValue label="IGST" value={money(b.total_igst, { paise: true })} />
        ) : (
          <>
            <KeyValue label="CGST" value={money(b.total_cgst, { paise: true })} />
            <KeyValue label={b.state_tax_label || 'SGST'} value={money(b.total_sgst, { paise: true })} />
          </>
        )}
        {b.round_off ? <KeyValue label="Round off" value={money(b.round_off, { paise: true })} /> : null}
        <KeyValue label="Total" value={money(b.grand_total)} strong />
        {b.reverse_charge ? (
          <AppText variant="small" color={colors.textMuted}>
            Reverse charge: you pay this GST in GSTR-3B, not the vendor.
          </AppText>
        ) : null}
      </Card>

      <SectionTitle title="Payments" action={open ? 'Record payment' : undefined} onAction={() => router.push(`/purchases/${b.id}/payment` as Href)} />
      <Card style={{ paddingVertical: 2, gap: 0 }}>
        {b.payments.length ? (
          b.payments.map((p, i) => {
            const label = `${money(p.amount)} · ${p.method_label}`;
            return (
              <Row
                key={p.id}
                divider={i < b.payments.length - 1}
                leading={<IconTile icon="arrow-up" bg={colors.greenTint} fg={colors.greenText} size={34} />}
                title={label}
                subtitle={`${date(p.paid_on)}${p.reference_number ? ` · ${p.reference_number}` : ''}${p.notes ? ` · ${p.notes}` : ''}`}
                trailing={
                  mayDelete ? (
                    <Pressable accessibilityRole="button" accessibilityLabel={`Remove payment ${label}`} hitSlop={10} onPress={() => confirmRemovePayment(p.id, label)}>
                      <Icon name="trash-outline" size={18} color={colors.redText} />
                    </Pressable>
                  ) : undefined
                }
              />
            );
          })
        ) : (
          <AppText color={colors.textMuted} style={{ paddingVertical: 16 }}>
            Nothing paid yet.
          </AppText>
        )}
      </Card>

      {b.reference_number || b.notes ? (
        <Card style={{ gap: 6 }}>
          {b.reference_number ? <AppText variant="small" color={colors.textBody}>Reference: {b.reference_number}</AppText> : null}
          {b.notes ? <AppText variant="small" color={colors.textBody}>Notes: {b.notes}</AppText> : null}
        </Card>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  track: { height: 8, borderRadius: 999, backgroundColor: 'rgba(255,255,255,0.18)', overflow: 'hidden', marginTop: 8 },
  fill: { height: '100%', borderRadius: 999, backgroundColor: colors.amber },
  actions: { flexDirection: 'row', backgroundColor: colors.surface, borderRadius: 22, paddingVertical: 14, marginTop: -44 },
  dash: { height: 1, borderTopWidth: 1, borderColor: '#D0D5DD', borderStyle: 'dashed' },
});
