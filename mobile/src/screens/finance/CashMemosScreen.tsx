import React from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { getCashMemos } from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Card, EmptyState } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { FinanceStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<FinanceStackParamList, 'CashMemos'>;

export default function CashMemosScreen() {
  const insets = useSafeAreaInsets();
  const navigation = useNavigation<Nav>();

  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['cash-memos'],
    queryFn: () => getCashMemos(),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg, paddingTop: insets.top + 4 }}>
      <View style={styles.header}>
        <Text style={styles.title}>Cash memos</Text>
        <Pressable style={styles.newBtn} onPress={() => navigation.navigate('CashMemoCreate')}>
          <Text style={styles.newBtnText}>+ New</Text>
        </Pressable>
      </View>

      {isLoading ? (
        <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />
      ) : isError ? (
        <Text style={styles.error}>{apiErrorMessage(error)}</Text>
      ) : (
        <FlatList
          data={data}
          keyExtractor={(m) => String(m.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          refreshing={isRefetching}
          onRefresh={refetch}
          ListEmptyComponent={
            <EmptyState title="No cash memos" subtitle="Record a cash purchase to track the expense." />
          }
          renderItem={({ item }) => (
            <Pressable onPress={() => navigation.navigate('CashMemoDetail', { id: item.id })}>
              <Card style={styles.row}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.memoNumber}>{item.memo_number ?? '—'}</Text>
                  <Text style={styles.seller}>{item.seller_name}</Text>
                  <Text style={styles.sub}>
                    {item.memo_date} · {item.payment_mode?.toUpperCase()}
                  </Text>
                </View>
                <Text style={styles.amount}>{formatINR(item.grand_total)}</Text>
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
  row: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  memoNumber: { fontSize: 15, fontWeight: '700', color: colors.text },
  seller: { fontSize: 14, color: colors.text, marginTop: 2 },
  sub: { fontSize: 12, color: colors.muted, marginTop: 2 },
  amount: { fontSize: 16, fontWeight: '800', color: colors.text, marginLeft: 8 },
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
});
