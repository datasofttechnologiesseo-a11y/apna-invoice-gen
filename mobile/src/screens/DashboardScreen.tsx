import React from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { BottomTabScreenProps } from '@react-navigation/bottom-tabs';
import { getDashboard } from '../api/endpoints';
import { apiErrorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Card, Centered, StatusBadge } from '../components/ui';
import { colors, formatINR } from '../theme';
import type { MainTabParamList } from '../navigation/types';
import { ActivityIndicator } from 'react-native';

type Props = BottomTabScreenProps<MainTabParamList, 'Home'>;

function Kpi({ label, value, color }: { label: string; value: string; color?: string }) {
  return (
    <Card style={styles.kpi}>
      <Text style={styles.kpiLabel}>{label}</Text>
      <Text style={[styles.kpiValue, color ? { color } : null]} numberOfLines={1} adjustsFontSizeToFit>
        {value}
      </Text>
    </Card>
  );
}

export default function DashboardScreen({ navigation }: Props) {
  const { user, company } = useAuth();
  const insets = useSafeAreaInsets();
  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['dashboard'],
    queryFn: getDashboard,
  });

  if (isLoading) {
    return (
      <Centered>
        <ActivityIndicator size="large" color={colors.primary} />
      </Centered>
    );
  }

  if (isError) {
    return (
      <Centered>
        <Text style={{ color: colors.danger, padding: 24, textAlign: 'center' }}>
          {apiErrorMessage(error)}
        </Text>
      </Centered>
    );
  }

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.bg }}
      contentContainerStyle={{ padding: 16, paddingTop: insets.top + 12, paddingBottom: 32 }}
      refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} />}
    >
      <Text style={styles.greeting}>Hi {user?.name?.split(' ')[0]} 👋</Text>
      <Text style={styles.company}>{company?.name ?? data?.company.name}</Text>

      <View style={styles.kpiGrid}>
        <Kpi label="Outstanding" value={formatINR(data!.kpis.outstanding)} color={colors.warning} />
        <Kpi label="Overdue" value={formatINR(data!.kpis.overdue)} color={colors.danger} />
        <Kpi label="Revenue (FY)" value={formatINR(data!.kpis.revenue_fy)} color={colors.success} />
        <Kpi label="This month" value={formatINR(data!.kpis.revenue_this_month)} />
      </View>

      <View style={styles.countsRow}>
        <Count label="Invoices" value={data!.counts.invoices} />
        <Count label="Drafts" value={data!.counts.drafts} />
        <Count label="Customers" value={data!.counts.customers} />
        <Count label="Products" value={data!.counts.products} />
      </View>

      <View style={styles.recentHeader}>
        <Text style={styles.sectionTitle}>Recent invoices</Text>
        <Text style={styles.link} onPress={() => navigation.navigate('Invoices')}>
          See all
        </Text>
      </View>

      {data!.recent_invoices.length === 0 ? (
        <Card>
          <Text style={{ color: colors.muted }}>No invoices yet.</Text>
        </Card>
      ) : (
        data!.recent_invoices.map((inv) => (
          <Card key={inv.id} style={styles.invoiceRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.invNumber}>{inv.display_number}</Text>
              <Text style={styles.invCustomer}>{inv.customer_name ?? '—'}</Text>
            </View>
            <View style={{ alignItems: 'flex-end' }}>
              <Text style={styles.invAmount}>{formatINR(inv.grand_total)}</Text>
              <StatusBadge status={inv.status} />
            </View>
          </Card>
        ))
      )}
    </ScrollView>
  );
}

function Count({ label, value }: { label: string; value: number }) {
  return (
    <View style={styles.count}>
      <Text style={styles.countValue}>{value}</Text>
      <Text style={styles.countLabel}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  greeting: { fontSize: 24, fontWeight: '800', color: colors.text },
  company: { fontSize: 15, color: colors.muted, marginBottom: 20 },
  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  kpi: { width: '47%', flexGrow: 1 },
  kpiLabel: { fontSize: 13, color: colors.muted },
  kpiValue: { fontSize: 22, fontWeight: '800', color: colors.text, marginTop: 6 },
  countsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 16,
    backgroundColor: colors.card,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    paddingVertical: 16,
  },
  count: { alignItems: 'center', flex: 1 },
  countValue: { fontSize: 20, fontWeight: '800', color: colors.text },
  countLabel: { fontSize: 12, color: colors.muted, marginTop: 2 },
  recentHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 24,
    marginBottom: 12,
  },
  sectionTitle: { fontSize: 18, fontWeight: '700', color: colors.text },
  link: { color: colors.primary, fontWeight: '600' },
  invoiceRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  invNumber: { fontSize: 15, fontWeight: '700', color: colors.text },
  invCustomer: { fontSize: 13, color: colors.muted, marginTop: 2 },
  invAmount: { fontSize: 15, fontWeight: '700', color: colors.text, marginBottom: 4 },
});
