import { router, useLocalSearchParams, type Href } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';

import type { PurchaseFilter } from '@/api/purchases';
import { AppText } from '@/components/app-text';
import { Chips, EmptyState } from '@/components/blocks';
import { Icon } from '@/components/icon';
import { PagedList } from '@/components/paged-list';
import { Screen } from '@/components/screen';
import { SearchBar } from '@/components/search-bar';
import { usePurchases } from '@/hooks/purchases';
import { StatTile } from '@/screens/purchases/parts';
import { PurchaseRow, rowCard } from '@/screens/purchases/purchase-row';
import { colors, fonts } from '@/theme';
import { date, money, moneyShort } from '@/utils/format';

const FILTERS: { value: PurchaseFilter; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'unpaid', label: 'To pay' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'paid', label: 'Paid' },
];

const isFilter = (s?: string): s is PurchaseFilter => !!s && FILTERS.some((f) => f.value === s);

/** Purchase invoices with the payables strip. Accepts ?status=&from=&to= from the dashboard. */
export default function Purchases() {
  const params = useLocalSearchParams<{ status?: string; from?: string; to?: string; vendor?: string }>();
  const linked: PurchaseFilter = isFilter(params.status) ? params.status : 'all';
  // A chip picked here wins until a link brings a new ?status.
  const [picked, setPicked] = useState<{ from?: string; value: PurchaseFilter } | null>(null);
  const status = picked && picked.from === params.status ? picked.value : linked;
  const [range, setRange] = useState<{ key: string; on: boolean }>({ key: '', on: true });
  const rangeKey = `${params.from ?? ''}|${params.to ?? ''}`;
  const rangeOn = (params.from || params.to) && (range.key !== rangeKey || range.on);
  const [search, setSearch] = useState('');

  const q = usePurchases({
    status,
    search,
    from: rangeOn ? params.from : undefined,
    to: rangeOn ? params.to : undefined,
    vendor: params.vendor ? Number(params.vendor) : undefined,
  });
  const s = q.data?.pages[0]?.meta.summary;

  return (
    <Screen
      scroll={false}
      nav="back"
      title="Purchases"
      headerExtra={<SearchBar placeholder="Vendor, GSTIN or bill no." onSearch={setSearch} />}
      collapsible={
        <View style={{ gap: 10 }}>
          {s ? (
            <View style={{ flexDirection: 'row', gap: 8 }}>
              <StatTile label="To pay" value={moneyShort(s.payable)} full={money(s.payable)} />
              <StatTile label="Overdue" value={moneyShort(s.overdue)} full={money(s.overdue)} tone={s.overdue > 0 ? 'danger' : undefined} />
              <StatTile label="Due in 7 days" value={moneyShort(s.due_7_days)} full={money(s.due_7_days)} tone={s.due_7_days > 0 ? 'warn' : undefined} />
            </View>
          ) : null}
          <Chips value={status} options={FILTERS} onChange={(value) => setPicked({ from: params.status, value })} />
          {rangeOn ? (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Clear date filter"
              onPress={() => setRange({ key: rangeKey, on: false })}
              style={{ alignSelf: 'flex-start', flexDirection: 'row', alignItems: 'center', gap: 6, height: 32, paddingHorizontal: 12, borderRadius: 999, backgroundColor: colors.tealSoft }}
            >
              <AppText style={{ fontFamily: fonts.semibold, fontSize: 12 }} color={colors.tealDark}>
                {params.from ? date(params.from, true) : '…'} – {params.to ? date(params.to, true) : '…'}
              </AppText>
              <Icon name="close" size={14} color={colors.tealDark} />
            </Pressable>
          ) : null}
        </View>
      }
    >
      <PagedList
        query={q}
        tabBar={false}
        renderItem={(b) => (
          <View style={rowCard()}>
            <PurchaseRow bill={b} />
          </View>
        )}
        empty={
          <EmptyState
            icon="cart-outline"
            title={search || status !== 'all' ? 'No purchase invoices here' : 'No purchase invoices yet'}
            body={search ? 'Try a different vendor or bill number.' : 'Record the bills your suppliers give you to track payables and claim GST input credit.'}
            action={search ? undefined : 'New purchase bill'}
            onAction={() => router.push('/purchases/new' as Href)}
          />
        }
      />
    </Screen>
  );
}
