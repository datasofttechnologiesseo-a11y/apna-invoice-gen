import React, { useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { getPnl } from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Card } from '../../components/ui';
import { colors, formatINR } from '../../theme';

const PERIODS: { key: string; label: string }[] = [
  { key: 'this_month', label: 'This month' },
  { key: 'last_month', label: 'Last month' },
  { key: 'this_quarter', label: 'This quarter' },
  { key: 'this_fy', label: 'This FY' },
  { key: 'ytd', label: 'FY to date' },
];

export default function PnlScreen() {
  const [period, setPeriod] = useState('this_month');
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['pnl', period],
    queryFn: () => getPnl(period),
  });

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.bg }} contentContainerStyle={{ padding: 16 }}>
      <View style={styles.chipWrap}>
        {PERIODS.map((p) => (
          <Pressable
            key={p.key}
            style={[styles.chip, period === p.key && styles.chipActive]}
            onPress={() => setPeriod(p.key)}
          >
            <Text style={[styles.chipText, period === p.key && { color: '#fff' }]}>{p.label}</Text>
          </Pressable>
        ))}
      </View>

      {isLoading ? (
        <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />
      ) : isError || !data ? (
        <Text style={styles.error}>{apiErrorMessage(error)}</Text>
      ) : (
        <>
          <Text style={styles.periodLabel}>{data.period.label}</Text>

          <Card style={styles.heroCard}>
            <Text style={styles.heroLabel}>Net profit</Text>
            <Text style={[styles.heroValue, { color: data.net_profit >= 0 ? colors.success : colors.danger }]}>
              {formatINR(data.net_profit)}
            </Text>
            <Text style={styles.heroSub}>{data.margin.toFixed(1)}% margin on taxable revenue</Text>
          </Card>

          <View style={styles.grid}>
            <Stat label="Revenue (taxable)" value={formatINR(data.revenue.taxable)} />
            <Stat label="Expenses" value={formatINR(data.expense.taxable)} />
            <Stat label="Cash in hand" value={formatINR(data.cash_in_hand)} color={data.cash_in_hand >= 0 ? colors.success : colors.danger} />
            <Stat label="GST payable" value={formatINR(data.gst_payable)} />
            <Stat label="Received" value={formatINR(data.revenue.received)} />
            <Stat label="Outstanding" value={formatINR(data.revenue.outstanding)} color={colors.warning} />
          </View>

          {data.by_category.length > 0 ? (
            <Card style={{ marginTop: 12 }}>
              <Text style={styles.sectionTitle}>Expenses by category</Text>
              {data.by_category.map((c) => (
                <View key={c.category} style={styles.catRow}>
                  <View style={[styles.dot, { backgroundColor: c.color }]} />
                  <Text style={styles.catLabel}>{c.label}</Text>
                  <Text style={styles.catShare}>{c.share.toFixed(0)}%</Text>
                  <Text style={styles.catAmt}>{formatINR(c.total)}</Text>
                </View>
              ))}
            </Card>
          ) : null}

          <Card style={{ marginTop: 12, marginBottom: 40 }}>
            <Text style={styles.sectionTitle}>12-month trend</Text>
            <TrendChart trend={data.trend} />
          </Card>
        </>
      )}
    </ScrollView>
  );
}

function Stat({ label, value, color }: { label: string; value: string; color?: string }) {
  return (
    <Card style={styles.statCard}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.statValue, color ? { color } : null]}>{value}</Text>
    </Card>
  );
}

function TrendChart({ trend }: { trend: { label: string; revenue: number; expenses: number }[] }) {
  const max = Math.max(1, ...trend.map((t) => Math.max(t.revenue, t.expenses)));
  return (
    <View>
      <View style={styles.barRow}>
        {trend.map((t, i) => (
          <View key={i} style={styles.barCol}>
            <View style={styles.barTrack}>
              <View style={[styles.bar, { height: `${(t.revenue / max) * 100}%`, backgroundColor: colors.primary }]} />
              <View style={[styles.bar, { height: `${(t.expenses / max) * 100}%`, backgroundColor: colors.danger, marginLeft: 2 }]} />
            </View>
            <Text style={styles.barLabel}>{t.label.split(' ')[0]}</Text>
          </View>
        ))}
      </View>
      <View style={styles.legend}>
        <View style={[styles.legendDot, { backgroundColor: colors.primary }]} />
        <Text style={styles.legendText}>Revenue</Text>
        <View style={[styles.legendDot, { backgroundColor: colors.danger, marginLeft: 16 }]} />
        <Text style={styles.legendText}>Expenses</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 8 },
  chip: { borderWidth: 1, borderColor: colors.border, borderRadius: 999, paddingHorizontal: 14, paddingVertical: 8, backgroundColor: colors.card },
  chipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  chipText: { fontSize: 13, fontWeight: '600', color: colors.text },
  periodLabel: { fontSize: 13, color: colors.muted, marginTop: 4, marginBottom: 10 },
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  heroCard: { alignItems: 'center', paddingVertical: 24 },
  heroLabel: { fontSize: 14, color: colors.muted },
  heroValue: { fontSize: 34, fontWeight: '800', marginTop: 6 },
  heroSub: { fontSize: 13, color: colors.muted, marginTop: 6 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', marginTop: 12 },
  statCard: { width: '48.5%', marginBottom: 10 },
  statLabel: { fontSize: 12, color: colors.muted },
  statValue: { fontSize: 18, fontWeight: '800', color: colors.text, marginTop: 4 },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: colors.text, marginBottom: 12 },
  catRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 6 },
  dot: { width: 10, height: 10, borderRadius: 5, marginRight: 10 },
  catLabel: { flex: 1, fontSize: 14, color: colors.text },
  catShare: { fontSize: 12, color: colors.muted, marginRight: 10 },
  catAmt: { fontSize: 14, fontWeight: '700', color: colors.text },
  barRow: { flexDirection: 'row', height: 120, alignItems: 'flex-end', justifyContent: 'space-between' },
  barCol: { flex: 1, alignItems: 'center' },
  barTrack: { flexDirection: 'row', height: 100, alignItems: 'flex-end' },
  bar: { width: 5, borderTopLeftRadius: 2, borderTopRightRadius: 2 },
  barLabel: { fontSize: 8, color: colors.muted, marginTop: 4 },
  legend: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', marginTop: 12 },
  legendDot: { width: 10, height: 10, borderRadius: 5, marginRight: 6 },
  legendText: { fontSize: 12, color: colors.muted },
});
