import React, { useMemo, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { getGstr1 } from '../../api/endpoints';
import { downloadAndShare } from '../../api/files';
import { apiErrorMessage } from '../../api/client';
import { Button, Card } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { Gstr1Summary } from '../../api/types';

function monthRange(offset: number): { from: string; to: string } {
  const d = new Date();
  d.setDate(1);
  d.setMonth(d.getMonth() + offset);
  const from = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
  const end = new Date(d.getFullYear(), d.getMonth() + 1, 0);
  const to = `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`;
  return { from, to };
}

function fyRange(): { from: string; to: string } {
  const now = new Date();
  const y = now.getMonth() + 1 >= 4 ? now.getFullYear() : now.getFullYear() - 1;
  return { from: `${y}-04-01`, to: `${y + 1}-03-31` };
}

const PERIODS = [
  { key: 'this_month', label: 'This month' },
  { key: 'last_month', label: 'Last month' },
  { key: 'this_fy', label: 'This FY' },
];

export default function Gstr1Screen() {
  const [periodKey, setPeriodKey] = useState('this_month');

  const range = useMemo(() => {
    if (periodKey === 'last_month') return monthRange(-1);
    if (periodKey === 'this_fy') return fyRange();
    return monthRange(0);
  }, [periodKey]);

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['gstr1', range.from, range.to],
    queryFn: () => getGstr1(range.from, range.to),
  });

  async function exportCsv() {
    try {
      await downloadAndShare(`/finance/gstr1/export/csv?from=${range.from}&to=${range.to}`, `gstr1-${range.from}.csv`);
    } catch (e) {
      Alert.alert('Export failed', apiErrorMessage(e));
    }
  }

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.bg }} contentContainerStyle={{ padding: 16 }}>
      <View style={styles.chipWrap}>
        {PERIODS.map((p) => (
          <Pressable
            key={p.key}
            style={[styles.chip, periodKey === p.key && styles.chipActive]}
            onPress={() => setPeriodKey(p.key)}
          >
            <Text style={[styles.chipText, periodKey === p.key && { color: '#fff' }]}>{p.label}</Text>
          </Pressable>
        ))}
      </View>
      <Text style={styles.range}>{range.from} → {range.to}</Text>

      {isLoading ? (
        <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />
      ) : isError || !data ? (
        <Text style={styles.error}>{apiErrorMessage(error)}</Text>
      ) : (
        <>
          <SummaryCard title="All outward supplies" s={data.all} highlight />
          <SummaryCard title="B2B (registered customers)" s={data.b2b} />
          <SummaryCard title="B2C (unregistered)" s={data.b2c} />

          <Button title="Export GSTR-1 CSV" variant="secondary" style={{ marginTop: 8 }} onPress={exportCsv} />
          <Text style={styles.note}>
            The CSV maps to the GSTR-1 B2B / B2C sections — hand it to your CA to file.
          </Text>
          <View style={{ height: 40 }} />
        </>
      )}
    </ScrollView>
  );
}

function SummaryCard({ title, s, highlight }: { title: string; s: Gstr1Summary; highlight?: boolean }) {
  return (
    <Card style={highlight ? { marginBottom: 12, borderColor: colors.primary, borderWidth: 1.5 } : { marginBottom: 12 }}>
      <View style={styles.cardHeader}>
        <Text style={styles.cardTitle}>{title}</Text>
        <Text style={styles.cardCount}>{s.count} invoices</Text>
      </View>
      <KV label="Taxable value" value={formatINR(s.taxable)} bold />
      <KV label="CGST" value={formatINR(s.cgst)} />
      <KV label="SGST" value={formatINR(s.sgst)} />
      <KV label="IGST" value={formatINR(s.igst)} />
      <KV label="Invoice total" value={formatINR(s.total)} bold />
    </Card>
  );
}

function KV({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
  return (
    <View style={styles.kv}>
      <Text style={[styles.kvLabel, bold && { fontWeight: '800', color: colors.text }]}>{label}</Text>
      <Text style={[styles.kvValue, bold && { fontWeight: '800' }]}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { borderWidth: 1, borderColor: colors.border, borderRadius: 999, paddingHorizontal: 14, paddingVertical: 8, backgroundColor: colors.card },
  chipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  chipText: { fontSize: 13, fontWeight: '600', color: colors.text },
  range: { fontSize: 12, color: colors.muted, marginTop: 8, marginBottom: 12 },
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 },
  cardTitle: { fontSize: 14, fontWeight: '800', color: colors.text, flex: 1 },
  cardCount: { fontSize: 12, color: colors.muted },
  kv: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 },
  kvLabel: { fontSize: 14, color: colors.muted },
  kvValue: { fontSize: 14, color: colors.text, fontWeight: '600' },
  note: { fontSize: 12, color: colors.muted, marginTop: 10, lineHeight: 17, fontStyle: 'italic' },
});
