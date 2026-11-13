import { Href, router } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';

import { ApiError } from '@/api/client';
import { CashMemo, MEMO_GST_RATES, MEMO_MODES } from '@/api/cash-memos';
import { AppText } from '@/components/app-text';
import { Card, Chips, KeyValue, Segmented } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { useSaveCashMemo } from '@/hooks/cash-memos';
import { useExpenseCategories } from '@/hooks/expenses';
import { MemoItemsEditor, MemoLine, memoLineAmount, newMemoLine } from '@/screens/cash-memos/memo-items-editor';
import { CategoryPicker } from '@/screens/expenses/category-picker';
import { DateField } from '@/screens/finance/date-field';
import { colors, fonts } from '@/theme';
import { money, today } from '@/utils/format';

const GST_OPTIONS = MEMO_GST_RATES.map((r) => ({
  value: String(r),
  label: r ? `GST ${r}%` : 'No GST',
}));

/**
 * Cash memo form (a purchase voucher you prepare yourself) — new, or edit with `initial`.
 * Numbering, GST split and the linked expense come from the server.
 */
export function MemoForm({ initial }: { initial?: CashMemo }) {
  const create = useSaveCashMemo();
  const cats = useExpenseCategories();
  const [lines, setLines] = useState<MemoLine[]>(() =>
    initial?.items.length
      ? initial.items.map((it) => ({
          ...newMemoLine(),
          description: it.description,
          hsn_sac: it.hsn_sac ?? '',
          quantity: String(Number(it.quantity)),
          unit: it.unit ?? '',
          rate: String(Number(it.rate)),
        }))
      : [newMemoLine()],
  );
  const [more, setMore] = useState(!!initial);
  const [f, setF] = useState({
    memo_date: initial?.memo_date ?? today(),
    seller_name: initial?.seller_name ?? '',
    seller_phone: initial?.seller_phone ?? '',
    seller_gstin: initial?.seller_gstin ?? '',
    seller_address: initial?.seller_address ?? '',
    seller_state: initial?.seller_state ?? '',
    discount: initial?.discount ? String(Number(initial.discount)) : '',
    gst_rate: String(Number(initial?.gst_rate ?? 0)),
    is_interstate: initial?.is_interstate ?? false,
    payment_mode: initial?.payment_mode ?? 'cash',
    reference_number: initial?.reference_number ?? '',
    expense_category: initial?.expense_category ?? 'misc',
    memo_number: initial?.memo_number ?? '',
    notes: initial?.notes ?? '',
  });
  const set =
    <K extends keyof typeof f>(k: K) =>
    (v: (typeof f)[K]) =>
      setF((s) => ({ ...s, [k]: v }));
  const errors = create.error instanceof ApiError ? create.error.fieldErrors : {};

  // Live preview only — the saved memo shows the server's figures.
  const subtotal = lines.reduce((s, l) => s + memoLineAmount(l), 0);
  const taxable = Math.max(0, subtotal - (Number(f.discount) || 0));
  const gst = (taxable * Number(f.gst_rate)) / 100;
  const total = Math.round(taxable + gst);

  const filled = lines.filter((l) => l.description.trim());
  const ready = f.seller_name.trim().length > 0 && filled.length > 0;

  const submit = () =>
    create.mutate(
      {
        id: initial?.id,
        input: {
          memo_date: f.memo_date,
          memo_number: f.memo_number.trim() || null,
          seller_name: f.seller_name.trim(),
          seller_phone: f.seller_phone.trim() || null,
          seller_gstin: f.seller_gstin.trim().toUpperCase() || null,
          seller_address: f.seller_address.trim() || null,
          seller_state: f.seller_state.trim() || null,
          discount: Number(f.discount) || 0,
          gst_rate: Number(f.gst_rate),
          is_interstate: Number(f.gst_rate) > 0 ? f.is_interstate : false,
          payment_mode: f.payment_mode,
          reference_number: f.reference_number.trim() || null,
          expense_category: f.expense_category,
          notes: f.notes.trim() || null,
          items: filled.map((l) => ({
            description: l.description.trim(),
            hsn_sac: l.hsn_sac || null,
            quantity: Number(l.quantity) || 0,
            unit: l.unit.trim() || null,
            rate: Number(l.rate) || 0,
          })),
        },
      },
      {
        onSuccess: (memo) => (initial ? router.back() : router.replace(`/cash-memos/${memo.id}` as Href)),
      },
    );

  return (
    <Screen
      nav="close"
      title={initial ? `Edit ${initial.memo_number}` : 'New cash memo'}
      subtitle={initial ? 'The linked expense is updated too' : 'For small buys without a proper bill · number given on save'}
      footer={
        <>
          <KeyValue label="Taxable value" value={money(taxable, { paise: true })} />
          {gst > 0 ? <KeyValue label={`GST ${f.gst_rate}%`} value={money(gst, { paise: true })} /> : null}
          <View style={{ height: 1, backgroundColor: colors.divider }} />
          <KeyValue label="Total" value={money(total)} strong />
          <Button label={initial ? 'Save changes' : 'Save cash memo'} icon="checkmark" loading={create.isPending} disabled={!ready} onPress={submit} />
        </>
      }
    >
      <DateField label="Memo date" value={f.memo_date} onChange={set('memo_date')} error={errors.memo_date} max={today()} />
      <TextField label="Bought from (seller / payee)" value={f.seller_name} onChangeText={set('seller_name')} placeholder="Shop or person's name" error={errors.seller_name} maxLength={160} />
      <TextField label="Seller phone (optional)" value={f.seller_phone} onChangeText={set('seller_phone')} keyboardType="phone-pad" error={errors.seller_phone} maxLength={30} />

      <MemoItemsEditor lines={lines} onChange={setLines} errors={errors} />

      <AppText variant="label" color={colors.textBody} style={{ marginTop: 4 }}>
        GST on this memo
      </AppText>
      <Chips value={f.gst_rate} options={GST_OPTIONS} onChange={set('gst_rate')} />
      {errors.gst_rate ? (
        <AppText variant="small" color={colors.redText}>
          {errors.gst_rate}
        </AppText>
      ) : null}
      {Number(f.gst_rate) > 0 ? (
        <Segmented
          value={f.is_interstate ? 'inter' : 'intra'}
          options={[
            { value: 'intra', label: 'CGST + SGST' },
            { value: 'inter', label: 'IGST (other state)' },
          ]}
          onChange={(v) => set('is_interstate')(v === 'inter')}
        />
      ) : null}

      <AppText variant="label" color={colors.textBody} style={{ marginTop: 4 }}>
        Paid by
      </AppText>
      <Chips value={f.payment_mode} options={MEMO_MODES} onChange={set('payment_mode')} />
      {errors.payment_mode ? (
        <AppText variant="small" color={colors.redText}>
          {errors.payment_mode}
        </AppText>
      ) : null}

      <CategoryPicker label="Expense category" value={f.expense_category} onChange={set('expense_category')} categories={cats.data ?? []} error={errors.expense_category} />

      <Pressable
        accessibilityRole="button"
        accessibilityState={{ expanded: more }}
        onPress={() => setMore((m) => !m)}
        style={{
          height: 52,
          borderRadius: 16,
          borderWidth: 1.5,
          borderStyle: 'dashed',
          borderColor: colors.tealLight,
          flexDirection: 'row',
          alignItems: 'center',
          paddingHorizontal: 16,
          gap: 8,
        }}
      >
        <AppText
          style={{
            flex: 1,
            fontFamily: fonts.semibold,
            fontSize: 14,
          }}
          color={colors.tealDark}
        >
          More: discount, seller GSTIN & address, reference, notes
        </AppText>
        <Icon name={more ? 'chevron-up' : 'chevron-down'} color={colors.tealDark} size={18} />
      </Pressable>
      {more ? (
        <Card>
          <TextField label="Discount (₹)" value={f.discount} onChangeText={(t) => set('discount')(t.replace(/[^\d.]/g, ''))} keyboardType="decimal-pad" error={errors.discount} />
          <TextField label="Seller GSTIN" value={f.seller_gstin} onChangeText={set('seller_gstin')} autoCapitalize="characters" error={errors.seller_gstin} maxLength={20} />
          <TextField label="Seller address" value={f.seller_address} onChangeText={set('seller_address')} multiline error={errors.seller_address} maxLength={500} />
          <TextField label="Seller state" value={f.seller_state} onChangeText={set('seller_state')} error={errors.seller_state} maxLength={80} />
          <TextField label="Reference (UPI / cheque no.)" value={f.reference_number} onChangeText={set('reference_number')} error={errors.reference_number} maxLength={60} />
          <TextField label="Memo number" value={f.memo_number} onChangeText={set('memo_number')} hint="Leave blank to use the next number." error={errors.memo_number} maxLength={40} />
          <TextField label="Notes" value={f.notes} onChangeText={set('notes')} multiline error={errors.notes} maxLength={1000} />
        </Card>
      ) : null}

      {create.error && !Object.keys(errors).length ? (
        <AppText variant="small" color={colors.redText}>
          {(create.error as Error).message}
        </AppText>
      ) : null}
    </Screen>
  );
}
