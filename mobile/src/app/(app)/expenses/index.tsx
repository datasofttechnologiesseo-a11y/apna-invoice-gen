import { Href, router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Alert, View } from 'react-native';

import { expensesApi } from '@/api/expenses';
import { AppText } from '@/components/app-text';
import { Chips, EmptyState, Row } from '@/components/blocks';
import { PagedList } from '@/components/paged-list';
import { BarAction, Screen } from '@/components/screen';
import { SearchBar } from '@/components/search-bar';
import { Pill } from '@/components/status-pill';
import { useExpenses } from '@/hooks/expenses';
import { EXPENSE_PERIODS, ExpensePeriod, expensePeriodRange, isDate } from '@/screens/finance/periods';
import { StatStrip } from '@/screens/finance/stat-strip';
import { colors } from '@/theme';
import { date, money, today } from '@/utils/format';
import { downloadAndShare } from '@/utils/share';

/** Expenses for a period, by category. Accepts ?category=&from=&to= (dashboard spend bars link here). */
export default function Expenses() {
  const params = useLocalSearchParams<{ category?: string; from?: string; to?: string; period?: string }>();
  const hasCustom = isDate(params.from) || isDate(params.to);
  const [period, setPeriod] = useState<ExpensePeriod>(hasCustom ? 'custom' : ((EXPENSE_PERIODS.find((p) => p.value === params.period)?.value ?? 'this_month') as ExpensePeriod));
  const [category, setCategory] = useState(params.category || 'all');
  const [search, setSearch] = useState('');

  const custom = { from: isDate(params.from) ? params.from : undefined, to: isDate(params.to) ? params.to : undefined };
  const range = expensePeriodRange(period, custom);
  const filter = { ...range, category: category === 'all' ? undefined : category, search: search || undefined };
  const q = useExpenses(filter);

  const meta = q.data?.pages[0]?.meta;
  const periodOptions = hasCustom ? [{ value: 'custom' as ExpensePeriod, label: `${date(custom.from, true) || '…'} – ${date(custom.to, true) || 'today'}` }, ...EXPENSE_PERIODS] : EXPENSE_PERIODS;
  const categoryOptions = [{ value: 'all', label: 'All categories' }, ...(meta?.categories ?? []).map((c) => ({ value: c.value, label: c.label }))];
  if (category !== 'all' && !categoryOptions.some((c) => c.value === category)) categoryOptions.push({ value: category, label: category });

  const exportReport = () =>
    Alert.alert('Export expenses', 'Share this list with your CA.', [
      { text: 'CSV (Excel)', onPress: () => downloadAndShare(expensesApi.csvPath(filter, today()), `expenses-${range.from ?? 'all'}.csv`, 'text/csv') },
      { text: 'PDF', onPress: () => downloadAndShare(expensesApi.pdfPath(filter, today()), `expenses-${range.from ?? 'all'}.pdf`) },
      { text: 'Cancel', style: 'cancel' },
    ]);

  const newHref = (category === 'all' ? '/expenses/new' : `/expenses/new?category=${encodeURIComponent(category)}`) as Href;

  return (
    <Screen
      scroll={false}
      nav="back"
      title="Expenses"
      right={<BarAction icon="share-outline" label="Export expenses" onPress={exportReport} />}
      headerExtra={<SearchBar placeholder="Search paid to or description" onSearch={setSearch} />}
      collapsible={
        <View style={{ gap: 10 }}>
          <Chips value={period} options={periodOptions} onChange={setPeriod} />
          <Chips value={category} options={categoryOptions} onChange={setCategory} />
          {meta ? (
            <StatStrip
              stats={[
                { label: 'Spent', value: meta.totals.amount },
                { label: 'GST', value: meta.totals.gst, tone: 'accent' },
                { label: 'Cash out', value: meta.totals.outflow, tone: 'bad' },
                { label: 'Entries', value: meta.totals.count, count: true },
              ]}
            />
          ) : null}
        </View>
      }
    >
      <PagedList
        query={q}
        tabBar={false}
        renderItem={(e) => (
          <View style={{ backgroundColor: colors.surface, borderRadius: 18, paddingHorizontal: 14 }}>
            <Row
              divider={false}
              onPress={() => router.push(`/expenses/${e.id}` as Href)}
              leading={<View style={{ width: 12, height: 40, borderRadius: 6, backgroundColor: e.category_color }} />}
              title={e.vendor_name || e.description}
              subtitle={`${e.category_label} · ${date(e.entry_date, true)}${e.vendor_name ? ` · ${e.description}` : ''}`}
              trailing={
                <View style={{ alignItems: 'flex-end', gap: 5 }}>
                  <AppText variant="money">{money(e.total)}</AppText>
                  {e.cash_memo_id ? <Pill label="Cash memo" tone="due" /> : e.gst_amount > 0 ? <Pill label={`GST ${money(e.gst_amount)}`} tone="info" /> : null}
                </View>
              }
            />
          </View>
        )}
        empty={
          <EmptyState
            icon="wallet-outline"
            title={search || category !== 'all' ? 'No expenses match' : 'No expenses in this period'}
            body="Record rent, salaries, fuel and bills so your profit and GST credit are right."
            action="Add expense"
            onAction={() => router.push(newHref)}
          />
        }
      />
    </Screen>
  );
}
