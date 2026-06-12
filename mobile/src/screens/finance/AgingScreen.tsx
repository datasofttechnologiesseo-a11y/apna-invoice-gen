import React from 'react';
import { ActivityIndicator, Alert, FlatList, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { getAging } from '../../api/endpoints';
import { downloadAndShare } from '../../api/files';
import { apiErrorMessage } from '../../api/client';
import { Button, Card, EmptyState } from '../../components/ui';
import { colors, formatINR } from '../../theme';

export default function AgingScreen() {
  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['aging'],
    queryFn: getAging,
  });

  async function exportFile(kind: 'csv' | 'pdf') {
    try {
      await downloadAndShare(`/finance/aging/export/${kind}`, `receivables-aging.${kind}`);
    } catch (e) {
      Alert.alert('Export failed', apiErrorMessage(e));
    }
  }

  if (isLoading) {
    return <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />;
  }
  if (isError || !data) {
    return <Text style={styles.error}>{apiErrorMessage(error)}</Text>;
  }

  const s = data.summary;

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: colors.bg }}
      data={data.by_customer}
      keyExtractor={(_, i) => String(i)}
      refreshing={isRefetching}
      onRefresh={refetch}
      contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
      ListHeaderComponent={
        <>
          <Card style={styles.summary}>
            <Text style={styles.summaryLabel}>Total outstanding · as on {data.as_on}</Text>
            <Text style={styles.summaryAmt}>{formatINR(s.total)}</Text>
            <Text style={styles.summarySub}>{s.invoices} invoices · {s.customers} customers</Text>
            <View style={styles.buckets}>
              <Bucket label="Current" value={s.current} color={colors.success} />
              <Bucket label="30–60d" value={s.b30_60} color={colors.warning} />
              <Bucket label="60–90d" value={s.b60_90} color="#ea580c" />
              <Bucket label="90+ d" value={s.b90_plus} color={colors.danger} />
            </View>
          </Card>

          <View style={styles.exportRow}>
            <Button title="Export CSV" variant="secondary" style={styles.exportBtn} onPress={() => exportFile('csv')} />
            <View style={{ width: 10 }} />
            <Button title="Export PDF" variant="secondary" style={styles.exportBtn} onPress={() => exportFile('pdf')} />
          </View>

          <Text style={styles.sectionTitle}>By customer</Text>
        </>
      }
      ListEmptyComponent={<EmptyState title="Nothing overdue" subtitle="All issued invoices are settled." />}
      renderItem={({ item }) => (
        <Card style={styles.row}>
          <View style={styles.rowTop}>
            <Text style={styles.name}>{item.name}</Text>
            <Text style={styles.total}>{formatINR(item.total)}</Text>
          </View>
          <Text style={styles.sub}>
            {item.invoice_count} invoices · oldest {item.oldest_days}d
          </Text>
          <View style={styles.miniBuckets}>
            {item.current > 0 ? <Mini label="Cur" value={item.current} color={colors.success} /> : null}
            {item.b30_60 > 0 ? <Mini label="30-60" value={item.b30_60} color={colors.warning} /> : null}
            {item.b60_90 > 0 ? <Mini label="60-90" value={item.b60_90} color="#ea580c" /> : null}
            {item.b90_plus > 0 ? <Mini label="90+" value={item.b90_plus} color={colors.danger} /> : null}
          </View>
        </Card>
      )}
    />
  );
}

function Bucket({ label, value, color }: { label: string; value: number; color: string }) {
  return (
    <View style={styles.bucket}>
      <Text style={[styles.bucketValue, { color }]}>{formatINR(value)}</Text>
      <Text style={styles.bucketLabel}>{label}</Text>
    </View>
  );
}

function Mini({ label, value, color }: { label: string; value: number; color: string }) {
  return (
    <View style={[styles.mini, { borderColor: color }]}>
      <Text style={[styles.miniText, { color }]}>{label}: {formatINR(value)}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  summary: { marginBottom: 12 },
  summaryLabel: { fontSize: 13, color: colors.muted },
  summaryAmt: { fontSize: 28, fontWeight: '800', color: colors.text, marginTop: 2 },
  summarySub: { fontSize: 13, color: colors.muted, marginTop: 2 },
  buckets: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 16 },
  bucket: { alignItems: 'center', flex: 1 },
  bucketValue: { fontSize: 14, fontWeight: '800' },
  bucketLabel: { fontSize: 11, color: colors.muted, marginTop: 2 },
  exportRow: { flexDirection: 'row', marginBottom: 16 },
  exportBtn: { flex: 1, height: 44 },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: colors.text, marginBottom: 10 },
  row: { marginBottom: 10 },
  rowTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  name: { fontSize: 15, fontWeight: '700', color: colors.text, flex: 1 },
  total: { fontSize: 15, fontWeight: '800', color: colors.text },
  sub: { fontSize: 12, color: colors.muted, marginTop: 2 },
  miniBuckets: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 8 },
  mini: { borderWidth: 1, borderRadius: 6, paddingHorizontal: 8, paddingVertical: 3 },
  miniText: { fontSize: 11, fontWeight: '700' },
});
