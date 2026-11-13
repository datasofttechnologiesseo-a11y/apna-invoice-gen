import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Alert, StyleSheet, TextInput, View } from 'react-native';

import { ApiError } from '@/api/client';
import { CreditNote, CreditNoteInput, creditNotesApi, CreditNotesResponse } from '@/api/credit-notes';
import type { Invoice } from '@/api/types';
import { AppText } from '@/components/app-text';
import { Card, Chips, ErrorState, IconTile, KeyValue, Loading, Segmented, SectionTitle } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { useCreateCreditNote, useCreditNotes, useDeleteCreditNote } from '@/hooks/credit-notes';
import { useInvoice } from '@/hooks/queries';
import { DateField } from '@/screens/finance/date-field';
import { fileSafe } from '@/screens/finance/periods';
import { colors, fonts, radius } from '@/theme';
import { date, money, today } from '@/utils/format';
import { downloadAndShare } from '@/utils/share';

/**
 * Raise a credit note (CGST s.34) against an issued invoice, and list/reverse
 * the ones already made. Either pick returned quantities per line (sales
 * return — stock comes back) or type an amount (price reduction). The server
 * splits the tax, numbers the note and enforces the s.34(2) deadline.
 */
export default function CreditNoteScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const invoiceId = Number(id);
  const inv = useInvoice(invoiceId);
  const notes = useCreditNotes(invoiceId);

  if (inv.isLoading || notes.isLoading) return <Screen nav="close" title="Credit note"><Loading /></Screen>;
  const error = inv.error ?? notes.error;
  if (error || !inv.data || !notes.data)
    return (
      <Screen nav="close" title="Credit note">
        <ErrorState message={(error as Error)?.message ?? 'Not found'} onRetry={() => (inv.error ? inv.refetch() : notes.refetch())} />
      </Screen>
    );
  return <CreditNoteBody invoice={inv.data} res={notes.data} refreshing={notes.isRefetching} onRefresh={() => notes.refetch()} />;
}

function CreditNoteBody({ invoice, res, refreshing, onRefresh }: { invoice: Invoice; res: CreditNotesResponse; refreshing: boolean; onRefresh: () => void }) {
  const create = useCreateCreditNote(invoice.id);
  const { meta } = res;
  const [mode, setMode] = useState<'lines' | 'amount'>('lines');
  const [reason, setReason] = useState(meta.reasons[0]?.value ?? 'other');
  const [noteDate, setNoteDate] = useState(today());
  const [amount, setAmount] = useState('');
  const [qty, setQty] = useState<Record<number, string>>({});
  const [remarks, setRemarks] = useState('');
  const errors = create.error instanceof ApiError ? create.error.fieldErrors : {};

  const picked = Object.fromEntries(Object.entries(qty).filter(([, v]) => Number(v) > 0).map(([k, v]) => [k, Number(v)]));
  // Preview only (line total incl. GST, pro rata); the note is built on the server.
  const linesPreview = invoice.items.reduce((s, it) => (it.id && picked[it.id] ? s + ((it.total ?? 0) * picked[it.id]) / (Number(it.quantity) || 1) : s), 0);
  const ready = mode === 'lines' ? Object.keys(picked).length > 0 : Number(amount) > 0;

  const submit = () => {
    const input: CreditNoteInput = { credit_note_date: noteDate, reason, notes: remarks.trim() || null };
    if (mode === 'lines') input.lines = picked;
    else input.amount = Number(amount);
    create.mutate(input, {
      onSuccess: (cn) => {
        setAmount('');
        setQty({});
        setRemarks('');
        Alert.alert('Credit note issued', `${cn.credit_note_number} for ${money(cn.amount)}. The invoice balance is updated.`);
      },
    });
  };

  const blocked = !meta.can_create;
  const reasonHint = meta.reasons.find((r) => r.value === reason)?.hint;

  return (
    <Screen
      nav="close"
      title="Credit note"
      subtitle={`Against ${invoice.display_number} · ${invoice.customer.name}`}
      onRefresh={onRefresh}
      refreshing={refreshing}
      footer={
        blocked ? undefined : (
          <>
            <KeyValue label="Can still be credited" value={money(meta.creditable)} />
            {mode === 'lines' && linesPreview > 0 ? <KeyValue label="These lines (about)" value={money(linesPreview)} strong /> : null}
            <Button label="Issue credit note" icon="return-down-back" loading={create.isPending} disabled={!ready} onPress={submit} />
          </>
        )
      }
    >
      <Card>
        <KeyValue label="Invoice total" value={money(invoice.grand_total)} />
        <KeyValue label="Already credited" value={money(invoice.credited_amount)} />
        <KeyValue label="Can still be credited" value={money(meta.creditable)} strong />
        {meta.deadline ? (
          <AppText variant="small" color={meta.window_closed ? colors.redText : colors.textMuted}>
            {meta.window_closed ? `The GST deadline for credit notes on this invoice was ${date(meta.deadline)}.` : `Credit notes reduce GST if issued by ${date(meta.deadline)} (s.34(2)).`}
          </AppText>
        ) : null}
      </Card>

      {blocked ? (
        <Card style={{ flexDirection: 'row', alignItems: 'center' }}>
          <IconTile icon="information-circle-outline" bg={colors.amberTint} fg={colors.amberText} />
          <AppText variant="small" color={colors.textBody} style={{ flex: 1 }}>
            {invoice.status === 'draft'
              ? 'Issue the invoice before creating a credit note.'
              : invoice.status === 'cancelled'
                ? 'Cancelled invoices cannot be credited.'
                : meta.window_closed
                  ? 'The Section 34(2) window is closed, so a new credit note cannot reduce GST.'
                  : 'This invoice has been credited in full.'}
          </AppText>
        </Card>
      ) : (
        <>
          <SectionTitle title="New credit note" />
          <Segmented
            value={mode}
            options={[
              { value: 'lines', label: 'Goods returned' },
              { value: 'amount', label: 'Reduce amount' },
            ]}
            onChange={setMode}
          />
          {mode === 'lines' ? (
            <Card>
              <AppText variant="small" color={colors.textMuted}>
                Enter how many came back on each line. The note is the value of these lines and the goods go back in stock.
              </AppText>
              {invoice.items.map((it) => {
                const key = it.id ?? 0;
                const err = errors[`lines.${key}`];
                return (
                  <View key={key} style={{ gap: 4 }}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                      <View style={{ flex: 1 }}>
                        <AppText style={{ fontFamily: fonts.medium, fontSize: 14 }} numberOfLines={2}>
                          {it.description}
                        </AppText>
                        <AppText variant="small" color={colors.textMuted}>
                          Billed {Number(it.quantity)} {it.unit ?? ''} · {money(it.total ?? 0)}
                        </AppText>
                      </View>
                      <TextInput
                        value={qty[key] ?? ''}
                        onChangeText={(t) => setQty((s) => ({ ...s, [key]: t.replace(/[^\d.]/g, '') }))}
                        keyboardType="decimal-pad"
                        placeholder="0"
                        placeholderTextColor={colors.textMuted}
                        accessibilityLabel={`Quantity returned of ${it.description}`}
                        style={[styles.qty, err ? { borderColor: colors.red } : null]}
                        selectTextOnFocus
                        maxLength={10}
                      />
                    </View>
                    {err ? <AppText variant="small" color={colors.redText}>{err}</AppText> : null}
                  </View>
                );
              })}
              {errors.lines ? <AppText variant="small" color={colors.redText}>{errors.lines}</AppText> : null}
            </Card>
          ) : (
            <TextField
              label="Amount to credit (incl. GST)"
              value={amount}
              onChangeText={(t) => setAmount(t.replace(/[^\d.]/g, ''))}
              keyboardType="decimal-pad"
              placeholder="0"
              hint={`Up to ${money(meta.creditable)}. Tax is split in the invoice's proportion.`}
              error={errors.amount}
              prefix={<AppText color={colors.textMuted}>₹</AppText>}
            />
          )}
          {mode === 'lines' && errors.amount ? <AppText variant="small" color={colors.redText}>{errors.amount}</AppText> : null}

          <AppText variant="label" color={colors.textBody} style={{ marginTop: 4 }}>Reason</AppText>
          <Chips value={reason} options={meta.reasons.map((r) => ({ value: r.value, label: r.label }))} onChange={setReason} />
          {errors.reason ? <AppText variant="small" color={colors.redText}>{errors.reason}</AppText> : reasonHint ? <AppText variant="small" color={colors.textMuted}>{reasonHint}</AppText> : null}

          <DateField label="Credit note date" value={noteDate} onChange={setNoteDate} min={invoice.invoice_date} max={today()} error={errors.credit_note_date} />
          <TextField label="Notes (printed on the note)" value={remarks} onChangeText={setRemarks} multiline maxLength={1000} error={errors.notes} placeholder="One fan returned, damaged in transit." />

          {create.error && !Object.keys(errors).length ? (
            <AppText variant="small" color={colors.redText}>{(create.error as Error).message}</AppText>
          ) : null}
        </>
      )}

      <SectionTitle title={res.data.length ? `Credit notes (${res.data.length})` : 'No credit notes yet'} />
      {res.data.map((cn) => (
        <NoteCard key={cn.id} cn={cn} invoiceId={invoice.id} />
      ))}
    </Screen>
  );
}

