import { LinearGradient } from 'expo-linear-gradient';
import { Href, router } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, { FadeInDown } from 'react-native-reanimated';

import type { Dashboard } from '@/api/types';
import { useAuth } from '@/auth/auth-context';
import { AppText } from '@/components/app-text';
import { Avatar, Card, ErrorState, IconTile, Loading, Row, SectionTitle, Segmented } from '@/components/blocks';
import { Button } from '@/components/button';
import { BarChart, Progress, RowBars, StackedBar } from '@/components/charts';
import { Icon, IconName } from '@/components/icon';
import { InvoiceRow } from '@/components/invoice-row';
import { PressableScale } from '@/components/pressable-scale';
import { BarAction, Screen } from '@/components/screen';
import { useDashboard } from '@/hooks/queries';
import { hrefFor } from '@/navigation/targets';
import { colors, fonts, gradient, shadow } from '@/theme';
import { date, daysFromToday, greeting, money, moneyShort } from '@/utils/format';

const STATUS: Record<string, { label: string; color: string; filter: string }> = {
  paid: { label: 'Paid', color: colors.green, filter: 'paid' },
  partially_paid: { label: 'Part paid', color: colors.teal, filter: 'partially_paid' },
  unpaid: { label: 'Unpaid', color: colors.amber, filter: 'outstanding' },
  overdue: { label: 'Overdue', color: colors.red, filter: 'outstanding' },
  draft: { label: 'Draft', color: '#94A3B8', filter: 'draft' },
};

const TONE: Record<string, { bg: string; fg: string }> = {
  danger: { bg: colors.redTint, fg: colors.redText },
  accent: { bg: colors.amberTint, fg: colors.amberText },
  info: { bg: colors.tealTint, fg: colors.teal },
};

const ACTION_ICON: Record<string, IconName> = { gst: 'receipt', invoice: 'document-text', overdue: 'alert-circle', draft: 'create', quote: 'chatbox-ellipses', stock: 'cube' };

/** Staggered entrance for each dashboard block. */
function Block({ i, children }: { i: number; children: React.ReactNode }) {
  return <Animated.View entering={FadeInDown.delay(60 + i * 70).springify().damping(18)}>{children}</Animated.View>;
}

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
        <>
          <BarAction icon="search" label="Search" onPress={() => router.push('/search' as Href)} />
          <BarAction icon="notifications-outline" label="Notifications" badge={!!d?.action_items?.length} onPress={() => router.push('/notifications' as Href)} />
        </>
      }
    >
      {q.isLoading ? (
        <Loading />
      ) : q.error ? (
        <ErrorState message={(q.error as Error).message} onRetry={() => q.refetch()} />
      ) : d ? (
        <Body d={d} />
      ) : null}
    </Screen>
  );
}

