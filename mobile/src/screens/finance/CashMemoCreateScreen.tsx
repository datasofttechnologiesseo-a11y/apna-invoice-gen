import React, { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { createCashMemo } from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Button, Card, TextField } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { FinanceStackParamList } from '../../navigation/types';
import type { CashMemoItemInput } from '../../api/types';

type Props = NativeStackScreenProps<FinanceStackParamList, 'CashMemoCreate'>;

const PAYMENT_MODES = ['cash', 'upi', 'card', 'bank', 'cheque', 'other'];
const GST_RATES = [0, 5, 12, 18, 28];

interface Row {
  description: string;
  hsn_sac: string;
  quantity: string;
  unit: string;
  rate: string;
}

const emptyRow = (): Row => ({ description: '', hsn_sac: '', quantity: '1', unit: '', rate: '' });

export default function CashMemoCreateScreen({ navigation }: Props) {
  const qc = useQueryClient();

  const [seller, setSeller] = useState('');
  const [gstin, setGstin] = useState('');
  const [sellerState, setSellerState] = useState('');
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [paymentMode, setPaymentMode] = useState('cash');
  const [gstRate, setGstRate] = useState(0);
  const [interstate, setInterstate] = useState(false);
  const [discount, setDiscount] = useState('');
  const [notes, setNotes] = useState('');
  const [rows, setRows] = useState<Row[]>([emptyRow()]);

  function updateRow(i: number, key: keyof Row, value: string) {
    setRows((rs) => rs.map((r, idx) => (idx === i ? { ...r, [key]: value } : r)));
  }
  function addRow() {
    setRows((rs) => [...rs, emptyRow()]);
  }
  function removeRow(i: number) {
    setRows((rs) => (rs.length > 1 ? rs.filter((_, idx) => idx !== i) : rs));
  }

  // Live preview mirrors the server compute().
  const totals = useMemo(() => {
    const subtotal = rows.reduce((s, r) => s + (parseFloat(r.quantity) || 0) * (parseFloat(r.rate) || 0), 0);
    const disc = parseFloat(discount) || 0;
    const taxable = Math.max(0, subtotal - disc);
    const gst = Math.round(((taxable * gstRate) / 100) * 100) / 100;
    const cgst = gstRate > 0 && !interstate ? Math.round((gst / 2) * 100) / 100 : 0;
    const sgst = gstRate > 0 && !interstate ? gst - cgst : 0;
    const igst = gstRate > 0 && interstate ? gst : 0;
    const preRound = taxable + cgst + sgst + igst;
    const grand = Math.round(preRound);
    return { subtotal, taxable, cgst, sgst, igst, grand };
  }, [rows, discount, gstRate, interstate]);

  const saveMut = useMutation({
    mutationFn: () => {
      const items: CashMemoItemInput[] = rows
        .filter((r) => r.description.trim() && (parseFloat(r.rate) || 0) >= 0)
        .map((r) => ({
          description: r.description.trim(),
          hsn_sac: r.hsn_sac || undefined,
          quantity: parseInt(r.quantity, 10) || 1,
          unit: r.unit || undefined,
          rate: parseFloat(r.rate) || 0,
        }));
      return createCashMemo({
        memo_date: date,
        seller_name: seller.trim(),
        seller_gstin: gstin || undefined,
        seller_state: sellerState || undefined,
        discount: parseFloat(discount) || undefined,
        gst_rate: gstRate || undefined,
        is_interstate: interstate,
        payment_mode: paymentMode,
        notes: notes || undefined,
        items,
      });
    },
    onSuccess: (memo) => {
      qc.invalidateQueries({ queryKey: ['cash-memos'] });
      navigation.replace('CashMemoDetail', { id: memo.id });
    },
    onError: (e) => Alert.alert('Could not save', apiErrorMessage(e)),
  });

  function save() {
    if (!seller.trim()) return Alert.alert('Seller name is required');
    if (!rows.some((r) => r.description.trim())) return Alert.alert('Add at least one item');
    saveMut.mutate();
  }

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.bg }} contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
      <Text style={styles.section}>Seller</Text>
      <TextField label="Seller name *" value={seller} onChangeText={setSeller} placeholder="Who you bought from" />
      <TextField label="Seller GSTIN" value={gstin} onChangeText={setGstin} autoCapitalize="characters" placeholder="Optional" />
      <TextField label="Seller state" value={sellerState} onChangeText={setSellerState} placeholder="Optional" />
      <TextField label="Date *" value={date} onChangeText={setDate} placeholder="YYYY-MM-DD" autoCapitalize="none" />

      <Text style={styles.section}>Payment mode</Text>
      <View style={styles.chipWrap}>
        {PAYMENT_MODES.map((m) => (
          <Chip key={m} label={m.toUpperCase()} active={paymentMode === m} onPress={() => setPaymentMode(m)} />
        ))}
      </View>

      <Text style={styles.section}>Items</Text>
      {rows.map((r, i) => (
        <Card key={i} style={{ marginBottom: 10 }}>
          <View style={styles.rowHeader}>
            <Text style={styles.rowLabel}>Item {i + 1}</Text>
            {rows.length > 1 ? (
              <Pressable onPress={() => removeRow(i)}>
                <Text style={styles.remove}>Remove</Text>
              </Pressable>
            ) : null}
          </View>
          <TextField label="Description" value={r.description} onChangeText={(v) => updateRow(i, 'description', v)} />
          <View style={styles.inline}>
            <View style={{ flex: 1 }}>
              <TextField label="Qty" value={r.quantity} onChangeText={(v) => updateRow(i, 'quantity', v)} keyboardType="number-pad" />
            </View>
            <View style={{ width: 12 }} />
            <View style={{ flex: 1.4 }}>
              <TextField label="Rate" value={r.rate} onChangeText={(v) => updateRow(i, 'rate', v)} keyboardType="decimal-pad" />
            </View>
          </View>
          <View style={styles.inline}>
            <View style={{ flex: 1 }}>
              <TextField label="HSN/SAC" value={r.hsn_sac} onChangeText={(v) => updateRow(i, 'hsn_sac', v)} placeholder="Optional" />
            </View>
            <View style={{ width: 12 }} />
            <View style={{ flex: 1 }}>
              <TextField label="Unit" value={r.unit} onChangeText={(v) => updateRow(i, 'unit', v)} placeholder="pcs" />
            </View>
          </View>
        </Card>
      ))}
      <Button title="+ Add item" variant="secondary" onPress={addRow} />

      <Text style={styles.section}>GST & discount</Text>
      <Text style={styles.label}>GST rate</Text>
      <View style={styles.chipWrap}>
        {GST_RATES.map((g) => (
          <Chip key={g} label={`${g}%`} active={gstRate === g} onPress={() => setGstRate(g)} />
        ))}
      </View>
      {gstRate > 0 ? (
        <Pressable style={styles.toggle} onPress={() => setInterstate((v) => !v)}>
          <View style={[styles.checkbox, interstate && styles.checkboxOn]}>
            {interstate ? <Text style={styles.checkboxTick}>✓</Text> : null}
          </View>
          <Text style={styles.toggleText}>Inter-state purchase (IGST instead of CGST+SGST)</Text>
        </Pressable>
      ) : null}
      <View style={{ height: 12 }} />
      <TextField label="Discount" value={discount} onChangeText={setDiscount} keyboardType="decimal-pad" placeholder="0" />
      <TextField label="Notes" value={notes} onChangeText={setNotes} placeholder="Optional" multiline style={{ height: 80, textAlignVertical: 'top' }} />

      <Card style={styles.totalsCard}>
        <Row label="Taxable value" value={formatINR(totals.taxable)} />
        {totals.cgst > 0 ? <Row label="CGST" value={formatINR(totals.cgst)} /> : null}
        {totals.sgst > 0 ? <Row label="SGST" value={formatINR(totals.sgst)} /> : null}
        {totals.igst > 0 ? <Row label="IGST" value={formatINR(totals.igst)} /> : null}
        <Row label="Grand total" value={formatINR(totals.grand)} bold />
      </Card>

      <Button title="Save cash memo" onPress={save} loading={saveMut.isPending} style={{ marginTop: 8, marginBottom: 40 }} />
    </ScrollView>
  );
}

