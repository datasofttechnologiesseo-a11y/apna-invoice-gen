import React, { useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { AppHeader, HeaderAction } from '../../components/AppHeader';
import type { CompositeScreenProps } from '@react-navigation/native';
import type { BottomTabScreenProps } from '@react-navigation/bottom-tabs';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { getInvoices } from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Card, EmptyState, StatusBadge } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { InvoicesStackParamList, MainTabParamList } from '../../navigation/types';

type Props = CompositeScreenProps<
  NativeStackScreenProps<InvoicesStackParamList, 'InvoicesList'>,
  BottomTabScreenProps<MainTabParamList>
>;

const FILTERS: { key: string; label: string }[] = [
  { key: '', label: 'All' },
  { key: 'draft', label: 'Drafts' },
  { key: 'outstanding', label: 'Outstanding' },
  { key: 'paid', label: 'Paid' },
];

export default function InvoicesScreen({ navigation }: Props) {
  const insets = useSafeAreaInsets();
  const [status, setStatus] = useState('');
  const [search, setSearch] = useState('');

  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['invoices', status, search],
    queryFn: () => getInvoices({ status: status || undefined, search: search || undefined }),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <AppHeader
        title="Invoices"
        right={<HeaderAction label="+ New" onPress={() => navigation.navigate('InvoiceEdit', {})} />}
      />

      <TextInput
        style={[styles.search, { marginTop: 12 }]}
        placeholder="Search number or customer…"
        placeholderTextColor={colors.muted}
        value={search}
        onChangeText={setSearch}
      />

      <View style={styles.filters}>
        {FILTERS.map((f) => (
          <Pressable
            key={f.key}
            onPress={() => setStatus(f.key)}
            style={[styles.chip, status === f.key && styles.chipActive]}
          >
            <Text style={[styles.chipText, status === f.key && styles.chipTextActive]}>
              {f.label}
            </Text>
          </Pressable>
        ))}
      </View>

      {isLoading ? (
        <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />
      ) : isError ? (
        <Text style={styles.error}>{apiErrorMessage(error)}</Text>
      ) : (
        <FlatList
          data={data}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          refreshing={isRefetching}
          onRefresh={refetch}
          ListEmptyComponent={
            <EmptyState title="No invoices" subtitle="Tap + New to create your first invoice." />
          }
          renderItem={({ item }) => (
            <Pressable onPress={() => navigation.navigate('InvoiceDetail', { id: item.id })}>
              <Card style={styles.row}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.number}>{item.display_number}</Text>
                  <Text style={styles.customer}>{item.customer_name ?? '—'}</Text>
                  <Text style={styles.date}>{item.invoice_date ?? ''}</Text>
                </View>
                <View style={{ alignItems: 'flex-end' }}>
                  <Text style={styles.amount}>{formatINR(item.grand_total)}</Text>
                  {item.balance > 0 && item.status !== 'draft' ? (
                    <Text style={styles.balance}>Due {formatINR(item.balance)}</Text>
                  ) : null}
                  <StatusBadge status={item.status} />
                </View>
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
  },
  title: { fontSize: 26, fontWeight: '800', color: colors.text },
  newBtn: { backgroundColor: colors.primary, paddingHorizontal: 16, paddingVertical: 8, borderRadius: 10 },
  newBtnText: { color: '#fff', fontWeight: '700' },
  search: {
    margin: 16,
    marginBottom: 8,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontSize: 15,
    color: colors.text,
  },
  filters: { flexDirection: 'row', gap: 8, paddingHorizontal: 16, marginBottom: 4 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 999,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.border,
  },
  chipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  chipText: { color: colors.muted, fontWeight: '600', fontSize: 13 },
  chipTextActive: { color: '#fff' },
  row: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  number: { fontSize: 16, fontWeight: '700', color: colors.text },
  customer: { fontSize: 14, color: colors.text, marginTop: 2 },
  date: { fontSize: 12, color: colors.muted, marginTop: 2 },
  amount: { fontSize: 16, fontWeight: '700', color: colors.text },
  balance: { fontSize: 12, color: colors.warning, marginVertical: 2 },
  error: { color: colors.danger, textAlign: 'center', marginTop: 40, paddingHorizontal: 24 },
});
