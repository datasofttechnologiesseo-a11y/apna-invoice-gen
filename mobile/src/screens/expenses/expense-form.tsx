import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, View } from 'react-native';

import { ApiError } from '@/api/client';
import { Expense, EXPENSE_METHODS, ExpenseInput } from '@/api/expenses';
import { AppText } from '@/components/app-text';
import { Card, Chips, KeyValue, Segmented } from '@/components/blocks';
import { Button } from '@/components/button';
import { BarAction, Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { useDeleteExpense, useExpenseCategories, useSaveExpense } from '@/hooks/expenses';
import { DateField } from '@/screens/finance/date-field';
import { colors } from '@/theme';
import { money, today } from '@/utils/format';

import { CategoryPicker } from './category-picker';

const num = (t: string) => t.replace(/[^\d.]/g, '');

/** New expense, or edit one (`initial`). GST slab check, books lock and ITC rules stay on the server. */
export function ExpenseForm({ initial, defaultCategory }: { initial?: Expense; defaultCategory?: string }) {
  const save = useSaveExpense();
  const remove = useDeleteExpense();
  const cats = useExpenseCategories();
  const [f, setF] = useState({
    entry_date: initial?.entry_date ?? today(),
    category: initial?.category ?? defaultCategory ?? 'misc',
    vendor_name: initial?.vendor_name ?? '',
    description: initial?.description ?? '',
    amount: initial ? String(initial.amount) : '',
    gst_amount: initial?.gst_amount ? String(initial.gst_amount) : '',
    is_interstate: initial?.is_interstate ?? false,
    payment_method: initial?.payment_method ?? 'cash',
    reference_number: initial?.reference_number ?? '',
    notes: initial?.notes ?? '',
  });
  const set = <K extends keyof typeof f>(k: K) => (v: (typeof f)[K]) => setF((s) => ({ ...s, [k]: v }));

  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};
  const gst = Number(f.gst_amount) || 0;
  const amount = Number(f.amount) || 0;
  const ready = f.description.trim().length > 0 && f.amount !== '' && !!f.category;

  const submit = () => {
    const input: ExpenseInput = {
      entry_date: f.entry_date,
      category: f.category,
      vendor_name: f.vendor_name.trim() || null,
      description: f.description.trim(),
      amount,
      gst_amount: gst,
      is_interstate: gst > 0 ? f.is_interstate : false,
      payment_method: f.payment_method,
      reference_number: f.reference_number.trim() || null,
      notes: f.notes.trim() || null,
    };
    save.mutate({ id: initial?.id, input }, { onSuccess: () => router.back() });
  };

  const confirmDelete = () =>
    initial &&
    Alert.alert('Delete this expense?', `${initial.description} · ${money(initial.total)}. This cannot be undone.`, [
      { text: 'Keep', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () =>
          remove.mutate(initial.id, {
            onSuccess: () => router.back(),
            onError: (e) => Alert.alert('Could not delete', (e as Error).message),
          }),
      },
    ]);

  return (
    <Screen
      nav="close"
      title={initial ? 'Edit expense' : 'New expense'}
      subtitle={initial ? `Expense #${initial.id}` : 'Rent, salary, fuel, bills…'}
      right={initial ? <BarAction icon="trash-outline" label="Delete expense" onPress={confirmDelete} /> : undefined}
      footer={
        <>
          <KeyValue label="Cash out (amount + GST)" value={money(amount + gst)} strong />
          <Button label={initial ? 'Save changes' : 'Save expense'} icon="checkmark" loading={save.isPending || remove.isPending} disabled={!ready} onPress={submit} />
        </>
      }
    >
      <DateField label="Date" value={f.entry_date} onChange={set('entry_date')} error={errors.entry_date} max={today()} />
      <CategoryPicker value={f.category} onChange={set('category')} error={errors.category} categories={cats.data ?? []} />
      <TextField label="What was it for?" value={f.description} onChangeText={set('description')} placeholder="Office rent for November" error={errors.description} maxLength={255} />
      <TextField label="Paid to (optional)" value={f.vendor_name} onChangeText={set('vendor_name')} placeholder="Landlord, shop or person" error={errors.vendor_name} maxLength={120} />

      <View style={{ flexDirection: 'row', gap: 10 }}>
        <View style={{ flex: 1 }}>
          <TextField label="Amount before GST" value={f.amount} onChangeText={(t) => set('amount')(num(t))} keyboardType="decimal-pad" placeholder="0" error={errors.amount} prefix={<AppText color={colors.textMuted}>₹</AppText>} />
        </View>
        <View style={{ flex: 1 }}>
          <TextField label="GST paid" value={f.gst_amount} onChangeText={(t) => set('gst_amount')(num(t))} keyboardType="decimal-pad" placeholder="0" error={errors.gst_amount} prefix={<AppText color={colors.textMuted}>₹</AppText>} />
        </View>
      </View>
      {gst > 0 ? (
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
      <Chips value={f.payment_method} options={EXPENSE_METHODS} onChange={set('payment_method')} />
      {errors.payment_method ? <AppText variant="small" color={colors.redText}>{errors.payment_method}</AppText> : null}

      <Card>
        <TextField label="Reference / bill no. (optional)" value={f.reference_number} onChangeText={set('reference_number')} error={errors.reference_number} maxLength={50} />
        <TextField label="Notes (optional)" value={f.notes} onChangeText={set('notes')} multiline error={errors.notes} maxLength={1000} />
      </Card>

      {save.error && !Object.keys(errors).length ? (
        <AppText variant="small" color={colors.redText}>
          {(save.error as Error).message}
        </AppText>
      ) : null}
    </Screen>
  );
}