function Body({ d }: { d: Dashboard }) {
  const k = d.kpis;
  const billedMonth = k.revenue_this_month;
  const collectedMonth = k.collected_this_month ?? 0;

  return (
    <>
      <Block i={0}>
        <LinearGradient colors={gradient.hero} locations={gradient.heroLocations} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={[styles.hero, shadow.card]}>
          <View style={styles.glow} />
          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
            <AppText variant="label" color={colors.onDarkMuted}>
              To collect
            </AppText>
            {k.overdue > 0 ? (
              <View style={styles.overdue}>
                <Icon name="alert-circle" size={13} color="#FECACA" />
                <AppText style={{ fontFamily: fonts.semibold, fontSize: 12 }} color="#FECACA">
                  {moneyShort(k.overdue)} overdue
                </AppText>
              </View>
            ) : (
              <AppText variant="small" color={colors.onDarkMuted}>
                Nothing overdue
              </AppText>
            )}
          </View>
          <AppText variant="hero" color={colors.onDark} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.5} accessibilityLabel={`To collect ${money(k.outstanding)}`}>
            {money(k.outstanding, { paise: false })}
          </AppText>
          <View style={{ gap: 6 }}>
            <Progress ratio={billedMonth ? collectedMonth / billedMonth : 0} />
            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <AppText variant="small" color={colors.onDarkMuted}>
                Collected {moneyShort(collectedMonth)} of {moneyShort(billedMonth)} billed this month
              </AppText>
            </View>
          </View>
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <Button label="Collect now" kind="accent" size="sm" icon="cash-outline" onPress={() => router.push('/invoices?status=outstanding')} />
            <Button label="Ledger" kind="glass" size="sm" icon="people-outline" onPress={() => router.push('/parties')} />
          </View>
        </LinearGradient>
      </Block>

      <Block i={1}>
        <View style={styles.tiles}>
          <Tile icon="trending-up" tint={colors.tealTint} fg={colors.teal} label="Sales this month" value={k.revenue_this_month} />
          <Tile icon="wallet" tint={colors.greenTint} fg={colors.greenText} label="Collected this month" value={k.collected_this_month ?? 0} />
          <Tile icon="cart" tint={colors.redTint} fg={colors.redText} label="Expenses this month" value={k.expenses_this_month ?? 0} />
          <Tile icon="calendar" tint={colors.amberTint} fg={colors.amberText} label="Sales this FY" value={k.revenue_fy} />
        </View>
      </Block>

      {d.action_items?.length ? (
        <Block i={2}>
          <Card>
            <SectionTitle title="Needs attention" />
            {d.action_items.slice(0, 4).map((a, i, all) => (
              <Row
                key={a.title}
                divider={i < all.length - 1}
                title={a.title}
                subtitle={a.sub}
                leading={<IconTile icon={ACTION_ICON[a.icon] ?? 'alert-circle'} bg={TONE[a.tone]?.bg} fg={TONE[a.tone]?.fg} size={36} />}
                trailing={
                  <AppText variant="label" color={colors.tealDark}>
                    {a.cta}
                  </AppText>
                }
                onPress={() => router.push(hrefFor(a.target))}
              />
            ))}
          </Card>
        </Block>
      ) : (
        <Attention d={d} />
      )}

      {d.sales ? (
        <Block i={3}>
          <SalesCard sales={d.sales} />
        </Block>
      ) : null}

      {d.status_breakdown?.length ? (
        <Block i={4}>
          <Card>
            <SectionTitle title="Invoices this year" action="All" onAction={() => router.push('/invoices')} />
            <StackedBar
              showCount
              slices={d.status_breakdown
                .filter((s) => STATUS[s.status])
                .map((s) => ({ key: s.status, label: STATUS[s.status]!.label, value: s.amount, count: s.count, color: STATUS[s.status]!.color }))}
              onPress={(s) => router.push(`/invoices?status=${STATUS[s.key]!.filter}` as Href)}
            />
          </Card>
        </Block>
      ) : null}

      {d.aging?.some((a) => a.amount > 0) ? (
        <Block i={5}>
          <Card>
            <SectionTitle title="Who owes, by age" action="Collect" onAction={() => router.push('/invoices?status=outstanding')} />
            <RowBars
              rows={d.aging.map((a, i) => ({ key: a.bucket, label: a.label, hint: `${a.count} inv`, value: a.amount, color: [colors.teal, colors.amber, '#F97316', colors.red][i] }))}
            />
          </Card>
        </Block>
      ) : null}

      {d.top_debtors?.length ? (
        <Block i={6}>
          <Card>
            <SectionTitle title="Top dues" />
            {d.top_debtors.map((c, i) => {
              const late = daysFromToday(c.oldest_due_date);
              return (
                <Row
                  key={c.customer_id}
                  divider={i < d.top_debtors!.length - 1}
                  title={c.name}
                  subtitle={late != null && late < 0 ? `${-late} days late` : c.oldest_due_date ? `Due ${date(c.oldest_due_date, true)}` : undefined}
                  leading={<Avatar name={c.name} />}
                  trailing={<AppText variant="money">{moneyShort(c.amount)}</AppText>}
                  onPress={() => router.push(`/parties/${c.customer_id}`)}
                />
              );
            })}
          </Card>
        </Block>
      ) : null}

      {d.gst ? (
        <Block i={7}>
          <GstCard gst={d.gst} />
        </Block>
      ) : null}

      {d.spend_by_category?.length ? (
        <Block i={8}>
          <Card>
            <SectionTitle title="Where money went · 12 months" action="Expenses" onAction={() => router.push('/expenses' as Href)} />
            <StackedBar slices={d.spend_by_category.map((s) => ({ key: s.key, label: s.label, value: s.amount, color: s.color }))} />
          </Card>
        </Block>
      ) : null}

      <Block i={9}>
        <SectionTitle title="Recent invoices" action="View all" onAction={() => router.push('/invoices')} />
        <Card style={{ paddingVertical: 4, marginTop: 8 }}>
          {d.recent_invoices.length ? (
            d.recent_invoices.map((inv, i) => <InvoiceRow key={inv.id} inv={inv} divider={i < d.recent_invoices.length - 1} />)
          ) : (
            <View style={{ paddingVertical: 20, gap: 8, alignItems: 'center' }}>
              <AppText variant="h3">Make your first invoice</AppText>
              <AppText variant="small" color={colors.textMuted}>
                Tap the + button below to start.
              </AppText>
            </View>
          )}
        </Card>
      </Block>
    </>
  );
}

function Tile({ icon, tint, fg, label, value }: { icon: IconName; tint: string; fg: string; label: string; value: number }) {
  return (
    <View style={[styles.tile, shadow.soft]} accessible accessibilityLabel={`${label} ${money(value)}`}>
      <IconTile icon={icon} bg={tint} fg={fg} size={34} />
      <AppText style={{ fontFamily: fonts.displayHeavy, fontSize: 20 }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.6}>
        {moneyShort(value)}
      </AppText>
      <AppText variant="small" color={colors.textMuted} numberOfLines={1}>
        {label}
      </AppText>
    </View>
  );
}

