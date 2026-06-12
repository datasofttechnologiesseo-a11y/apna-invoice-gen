import React from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { getExpenses } from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Card, EmptyState } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { FinanceStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<FinanceStackParamList, 'Expenses'>;

export default function ExpensesScreen() {
  const insets = useSafeAreaInsets();
  const navigation = useNavigation<Nav>();

  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['expenses'],
    queryFn: () => getExpenses(),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg, paddingTop: insets.top + 4 }}>
      <View style={styles.header}>
        <Text style={styles.title}>Expenses</Text>
        <Pressable style={styles.newBtn} onPress={() => navigation.navigate('ExpenseForm')}>
          <Text style={styles.newBtnText}>+ New</Text>
        </Pressable>
      </View>

      {isLoading ? (
        <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />
      ) : isError ? (
        <Text style={styles.error}>{apiErrorMessage(error)}</Text>
      ) : (
        <FlatList
          data={data!.data}
          keyExtractor={(e) => String(e.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          refreshing={isRefetching}
          onRefresh={refetch}
          ListHeaderComponent={
            <Card style={styles.summary}>
              <Text style={styles.summaryLabel}>Total outflow ({data!.meta.totals.count} entries)</Text>
              <Text style={styles.summaryAmt}>{formatINR(data!.meta.totals.outflow)}</Text>
              <Text style={styles.summarySub}>
                Base {formatINR(data!.meta.totals.amount)} · GST {formatINR(data!.meta.totals.gst)}
              </Text>
            </Card>
          }
          ListEmptyComponent={
            <EmptyState title="No expenses" subtitle="Add a cost to start building your P&L." />
          }
          renderItem={({ item }) => (
            <Pressable
              onPress={() =>
                item.cash_memo_id
                  ? navigation.navigate('CashMemoDetail', { id: item.cash_memo_id })
                  : navigation.navigate('ExpenseForm', { id: item.id })
              }
            >
              <Card style={styles.row}>
                <View style={[styles.dot, { backgroundColor: item.category_color }]} />
                <View style={{ flex: 1 }}>
                  <Text style={styles.desc}>{item.description}</Text>
                  <Text style={styles.sub}>
                    {item.category_label}
                    {item.vendor_name ? ` · ${item.vendor_name}` : ''}
                  </Text>
                  <Text style={styles.date}>
                    {item.entry_date}
                    {item.cash_memo_id ? ' · from cash memo' : ''}
                  </Text>
                </View>
                <Text style={styles.amount}>{formatINR(item.total)}</Text>
              </Card>
            </Pressable>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  title: { fontSize: 24, fontWeight: '800', color: colors.text },
  newBtn: { backgroundColor: colors.primary, paddingHorizontal: 16, paddingVertical: 8, borderRadius: 10 },
  newBtnText: { color: '#fff', fontWeight: '700' },
  summary: { marginBottom: 14 },
  summaryLabel: { fontSize: 13, color: colors.muted },
  summaryAmt: { fontSize: 26, fontWeight: '800', color: colors.text, marginTop: 2 },
  summarySub: { fontSize: 13, color: colors.muted, marginTop: 4 },
  row: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  dot: { width: 10, height: 10, borderRadius: 5, marginRight: 12 },
  desc: { fontSize: 15, fontWeight: '700', color: colors.text },
  sub: { fontSize: 13, color: colors.muted, marginTop: 2 },
  date: { fontSize: 12, color: colors.muted, marginTop: 2 },
  amount: { fontSize: 16, fontWeight: '800', color: colors.text, marginLeft: 8 },
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
});