function NoteCard({ cn, invoiceId }: { cn: CreditNote; invoiceId: number }) {
  const remove = useDeleteCreditNote(invoiceId);
  const [sharing, setSharing] = useState(false);
  const tax = cn.total_cgst + cn.total_sgst + cn.total_igst;
  const share = async () => {
    setSharing(true);
    await downloadAndShare(creditNotesApi.pdfPath(cn.id), `credit-note-${fileSafe(cn.credit_note_number)}.pdf`);
    setSharing(false);
  };
  const reverse = () =>
    Alert.alert('Reverse this credit note?', `${cn.credit_note_number} (${money(cn.amount)}) will be deleted and the invoice balance restored. Returned goods go out of stock again.`, [
      { text: 'Keep', style: 'cancel' },
      { text: 'Reverse', style: 'destructive', onPress: () => remove.mutate(cn.id, { onError: (e) => Alert.alert('Could not reverse', (e as Error).message) }) },
    ]);
  return (
    <Card>
      <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 8 }}>
        <AppText variant="h3" style={{ flex: 1 }}>{cn.credit_note_number}</AppText>
        <AppText variant="money">{money(cn.amount)}</AppText>
      </View>
      <AppText variant="small" color={colors.textMuted}>
        {date(cn.credit_note_date)} · {cn.reason_label} · taxable {money(cn.taxable_value, { paise: true })} + GST {money(tax, { paise: true })}
      </AppText>
      {cn.notes ? <AppText variant="small" color={colors.textBody}>{cn.notes}</AppText> : null}
      <View style={{ flexDirection: 'row', gap: 10 }}>
        <Button label="PDF" icon="share-outline" kind="soft" size="sm" style={{ flex: 1 }} loading={sharing} onPress={share} />
        <Button label="Reverse" icon="trash-outline" kind="danger" size="sm" style={{ flex: 1 }} loading={remove.isPending} onPress={reverse} />
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  qty: { width: 76, height: 44, borderRadius: radius.sm + 2, borderWidth: 1.5, borderColor: colors.borderStrong, textAlign: 'center', fontFamily: fonts.bold, fontSize: 15, color: colors.text },
});
