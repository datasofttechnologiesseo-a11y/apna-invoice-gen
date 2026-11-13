import { router } from 'expo-router';
import { StyleSheet, View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, IconTile, Loading, Row, SectionTitle } from '@/components/blocks';
import { Button } from '@/components/button';
import { InvoiceRow } from '@/components/invoice-row';
import { Screen } from '@/components/screen';
import { useDashboard } from '@/hooks/queries';
import { colors, fonts, shadow } from '@/theme';
import { greeting, money } from '@/utils/format';

export default function Home() {
  const { user } = useAuth();
  const q = useDashboard();
  const d = q.data;
  const firstName = user?.name.split(' ')[0] ?? '';

  return (
    <Screen
      tabBar
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      title={d?.company.name ?? 'Apna Invoice'}
      subtitle={`${greeting()}, ${firstName} ji`}
      right={
        <View style={{ flexDirection: 'row', gap: 8 }}>
          <Button kind="glass" size="md" icon="search" accessibilityLabel="Search invoices" onPress={() => router.push('/invoices?focus=search')} />
          <Button kind="accent" size="md" icon="add" accessibilityLabel="New invoice" onPress={() => router.push('/invoices/new')} />
        </View>
      }
      headerExtra={
        d ? (
          <View style={styles.hero}>
            <View style={{ flex: 1, gap: 2 }}>
              <AppText variant="small" color={colors.onDarkMuted} style={{ fontSize: 13 }}>
                Outstanding receivables
              </AppText>
              <AppText variant="hero" color={colors.onDark} adjustsFontSizeToFit numberOfLines={1}>
                {money(d.kpis.outstanding)}
              </AppText>
              {d.kpis.overdue > 0 ? (
                <AppText style={{ fontFamily: fonts.semibold, fontSize: 13 }} color="#FCA5A5">
                  {money(d.kpis.overdue)} overdue
                </AppText>
              ) : (
                <AppText variant="small" color={colors.onDarkMuted}>
                  Nothing overdue
                </AppText>
              )}
            </View>
            <Button label="Collect" kind="accent" size="sm" onPress={() => router.push('/invoices?status=outstanding')} />
          </View>
        ) : null
      }
    >
      {q.isLoading ? (
        <Loading />
      ) : q.error ? (
        <ErrorState message={(q.error as Error).message} onRetry={() => q.refetch()} />
      ) : d ? (
        <>
          <View style={styles.kpis}>
            <Kpi label="Sales this month" value={money(d.kpis.revenue_this_month)} />
            <Kpi label="Sales this FY" value={money(d.kpis.revenue_fy)} />
            <Kpi label="Customers" value={String(d.counts.customers)} />
          </View>

          <Card>
            <SectionTitle title="Needs attention" />
            {d.kpis.overdue > 0 ? (
              <Row
                title="Overdue invoices"
                subtitle={`${money(d.kpis.overdue)} past the due date`}
                leading={<IconTile icon="alert-circle" bg={colors.redTint} fg={colors.redText} size={36} />}
                trailing={<AppText variant="label" color={colors.tealDark}>View</AppText>}
                onPress={() => router.push('/invoices?status=outstanding')}
              />
            ) : null}
            {d.counts.drafts > 0 ? (
              <Row
                title={`${d.counts.drafts} draft${d.counts.drafts > 1 ? 's' : ''} to issue`}
                subtitle="Issue them to get an invoice number"
                leading={<IconTile icon="create-outline" bg={colors.slateTint} fg={colors.navy} size={36} />}
                trailing={<AppText variant="label" color={colors.tealDark}>Open</AppText>}
                onPress={() => router.push('/invoices?status=draft')}
              />
            ) : null}
            {d.counts.quotations_awaiting > 0 ? (
              <Row
                divider={false}
                title={`${d.counts.quotations_awaiting} quotation${d.counts.quotations_awaiting > 1 ? 's' : ''} awaiting reply`}
                subtitle="Follow up or mark accepted"
                leading={<IconTile icon="chatbox-ellipses-outline" bg={colors.amberTint} fg={colors.amberText} size={36} />}
                trailing={<AppText variant="label" color={colors.tealDark}>Open</AppText>}
                onPress={() => router.push('/quotations')}
              />
            ) : null}
            {d.kpis.overdue <= 0 && d.counts.drafts === 0 && d.counts.quotations_awaiting === 0 ? (
              <AppText variant="small" color={colors.textMuted}>
                All clear. Nothing needs you right now.
              </AppText>
            ) : null}
          </Card>

          <SectionTitle title="Recent invoices" action="View all" onAction={() => router.push('/invoices')} />
          <Card style={{ paddingVertical: 4 }}>
            {d.recent_invoices.length ? (
              d.recent_invoices.map((inv, i) => <InvoiceRow key={inv.id} inv={inv} divider={i < d.recent_invoices.length - 1} />)
            ) : (
              <View style={{ paddingVertical: 20, gap: 12, alignItems: 'center' }}>
                <AppText variant="h3">Make your first invoice</AppText>
                <Button label="New invoice" icon="add" size="md" onPress={() => router.push('/invoices/new')} />
              </View>
            )}
          </Card>
        </>
      ) : null}
    </Screen>
  );
}

function Kpi({ label, value }: { label: string; value: string }) {
  return (
    <View style={[styles.kpi, shadow.card]}>
      <AppText variant="small" color={colors.textMuted} numberOfLines={1}>
        {label}
      </AppText>
      <AppText style={{ fontFamily: fonts.displayHeavy, fontSize: 16 }} adjustsFontSizeToFit numberOfLines={1}>
        {value}
      </AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  hero: { flexDirection: 'row', alignItems: 'flex-end', gap: 12, paddingBottom: 26 },
  kpis: { flexDirection: 'row', gap: 8, marginTop: -40 },
  kpi: { flex: 1, backgroundColor: colors.surface, borderRadius: 18, padding: 12, gap: 2 },
});
