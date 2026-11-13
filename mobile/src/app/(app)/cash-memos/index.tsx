import { Href, router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { MEMO_MODES } from '@/api/cash-memos';
import { AppText } from '@/components/app-text';
import { Avatar, Chips, EmptyState, Row } from '@/components/blocks';
import { PagedList } from '@/components/paged-list';
import { Screen } from '@/components/screen';
import { SearchBar } from '@/components/search-bar';
import { Pill } from '@/components/status-pill';
import { useCashMemos } from '@/hooks/cash-memos';
import { EXPENSE_PERIODS, ExpensePeriod, expensePeriodRange, isDate } from '@/screens/finance/periods';
import { colors } from '@/theme';
import { date, money } from '@/utils/format';

/** Cash memos, newest first. Accepts ?from=&to=. */
export default function CashMemos() {
  const params = useLocalSearchParams<{ from?: string; to?: string }>();
  const hasCustom = isDate(params.from) || isDate(params.to);
  const [period, setPeriod] = useState<ExpensePeriod>(hasCustom ? 'custom' : 'all');
  const [search, setSearch] = useState('');
  const custom = { from: isDate(params.from) ? params.from : undefined, to: isDate(params.to) ? params.to : undefined };
  const range = expensePeriodRange(period, custom);
  const q = useCashMemos(search, range.from, range.to);

  const options = [
    { value: 'all' as ExpensePeriod, label: 'All' },
    ...EXPENSE_PERIODS.filter((p) => p.value !== 'all'),
    ...(hasCustom ? [{ value: 'custom' as ExpensePeriod, label: `${date(custom.from, true) || '…'} – ${date(custom.to, true) || 'today'}` }] : []),
  ];

  return (
    <Screen
      scroll={false}
      nav="back"
      title="Cash memos"
      headerExtra={<SearchBar placeholder="Search memo no. or seller" onSearch={setSearch} />}
      collapsible={<Chips value={period} options={options} onChange={setPeriod} />}
    >
      <PagedList
        query={q}
        tabBar={false}
        renderItem={(m) => (
          <View style={{ backgroundColor: colors.surface, borderRadius: 18, paddingHorizontal: 14 }}>
            <Row
              divider={false}
              onPress={() => router.push(`/cash-memos/${m.id}` as Href)}
              leading={<Avatar name={m.seller_name} tone="amber" />}
              title={m.seller_name}
              subtitle={`${m.memo_number} · ${date(m.memo_date, true)}`}
              trailing={
                <View style={{ alignItems: 'flex-end', gap: 5 }}>
                  <AppText variant="money">{money(m.grand_total)}</AppText>
                  <Pill label={MEMO_MODES.find((x) => x.value === m.payment_mode)?.label ?? m.payment_mode} tone="draft" />
                </View>
              }
            />
          </View>
        )}
        empty={
          <EmptyState
            icon="receipt-outline"
            title={search ? 'No cash memos match' : 'No cash memos yet'}
            body="Make a cash memo for small buys where the seller gave no bill. It also adds the expense."
            action="New cash memo"
            onAction={() => router.push('/cash-memos/new' as Href)}
          />
        }
      />
    </Screen>
  );
}
