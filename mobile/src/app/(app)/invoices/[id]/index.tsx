import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Alert, StyleSheet, View } from 'react-native';

import { ApiError } from '@/api/client';
import { invoicesApi } from '@/api/endpoints';
import { documentsApi } from '@/api/workspace';
import { ActionSheet, SheetAction } from '@/components/action-sheet';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, IconTile, KeyValue, Loading, Row, SectionTitle } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon, IconName } from '@/components/icon';
import { BarAction, Screen } from '@/components/screen';
import { invoiceStatus, Pill } from '@/components/status-pill';
import { TextField } from '@/components/text-field';
import { useInvoice, useInvoiceAction } from '@/hooks/queries';
import { useDuplicateInvoice } from '@/hooks/workspace';
import { colors, fonts, shadow } from '@/theme';
import { date, money } from '@/utils/format';
import { thermalSlip } from '@/utils/print';
import { downloadAndShare, openWhatsApp } from '@/utils/share';

function Action({ icon, label, onPress, primary, busy }: { icon: IconName; label: string; onPress: () => void; primary?: boolean; busy?: boolean }) {
  return (
    <View style={{ flex: 1, alignItems: 'center', gap: 6 }}>
      <Button kind={primary ? 'accent' : 'soft'} icon={icon} accessibilityLabel={label} onPress={onPress} loading={busy} style={{ width: 52, height: 52, borderRadius: 16 }} />
      <AppText style={{ fontFamily: fonts.semibold, fontSize: 12 }}>{label}</AppText>
    </View>
  );
}

