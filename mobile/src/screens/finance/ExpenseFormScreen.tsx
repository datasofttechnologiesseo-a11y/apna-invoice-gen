import React, { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { createExpense, deleteExpense, getExpenses, updateExpense } from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Button, TextField } from '../../components/ui';
import { colors } from '../../theme';
import type { FinanceStackParamList } from '../../navigation/types';
import type { ExpensesResponse } from '../../api/types';

type Props = NativeStackScreenProps<FinanceStackParamList, 'ExpenseForm'>;

const PAYMENT_METHODS = ['cash', 'bank', 'upi', 'card', 'cheque', 'other'];

export default function ExpenseFormScreen({ route, navigation }: Props) {
  const id = route.params?.id;
  const qc = useQueryClient();

  // Categories + (for edit) the existing row come from the cached list query.
  const { data: list } = useQuery({ queryKey: ['expenses'], queryFn: () => getExpenses() });
  const categories = list?.meta.categories ?? [];
  const cached = qc.getQueryData<ExpensesResponse>(['expenses']);
  const existing = id ? cached?.data.find((e) => e.id === id) : undefined;

  const [date, setDate] = useState(existing?.entry_date ?? new Date().toISOString().slice(0, 10));
  const [category, setCategory] = useState<string>(existing?.category ?? 'misc');
  const [vendor, setVendor] = useState(existing?.vendor_name ?? '');
  const [description, setDescription] = useState(existing?.description ?? '');
  const [amount, setAmount] = useState(existing ? String(existing.amount) : '');
  const [gst, setGst] = useState(existing ? String(existing.gst_amount) : '');
  const [method, setMethod] = useState(existing?.payment_method ?? 'bank');
  const [notes, setNotes] = useState(existing?.notes ?? '');

  function invalidate() {
    qc.invalidateQueries({ queryKey: ['expenses'] });
    qc.invalidateQueries({ queryKey: ['dashboard'] });
  }

  const saveMut = useMutation({
    mutationFn: () => {
      const payload = {
        entry_date: date,
        category,
        vendor_name: vendor || undefined,
        description: description.trim(),
        amount: parseFloat(amount) || 0,
        gst_amount: parseFloat(gst) || 0,
        payment_method: method,
        notes: notes || undefined,
      };
      return existing ? updateExpense(existing.id, payload) : createExpense(payload);
    },
    onSuccess: () => {
      invalidate();
      navigation.goBack();
    },
    onError: (e) => Alert.alert('Could not save', apiErrorMessage(e)),
  });

  const deleteMut = useMutation({
    mutationFn: () => deleteExpense(existing!.id),
    onSuccess: () => {
      invalidate();
      navigation.goBack();
    },
    onError: (e) => Alert.alert('Cannot delete', apiErrorMessage(e)),
  });

  function save() {
    if (!description.trim()) return Alert.alert('Description is required');
    if (!(parseFloat(amount) >= 0)) return Alert.alert('Enter a valid amount');
    saveMut.mutate();
  }

  const lockedByMemo = !!existing?.cash_memo_id;

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.bg }} contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
      {lockedByMemo ? (
        <Text style={styles.lockNote}>This expense came from a cash memo and is edited there.</Text>
      ) : null}

      <TextField label="Date *" value={date} onChangeText={setDate} placeholder="YYYY-MM-DD" autoCapitalize="none" editable={!lockedByMemo} />
      <TextField label="Description *" value={description} onChangeText={setDescription} editable={!lockedByMemo} />
      <TextField label="Vendor" value={vendor} onChangeText={setVendor} placeholder="Optional" editable={!lockedByMemo} />

      <View style={styles.inline}>
        <View style={{ flex: 1 }}>
          <TextField label="Amount (pre-GST) *" value={amount} onChangeText={setAmount} keyboardType="decimal-pad" editable={!lockedByMemo} />
        </View>
        <View style={{ width: 12 }} />
        <View style={{ flex: 1 }}>
          <TextField label="GST amount" value={gst} onChangeText={setGst} keyboardType="decimal-pad" placeholder="0" editable={!lockedByMemo} />
        </View>
      </View>

      <Text style={styles.label}>Category *</Text>
      <View style={styles.chipWrap}>
        {categories.map((c) => {
          const active = category === c.value;
          return (
            <Pressable
              key={c.value}
              disabled={lockedByMemo}
              style={[styles.chip, active && { backgroundColor: c.color, borderColor: c.color }]}
              onPress={() => setCategory(c.value)}
            >
              <Text style={[styles.chipText, active && { color: '#fff' }]}>{c.label}</Text>
            </Pressable>
          );
        })}
      </View>

      <Text style={[styles.label, { marginTop: 16 }]}>Payment method</Text>
      <View style={styles.chipWrap}>
        {PAYMENT_METHODS.map((m) => {
          const active = method === m;
          return (
            <Pressable
              key={m}
              disabled={lockedByMemo}
              style={[styles.chip, active && styles.chipActive]}
              onPress={() => setMethod(m)}
            >
              <Text style={[styles.chipText, active && { color: '#fff' }]}>{m.toUpperCase()}</Text>
            </Pressable>
          );
        })}
      </View>

      <View style={{ height: 16 }} />
      <TextField label="Notes" value={notes} onChangeText={setNotes} placeholder="Optional" multiline style={{ height: 80, textAlignVertical: 'top' }} editable={!lockedByMemo} />

      {!lockedByMemo ? (
        <Button title={existing ? 'Save changes' : 'Add expense'} onPress={save} loading={saveMut.isPending} style={{ marginTop: 8 }} />
      ) : null}

      {existing && !lockedByMemo ? (
        <Button
          title="Delete expense"
          variant="danger"
          style={{ marginTop: 12, marginBottom: 40 }}
          loading={deleteMut.isPending}
          onPress={() =>
            Alert.alert('Delete expense?', 'This cannot be undone.', [
              { text: 'Cancel', style: 'cancel' },
              { text: 'Delete', style: 'destructive', onPress: () => deleteMut.mutate() },
            ])
          }
        />
      ) : (
        <View style={{ height: 40 }} />
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  lockNote: { fontSize: 13, color: colors.warning, marginBottom: 14, lineHeight: 18 },
  inline: { flexDirection: 'row' },
  label: { fontSize: 13, fontWeight: '600', color: colors.text, marginBottom: 8 },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { borderWidth: 1, borderColor: colors.border, borderRadius: 999, paddingHorizontal: 12, paddingVertical: 7, backgroundColor: colors.card },
  chipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  chipText: { fontSize: 12, fontWeight: '600', color: colors.text },
});
