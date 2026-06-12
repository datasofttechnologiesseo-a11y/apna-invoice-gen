import React from 'react';
import { ActivityIndicator, FlatList, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { getCustomerLedger } from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Card, EmptyState } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { CustomersStackParamList } from '../../navigation/types';
import type { LedgerEntry } from '../../api/types';

type Props = NativeStackScreenProps<CustomersStackParamList, 'CustomerLedger'>;

const TYPE_META: Record<LedgerEntry['type'], { icon: string; color: string }> = {
  invoice: { icon: '🧾', color: colors.final },
  payment: { icon: '✅', color: colors.paid },
  credit_note: { icon: '↩️', color: colors.converted },
};

export default function CustomerLedgerScreen({ route }: Props) {
  const { id } = route.params;
  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['customer-ledger', id],
    queryFn: () => getCustomerLedger(id),
  });

  if (isLoading) {
    return <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />;
  }
  if (isError) {
    return <Text style={styles.error}>{apiErrorMessage(error)}</Text>;
  }

  const t = data!.totals;

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: colors.bg }}
      data={data!.entries}
      keyExtractor={(_, i) => String(i)}
      refreshing={isRefetching}
      onRefresh={refetch}
      contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
      ListHeaderComponent={
        <Card style={styles.summary}>
          <Text style={styles.summaryName}>{data!.customer.name}</Text>
          <View style={styles.summaryGrid}>
            <Summary label="Invoiced" value={t.invoiced} />
            <Summary label="Received" value={t.received} color={colors.paid} />
            <Summary label="Credited" value={t.credited} color={colors.converted} />
            <Summary label="Outstanding" value={t.outstanding} color={t.outstanding > 0 ? colors.danger : colors.paid} strong />
          </View>
        </Card>
      }
      ListEmptyComponent={
        <EmptyState title="No ledger activity" subtitle="Issued invoices and payments will appear here." />
      }
      renderItem={({ item }) => {
        const meta = TYPE_META[item.type];
        const isCredit = item.credit > 0;
        return (
          <Card style={styles.row}>
            <Text style={styles.rowIcon}>{meta.icon}</Text>
            <View style={{ flex: 1 }}>
              <Text style={styles.rowRef}>{item.ref || '—'}</Text>
              <Text style={styles.rowSub}>{item.particulars}</Text>
              <Text style={styles.rowDate}>{item.date ?? ''}</Text>
            </View>
            <View style={{ alignItems: 'flex-end' }}>
              <Text style={[styles.rowAmt, { color: isCredit ? colors.paid : colors.text }]}>
                {isCredit ? '− ' : '+ '}
                {formatINR(isCredit ? item.credit : item.debit)}
              </Text>
              <Text style={styles.rowBal}>Bal {formatINR(item.balance)}</Text>
            </View>
          </Card>
        );
      }}
    />
  );
}

function Summary({ label, value, color, strong }: { label: string; value: number; color?: string; strong?: boolean }) {
  return (
    <View style={styles.summaryCell}>
      <Text style={styles.summaryLabel}>{label}</Text>
      <Text style={[styles.summaryValue, color ? { color } : null, strong ? { fontWeight: '800' } : null]}>
        {formatINR(value)}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  summary: { marginBottom: 14 },
  summaryName: { fontSize: 18, fontWeight: '800', color: colors.text, marginBottom: 12 },
  summaryGrid: { flexDirection: 'row', flexWrap: 'wrap' },
  summaryCell: { width: '50%', marginBottom: 12 },
  summaryLabel: { fontSize: 12, color: colors.muted },
  summaryValue: { fontSize: 17, fontWeight: '700', color: colors.text, marginTop: 2 },
  row: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  rowIcon: { fontSize: 20, marginRight: 12 },
  rowRef: { fontSize: 15, fontWeight: '700', color: colors.text },
  rowSub: { fontSize: 13, color: colors.muted, marginTop: 2 },
  rowDate: { fontSize: 12, color: colors.muted, marginTop: 2 },
  rowAmt: { fontSize: 15, fontWeight: '700' },
  rowBal: { fontSize: 12, color: colors.muted, marginTop: 2 },
});