function SalesCard({ sales }: { sales: NonNullable<Dashboard['sales']> }) {
  const [view, setView] = useState<'monthly' | 'yearly'>('monthly');
  const rows = sales[view];
  return (
    <Card>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
        <AppText variant="h2" style={{ flex: 1 }}>
          Sales & collection
        </AppText>
        <View style={{ width: 150 }}>
          <Segmented
            value={view}
            onChange={setView}
            options={[
              { value: 'monthly', label: '6 mo' },
              { value: 'yearly', label: 'FY' },
            ]}
          />
        </View>
      </View>
      <BarChart
        key={view}
        data={rows.map((r) => ({ label: r.label, full: r.full, values: { billed: r.billed, collected: r.collected } }))}
        series={[
          { key: 'billed', label: 'Billed', color: colors.teal },
          { key: 'collected', label: 'Collected', color: colors.amber },
        ]}
      />
    </Card>
  );
}

function GstCard({ gst }: { gst: NonNullable<Dashboard['gst']> }) {
  const items = [
    { key: 'gstr1', title: 'GSTR-1', due: gst.gstr1_due },
    { key: 'gstr3b', title: 'GSTR-3B', due: gst.gstr3b_due },
  ];
  return (
    <Card>
      <SectionTitle title={`GST · ${gst.label}`} action="Returns" onAction={() => router.push('/gst' as Href)} />
      <View style={{ flexDirection: 'row', gap: 10 }}>
        {items.map((it) => {
          const days = daysFromToday(it.due);
          const late = days != null && days < 0;
          return (
            <PressableScale key={it.key} style={[styles.gst, late && { backgroundColor: colors.redTint }]} onPress={() => router.push(hrefFor({ screen: it.key as 'gstr1' | 'gstr3b', params: gst.month_query ?? {} }))}>
              <AppText variant="label" color={colors.textMuted}>
                {it.title}
              </AppText>
              <AppText style={{ fontFamily: fonts.displayHeavy, fontSize: 18 }} color={late ? colors.redText : colors.text}>
                {date(it.due, true)}
              </AppText>
              <AppText variant="small" color={late ? colors.redText : colors.textMuted}>
                {days == null ? '' : days === 0 ? 'Due today' : late ? `${-days} days ago` : `in ${days} days`}
              </AppText>
            </PressableScale>
          );
        })}
      </View>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
        <AppText variant="small" color={colors.textMuted}>
          {gst.invoices} invoices · tax to pay in cash
        </AppText>
        <AppText variant="money">{money(gst.cash)}</AppText>
      </View>
    </Card>
  );
}

/** Fallback when the server sends no action items (older API). */
function Attention({ d }: { d: Dashboard }) {
  const rows = [
    d.kpis.overdue > 0 && { t: 'Overdue invoices', s: `${money(d.kpis.overdue)} past the due date`, icon: 'alert-circle' as IconName, tone: 'danger', href: '/invoices?status=outstanding' },
    d.counts.drafts > 0 && { t: `${d.counts.drafts} draft${d.counts.drafts > 1 ? 's' : ''} to issue`, s: 'Issue them to get an invoice number', icon: 'create' as IconName, tone: 'info', href: '/invoices?status=draft' },
    d.counts.quotations_awaiting > 0 && { t: `${d.counts.quotations_awaiting} quotation${d.counts.quotations_awaiting > 1 ? 's' : ''} awaiting reply`, s: 'Follow up or mark accepted', icon: 'chatbox-ellipses' as IconName, tone: 'accent', href: '/quotations' },
  ].filter(Boolean) as { t: string; s: string; icon: IconName; tone: string; href: string }[];
  if (!rows.length) return null;
  return (
    <Block i={2}>
      <Card>
        <SectionTitle title="Needs attention" />
        {rows.map((r, i) => (
          <Row key={r.t} divider={i < rows.length - 1} title={r.t} subtitle={r.s} leading={<IconTile icon={r.icon} bg={TONE[r.tone]!.bg} fg={TONE[r.tone]!.fg} size={36} />} onPress={() => router.push(r.href as Href)} />
        ))}
      </Card>
    </Block>
  );
}

const styles = StyleSheet.create({
  hero: { borderRadius: 26, padding: 20, gap: 12, overflow: 'hidden' },
  glow: { position: 'absolute', width: 220, height: 220, borderRadius: 110, right: -70, top: -90, backgroundColor: 'rgba(112,213,225,0.18)' },
  overdue: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: 'rgba(220,38,38,0.28)', paddingHorizontal: 9, paddingVertical: 4, borderRadius: 99 },
  tiles: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  tile: { flexBasis: '47%', flexGrow: 1, backgroundColor: colors.surface, borderRadius: 20, padding: 14, gap: 6 },
  gst: { flex: 1, backgroundColor: colors.ground, borderRadius: 16, padding: 12, gap: 2 },
});
