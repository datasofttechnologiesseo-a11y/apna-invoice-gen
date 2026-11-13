import { Href, router } from 'expo-router';
import { Alert, View } from 'react-native';

import { financeApi } from '@/api/finance';
import { AppText } from '@/components/app-text';
import { Avatar, Card, EmptyState, ErrorState, Loading, Row, SectionTitle } from '@/components/blocks';
import { RowBars } from '@/components/charts';
import { BarAction, Screen } from '@/components/screen';
import { Pill } from '@/components/status-pill';
import { useAging } from '@/hooks/finance';
import { StatStrip } from '@/screens/finance/stat-strip';
import { colors } from '@/theme';
import { date, money } from '@/utils/format';
import { downloadAndShare } from '@/utils/share';

/** Receivables ageing: unpaid balances by days past due, and per customer. CSV/PDF export. */
export default function AgingReport() {
  const q = useAging();
  const d = q.data;

  const exportReport = () =>
    Alert.alert('Export ageing report', 'Share with your CA or team.', [
      { text: 'PDF', onPress: () => downloadAndShare(financeApi.agingPdfPath, `receivables-ageing-${d?.as_on ?? 'today'}.pdf`) },
      { text: 'CSV (Excel)', onPress: () => downloadAndShare(financeApi.agingCsvPath, `receivables-ageing-${d?.as_on ?? 'today'}.csv`, 'text/csv') },
      { text: 'Cancel', style: 'cancel' },
    ]);

  return (
    <Screen
      nav="back"
      title="Receivables ageing"
      subtitle={d ? `As on ${date(d.as_on)}` : undefined}
      right={<BarAction icon="share-outline" label="Export ageing report" onPress={exportReport} />}
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
    >
      {q.isLoading ? (
        <Loading />
      ) : q.error || !d ? (
        <ErrorState message={(q.error as Error)?.message ?? 'Could not load the report'} onRetry={() => q.refetch()} />
      ) : !d.summary.invoices ? (
        <EmptyState icon="checkmark-done-circle-outline" title="Nothing outstanding" body="Every issued invoice is paid. Unpaid balances show up here by age." action="See invoices" onAction={() => router.push('/invoices' as Href)} />
      ) : (
        <>
          <StatStrip
            stats={[
              { label: 'Outstanding', value: d.summary.total, tone: 'bad' },
              { label: 'Customers', value: d.summary.customers, count: true },
              { label: 'Invoices', value: d.summary.invoices, count: true },
            ]}
          />

          <SectionTitle title="By days past due" />
          <Card>
            <RowBars
              rows={[
                { key: 'current', label: '0–30 days', value: d.summary.current, color: colors.teal },
                { key: '30-60', label: '31–60 days', value: d.summary.b30_60, color: colors.amber },
                { key: '60-90', label: '61–90 days', value: d.summary.b60_90, color: colors.amberText },
                { key: '90+', label: 'Over 90 days', value: d.summary.b90_plus, color: colors.red },
              ]}
            />
          </Card>

          <SectionTitle title="By customer" />
          <Card style={{ gap: 0, paddingVertical: 4 }}>
            {d.by_customer.map((c, i) => {
              const tone = c.b90_plus > 0 ? 'late' : c.b60_90 > 0 || c.b30_60 > 0 ? 'due' : 'part';
              return (
                <Row
                  key={`${c.name}-${i}`}
                  divider={i < d.by_customer.length - 1}
                  leading={<Avatar name={c.name} size={40} />}
                  title={c.name}
                  subtitle={`${c.invoice_count} invoice${c.invoice_count === 1 ? '' : 's'}${c.gstin ? ` · ${c.gstin}` : ''}`}
                  accessibilityLabel={`${c.name}, ${money(c.total)} outstanding, oldest ${c.oldest_days} days`}
                  trailing={
                    <View style={{ alignItems: 'flex-end', gap: 5 }}>
                      <AppText variant="money">{money(c.total)}</AppText>
                      <Pill label={c.oldest_days ? `${c.oldest_days} days` : 'Not yet due'} tone={tone} />
                    </View>
                  }
                />
              );
            })}
          </Card>
          <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
            Days counted from each invoice&apos;s due date (or invoice date when it has none).
          </AppText>
        </>
      )}
    </Screen>
  );
}