export default function InvoiceDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const invoiceId = Number(id);
  const q = useInvoice(invoiceId);
  const act = useInvoiceAction(invoiceId);
  const [cancelOpen, setCancelOpen] = useState(false);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState<string | null>(null);
  const [more, setMore] = useState(false);
  const dup = useDuplicateInvoice();
  const inv = q.data;

  if (q.isLoading) return <Screen hero nav="back" title="Invoice"><Loading /></Screen>;
  if (q.error || !inv) return <Screen hero nav="back" title="Invoice"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;

  const st = invoiceStatus(inv.status, inv.due_date, inv.effective_balance);
  const isDraft = inv.status === 'draft';
  const isCancelled = inv.status === 'cancelled';
  const canPay = !isDraft && !isCancelled && inv.effective_balance > 0;
  const paidPct = inv.grand_total > 0 ? Math.min(100, Math.round(((inv.grand_total - inv.effective_balance) / inv.grand_total) * 100)) : 0;

  const run = async (key: string, fn: () => Promise<unknown>) => {
    setBusy(key);
    try {
      await fn();
    } catch (e) {
      Alert.alert('Could not finish', (e as ApiError).message);
    } finally {
      setBusy(null);
    }
  };

  const share = () =>
    run('share', async () => {
      const link = await invoicesApi.shareLink(inv.id);
      await openWhatsApp(link.whatsapp_link ?? inv.whatsapp_link, `${inv.document_title} ${inv.display_number} for ${money(inv.grand_total)}: ${link.url}`);
    });
  const remind = () =>
    run('remind', async () => {
      const r = await invoicesApi.remind(inv.id, 'whatsapp');
      if (r.whatsapp_link) await openWhatsApp(r.whatsapp_link, r.message ?? '');
      else Alert.alert('Reminder', r.message ?? 'Reminder sent.');
    });

  const fileBase = inv.display_number.replace(/\W+/g, '-');
  const duplicate = () =>
    dup.mutate(inv.id, {
      // the copy is a new draft: open its form to check the date and details
      onSuccess: (copy) => router.push(`/invoices/new?id=${copy.id}`),
      onError: (e) => Alert.alert('Could not duplicate', (e as ApiError).message),
    });
  const slip = (mode: 'print' | 'pdf') => run(mode === 'print' ? 'print' : 'slip', () => thermalSlip(inv.id, `${fileBase}-thermal.pdf`, mode));
  const receipt = (p: { id: number; receipt_number: string }) =>
    run(`receipt-${p.id}`, () => downloadAndShare(documentsApi.receiptPath(p.id), `receipt-${p.receipt_number.replace(/\W+/g, '-')}.pdf`));

  const moreActions: SheetAction[] = [
    ...(isDraft
      ? [{ key: 'edit', label: 'Edit draft', icon: 'create-outline' as const, onPress: () => router.push(`/invoices/new?id=${inv.id}`) }]
      : inv.can.soft_edit
        ? [{ key: 'edit', label: 'Edit details', hint: 'Due date, notes, terms, transport', icon: 'create-outline' as const, onPress: () => router.push(`/invoices/${inv.id}/edit`) }]
        : []),
    { key: 'duplicate', label: 'Duplicate', hint: 'Copy into a new draft', icon: 'copy-outline', onPress: duplicate },
    ...(!isDraft && !isCancelled
      ? [{ key: 'credit', label: 'Credit note', hint: 'Return, discount or correction', icon: 'return-down-back-outline' as const, onPress: () => router.push(`/invoices/${inv.id}/credit-note`) }]
      : []),
    { key: 'print', label: 'Thermal print', hint: '80 mm receipt printer', icon: 'print-outline', onPress: () => slip('print') },
    { key: 'slip', label: 'Share thermal slip as PDF', icon: 'receipt-outline', onPress: () => slip('pdf') },
  ];

  return (
    <Screen
      hero
      nav="back"
      title={inv.display_number}
      subtitle={inv.document_title}
      right={<BarAction hero icon="ellipsis-horizontal" label="More actions" onPress={() => setMore(true)} />}
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      headerExtra={
        <View style={{ gap: 6, paddingBottom: 30 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <AppText color={colors.onDarkMuted} style={{ fontSize: 14 }} numberOfLines={1}>
              {inv.customer.name}
            </AppText>
            <Pill label={st.label} tone={st.tone} />
          </View>
          <AppText variant="hero" color={colors.onDark}>
            {isDraft || isCancelled ? money(inv.grand_total) : money(inv.effective_balance)}
            <AppText style={{ fontFamily: fonts.semibold, fontSize: 15 }} color={colors.onDarkMuted}>
              {isDraft ? '  draft' : isCancelled ? '  cancelled' : inv.effective_balance > 0 ? '  due' : '  paid'}
            </AppText>
          </AppText>
          {!isDraft && !isCancelled ? (
            <>
              <View style={styles.track} accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: 100, now: paidPct }}>
                <View style={[styles.fill, { width: `${paidPct}%` }]} />
              </View>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                <AppText variant="small" color={colors.onDarkMuted}>
                  {money(inv.grand_total - inv.effective_balance)} of {money(inv.grand_total)} received
                </AppText>
                {inv.due_date ? <AppText variant="small" color={colors.onDarkMuted}>Due {date(inv.due_date, true)}</AppText> : null}
              </View>
            </>
          ) : null}
        </View>
      }
    >
      <View style={[styles.actions, shadow.card]}>
        {isDraft ? (
          <>
            <Action icon="checkmark-done" label="Issue" primary busy={act.isPending} onPress={() => act.mutate({ type: 'finalize' }, { onSuccess: () => router.push(`/invoices/${inv.id}/issued`), onError: (e) => Alert.alert('Could not issue', (e as ApiError).message) })} />
            <Action icon="create-outline" label="Edit" onPress={() => router.push(`/invoices/new?id=${inv.id}`)} />
            <Action icon="document-outline" label="Preview" busy={busy === 'pdf'} onPress={() => run('pdf', () => downloadAndShare(invoicesApi.pdfPath(inv.id), `${inv.display_number.replace(/\W+/g, '-')}.pdf`))} />
            {inv.can.delete ? (
              <Action
                icon="trash-outline"
                label="Delete"
                onPress={() =>
                  Alert.alert('Delete this draft?', 'This cannot be undone.', [
                    { text: 'Keep', style: 'cancel' },
                    { text: 'Delete', style: 'destructive', onPress: () => act.mutate({ type: 'delete' }, { onSuccess: () => router.back() }) },
                  ])
                }
              />
            ) : null}
          </>
        ) : (
          <>
            {canPay ? <Action icon="wallet-outline" label="Payment" primary onPress={() => router.push(`/invoices/${inv.id}/payment`)} /> : null}
            {canPay ? <Action icon="notifications-outline" label="Remind" busy={busy === 'remind'} onPress={remind} /> : null}
            <Action icon="logo-whatsapp" label="Share" busy={busy === 'share'} onPress={share} />
            <Action icon="download-outline" label="PDF" busy={busy === 'pdf'} onPress={() => run('pdf', () => downloadAndShare(invoicesApi.pdfPath(inv.id), `${inv.display_number}.pdf`))} />
          </>
        )}
      </View>

      <Card>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <AppText variant="h3">{inv.items.length} item{inv.items.length === 1 ? '' : 's'}</AppText>
          <AppText variant="small" color={colors.textMuted}>
            {date(inv.invoice_date)} · {inv.customer.state?.name ?? ''}
          </AppText>
        </View>
        {inv.items.map((it) => (
          <View key={it.id} style={{ flexDirection: 'row', gap: 10 }}>
            <View style={{ flex: 1 }}>
              <AppText style={{ fontFamily: fonts.medium, fontSize: 14 }}>{it.description}</AppText>
              <AppText variant="small" color={colors.textMuted}>
                {it.quantity} {it.unit ?? ''} × {money(it.rate)} · GST {it.gst_rate}%
              </AppText>
            </View>
            <AppText style={{ fontFamily: fonts.medium, fontSize: 14 }}>{money(it.amount)}</AppText>
          </View>
        ))}
        <View style={styles.dash} />
        <KeyValue label="Taxable value" value={money(inv.subtotal, { paise: true })} />
        {inv.is_interstate ? (
          <KeyValue label="IGST" value={money(inv.total_igst, { paise: true })} />
        ) : (
          <>
            <KeyValue label="CGST" value={money(inv.total_cgst, { paise: true })} />
            <KeyValue label="SGST" value={money(inv.total_sgst, { paise: true })} />
          </>
        )}
        {inv.round_off ? <KeyValue label="Round off" value={money(inv.round_off, { paise: true })} /> : null}
        {inv.credited_amount > 0 ? <KeyValue label="Credit notes" value={`−${money(inv.credited_amount)}`} /> : null}
        <KeyValue label="Total" value={money(inv.grand_total)} strong />
      </Card>

      {inv.payments.length ? (
        <>
          <SectionTitle title="Payments" />
          <Card style={{ paddingVertical: 2 }}>
            {inv.payments.map((p, i) => (
              <Row
                key={p.id}
                divider={i < inv.payments.length - 1}
                leading={<IconTile icon="checkmark" bg={colors.greenTint} fg={colors.greenText} size={34} />}
                title={`${money(p.amount)} · ${p.method_label}`}
                subtitle={`${p.receipt_number} · ${date(p.received_at)}${p.reference_number ? ` · ${p.reference_number}` : ''}`}
                onPress={busy ? undefined : () => receipt(p)}
                accessibilityLabel={`${money(p.amount)} by ${p.method_label}, ${date(p.received_at)}. Share receipt ${p.receipt_number}`}
                trailing={
                  busy === `receipt-${p.id}` ? (
                    <AppText variant="small" color={colors.textMuted}>Opening…</AppText>
                  ) : (
                    <Icon name="share-outline" size={20} color={colors.tealDark} />
                  )
                }
              />
            ))}
          </Card>
        </>
      ) : null}

      {inv.notes || inv.terms ? (
        <Card>
          {inv.terms ? <AppText variant="small" color={colors.textBody}>Terms: {inv.terms}</AppText> : null}
          {inv.notes ? <AppText variant="small" color={colors.textBody}>Notes: {inv.notes}</AppText> : null}
        </Card>
      ) : null}

      {isCancelled && inv.cancellation_reason ? (
        <Card style={{ backgroundColor: colors.redTint }}>
          <AppText variant="small" color={colors.redText}>
            Cancelled: {inv.cancellation_reason}
          </AppText>
        </Card>
      ) : null}

      {!isDraft && !isCancelled && inv.can.cancel ? (
        cancelOpen ? (
          <Card>
            <TextField label="Why are you cancelling?" value={reason} onChangeText={setReason} placeholder="e.g. Raised twice by mistake" multiline error={act.error instanceof ApiError ? act.error.fieldErrors.cancellation_reason : undefined} />
            <Button label="Cancel invoice" kind="danger" loading={act.isPending} disabled={reason.trim().length < 5} onPress={() => act.mutate({ type: 'cancel', reason: reason.trim() }, { onSuccess: () => setCancelOpen(false) })} />
            <Button label="Keep invoice" kind="ghost" size="md" onPress={() => setCancelOpen(false)} />
          </Card>
        ) : (
          <Button label="Cancel invoice" kind="danger" icon="close-circle-outline" size="md" onPress={() => setCancelOpen(true)} />
        )
      ) : null}

      <ActionSheet visible={more} title={inv.display_number} subtitle={`${inv.customer.name} · ${money(inv.grand_total)}`} actions={moreActions} onClose={() => setMore(false)} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  track: { height: 8, borderRadius: 999, backgroundColor: 'rgba(255,255,255,0.18)', overflow: 'hidden', marginTop: 8 },
  fill: { height: '100%', borderRadius: 999, backgroundColor: colors.amber },
  actions: { flexDirection: 'row', backgroundColor: colors.surface, borderRadius: 22, paddingVertical: 14, marginTop: -44 },
  dash: { height: 1, borderTopWidth: 1, borderColor: '#D0D5DD', borderStyle: 'dashed' },
});