function Chip({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  return (
    <Pressable style={[styles.chip, active && styles.chipActive]} onPress={onPress}>
      <Text style={[styles.chipText, active && styles.chipTextActive]}>{label}</Text>
    </Pressable>
  );
}

function Row({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
  return (
    <View style={styles.totalRow}>
      <Text style={[styles.totalLabel, bold && { fontWeight: '800', color: colors.text }]}>{label}</Text>
      <Text style={[styles.totalValue, bold && { fontWeight: '800' }]}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { fontSize: 15, fontWeight: '800', color: colors.text, marginTop: 16, marginBottom: 10 },
  label: { fontSize: 13, fontWeight: '600', color: colors.text, marginBottom: 6 },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { borderWidth: 1, borderColor: colors.border, borderRadius: 999, paddingHorizontal: 14, paddingVertical: 8, backgroundColor: colors.card },
  chipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  chipText: { fontSize: 13, fontWeight: '600', color: colors.text },
  chipTextActive: { color: '#fff' },
  rowHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  rowLabel: { fontSize: 13, fontWeight: '700', color: colors.muted },
  remove: { fontSize: 13, fontWeight: '700', color: colors.danger },
  inline: { flexDirection: 'row' },
  toggle: { flexDirection: 'row', alignItems: 'center', marginTop: 12 },
  checkbox: { width: 24, height: 24, borderRadius: 6, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center', marginRight: 10 },
  checkboxOn: { backgroundColor: colors.primary, borderColor: colors.primary },
  checkboxTick: { color: '#fff', fontWeight: '800' },
  toggleText: { fontSize: 13, color: colors.text, flex: 1 },
  totalsCard: { marginTop: 16 },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  totalLabel: { fontSize: 14, color: colors.muted },
  totalValue: { fontSize: 14, color: colors.text },
});
