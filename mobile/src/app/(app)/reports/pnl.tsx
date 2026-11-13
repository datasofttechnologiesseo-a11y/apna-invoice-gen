import { Href, router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import type { PnlPeriod } from '@/api/finance';
import { AppText } from '@/components/app-text';
import { Card, Chips, ErrorState, KeyValue, Loading, SectionTitle } from '@/components/blocks';
import { BarChart, StackedBar } from '@/components/charts';
import { Screen } from '@/components/screen';
import { usePnl } from '@/hooks/finance';
import { StatStrip } from '@/screens/finance/stat-strip';
import { colors } from '@/theme';
import { money } from '@/utils/format';

const PERIODS: { value: PnlPeriod; label: string }[] = [
  { value: 'this_month', label: 'This month' },
  { value: 'last_month', label: 'Last month' },
  { value: 'this_quarter', label: 'This quarter' },
  { value: 'ytd', label: 'FY to date' },
  { value: 'this_fy', label: 'This FY' },
  { value: 'last_fy', label: 'Last FY' },
];

/** Profit & loss for a period (figures from App\Services\ProfitAndLoss), 6-month trend and spend by category. Accepts ?period=. */
export default function PnlReport() {
  const params = useLocalSearchParams<{ period?: string }>();
  const [period, setPeriod] = useState<PnlPeriod>(PERIODS.find((p) => p.value === params.period)?.value ?? 'this_month');
  const q = usePnl(period);
  const d = q.data;

  return (
    <Screen nav="back" title="Profit & loss" subtitle={d?.period.label} onRefresh={() => q.refetch()} refreshing={q.isRefetching}>
      <Chips value={period} options={PERIODS} onChange={setPeriod} />

      {q.isLoading ? (
        <Loading />
      ) : q.error || !d ? (
        <ErrorState message={(q.error as Error)?.message ?? 'Could not load the report'} onRetry={() => q.refetch()} />
      ) : (
        <>
          <StatStrip
            stats={[
              { label: 'Revenue', value: d.revenue.taxable, tone: 'accent' },
              { label: 'Expenses', value: d.expense.taxable, tone: 'bad' },
              { label: d.net_profit < 0 ? 'Loss' : 'Profit', value: d.net_profit, tone: d.net_profit < 0 ? 'bad' : 'good' },
            ]}
          />

          <Card>
            <AppText variant="h3">Revenue (before GST)</AppText>
            <KeyValue label="Sales, net of credit notes" value={money(d.revenue.taxable)} />
            <KeyValue label="GST collected" value={money(d.revenue.gst_collected)} />
            <KeyValue label="Received" value={money(d.revenue.received)} />
            <KeyValue label="Still to collect" value={money(d.revenue.outstanding)} />
            <View style={{ height: 1, backgroundColor: colors.divider }} />
            <AppText variant="h3">Expenses (before GST)</AppText>
            <KeyValue label="Expenses and purchases" value={money(d.expense.taxable)} />
            <KeyValue label="GST input credit" value={money(d.expense.gst_itc)} />
            <KeyValue label="Cash paid out" value={money(d.expense.cash_out)} />
            <View style={{ height: 1, backgroundColor: colors.divider }} />
            <KeyValue label={`Net ${d.net_profit < 0 ? 'loss' : 'profit'} · margin ${d.margin}%`} value={money(d.net_profit)} strong />
            <KeyValue label="Net GST payable" value={money(d.gst_payable)} />
            <KeyValue label="Cash in hand (received − paid)" value={money(d.cash_in_hand)} />
          </Card>

          <SectionTitle title="Last 6 months" />
          <Card>
            <BarChart
              data={d.trend.slice(-6).map((t) => ({ label: t.label.split(' ')[0], full: t.label, values: { revenue: t.revenue, expenses: t.expenses } }))}
              series={[
                { key: 'revenue', label: 'Revenue', color: colors.teal },
                { key: 'expenses', label: 'Expenses', color: colors.amber },
              ]}
            />
          </Card>

          <SectionTitle title="Where the money went" action={d.by_category.length ? 'All expenses' : undefined} onAction={() => router.push(`/expenses?from=${d.period.start}&to=${d.period.end}` as Href)} />
          <Card>
            {d.by_category.length ? (
              <StackedBar
                showCount
                slices={d.by_category.map((c) => ({ key: c.category, label: c.label, value: c.total, color: c.color, count: c.count }))}
                onPress={(s) => router.push(`/expenses?category=${encodeURIComponent(s.key)}&from=${d.period.start}&to=${d.period.end}` as Href)}
              />
            ) : (
              <AppText variant="small" color={colors.textMuted}>No expenses recorded in this period.</AppText>
            )}
          </Card>
        </>
      )}
    </Screen>
  );
}
