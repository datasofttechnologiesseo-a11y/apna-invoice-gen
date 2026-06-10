import React, { useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { getGstr3b } from '../../api/endpoints';
import { downloadAndShare } from '../../api/files';
import { apiErrorMessage } from '../../api/client';
import { Button, Card } from '../../components/ui';
import { colors, formatINR } from '../../theme';

// Default to the previous calendar month (the one you'd be filing).
function prevMonth(): string {
  const d = new Date();
  d.setDate(1);
  d.setMonth(d.getMonth() - 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function shiftMonth(ym: string, delta: number): string {
  const [y, m] = ym.split('-').map(Number);
  const d = new Date(y, m - 1 + delta, 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function Gstr3bScreen() {
  const [month, setMonth] = useState(prevMonth());
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['gstr3b', month],
    queryFn: () => getGstr3b(month),
  });

  async function exportFile(kind: 'csv' | 'pdf') {
    try {
      await downloadAndShare(`/finance/gstr3b/export/${kind}?month=${month}`, `gstr3b-${month}.${kind}`);
    } catch (e) {
      Alert.alert('Export failed', apiErrorMessage(e));
    }
  }

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.bg }} contentContainerStyle={{ padding: 16 }}>
      <View style={styles.monthNav}>
        <Pressable style={styles.navBtn} onPress={() => setMonth((m) => shiftMonth(m, -1))}>
          <Text style={styles.navBtnText}>‹</Text>
        </Pressable>
        <Text style={styles.monthLabel}>{data?.period.label ?? month}</Text>
        <Pressable style={styles.navBtn} onPress={() => setMonth((m) => shiftMonth(m, 1))}>
          <Text style={styles.navBtnText}>›</Text>
        </Pressable>
      </View>

      {isLoading ? (
        <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />
      ) : isError || !data ? (
        <Text style={styles.error}>{apiErrorMessage(error)}</Text>
      ) : (
        <>
          <Card style={styles.hero}>
            <Text style={styles.heroLabel}>Net cash payable</Text>
            <Text style={styles.heroValue}>{formatINR(data.net_cash.total)}</Text>
            <Text style={styles.heroSub}>
              {data.invoice_count} invoices · {data.expense_count} expenses · {data.cash_memo_count} memos
            </Text>
          </Card>

          <Section title="3.1(a) Outward taxable supplies">
            <KV label="Taxable value" value={formatINR(data.outward.taxable)} />
            <KV label="IGST" value={formatINR(data.outward.igst)} />
            <KV label="CGST" value={formatINR(data.outward.cgst)} />
            <KV label="SGST" value={formatINR(data.outward.sgst)} />
          </Section>

          {data.rcm_outward.taxable > 0 ? (
            <Section title="3.1(d) Inward supplies (reverse charge)">
              <KV label="Taxable value" value={formatINR(data.rcm_outward.taxable)} />
            </Section>
          ) : null}

          <Section title="4(A)(5) ITC available">
            <KV label="IGST" value={formatINR(data.itc.igst)} />
            <KV label="CGST" value={formatINR(data.itc.cgst)} />
            <KV label="SGST" value={formatINR(data.itc.sgst)} />
            <KV label="Total ITC" value={formatINR(data.itc.total)} bold />
          </Section>

          <Section title="6.1 Net cash payable">
            <KV label="IGST" value={formatINR(data.net_cash.igst)} />
            <KV label="CGST" value={formatINR(data.net_cash.cgst)} />
            <KV label="SGST" value={formatINR(data.net_cash.sgst)} />
            <KV label="Total" value={formatINR(data.net_cash.total)} bold />
          </Section>

          <Text style={styles.note}>
            Computed from your books. Verify against GSTR-1 + ITC ledgers before filing.
          </Text>

          <View style={styles.exportRow}>
            <Button title="Export CSV" variant="secondary" style={styles.exportBtn} onPress={() => exportFile('csv')} />
            <View style={{ width: 10 }} />
            <Button title="Export PDF" variant="secondary" style={styles.exportBtn} onPress={() => exportFile('pdf')} />
          </View>
          <View style={{ height: 40 }} />
        </>
      )}
    </ScrollView>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <Card style={{ marginTop: 12 }}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {children}
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
  monthNav: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 },
  navBtn: { width: 44, height: 44, borderRadius: 10, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.card },
  navBtnText: { fontSize: 22, color: colors.primary, fontWeight: '800' },
  monthLabel: { fontSize: 16, fontWeight: '700', color: colors.text },
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  hero: { alignItems: 'center', paddingVertical: 20 },
  heroLabel: { fontSize: 14, color: colors.muted },
  heroValue: { fontSize: 30, fontWeight: '800', color: colors.text, marginTop: 6 },
  heroSub: { fontSize: 12, color: colors.muted, marginTop: 6 },
  sectionTitle: { fontSize: 14, fontWeight: '800', color: colors.text, marginBottom: 10 },
  kv: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 },
  kvLabel: { fontSize: 14, color: colors.muted },
  kvValue: { fontSize: 14, color: colors.text, fontWeight: '600' },
  note: { fontSize: 12, color: colors.muted, marginTop: 12, lineHeight: 17, fontStyle: 'italic' },
  exportRow: { flexDirection: 'row', marginTop: 16 },
  exportBtn: { flex: 1, height: 44 },
});
