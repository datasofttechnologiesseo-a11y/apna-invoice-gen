import { Href, router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import type { ItemFilter } from '@/api/items';
import { AppText } from '@/components/app-text';
import { Chips, EmptyState, IconTile, Row } from '@/components/blocks';
import { PagedList } from '@/components/paged-list';
import { Screen } from '@/components/screen';
import { SearchBar } from '@/components/search-bar';
import { Pill } from '@/components/status-pill';
import { useItems } from '@/hooks/items';
import { colors } from '@/theme';
import { money } from '@/utils/format';

const FILTERS: { value: ItemFilter; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'goods', label: 'Goods' },
  { value: 'service', label: 'Services' },
  { value: 'low', label: 'Low stock' },
  { value: 'archived', label: 'Archived' },
];

const isFilter = (v?: string): v is ItemFilter => FILTERS.some((f) => f.value === v);

/** Items & services. Accepts ?status= (all | goods | service | archived) from links. */
export default function Items() {
  const params = useLocalSearchParams<{ status?: string; kind?: string; stock?: string }>();
  const start = params.stock === 'low' ? 'low' : (params.status ?? params.kind);
  const [filter, setFilter] = useState<ItemFilter>(isFilter(start) ? start : 'all');
  const [search, setSearch] = useState('');
  const q = useItems(filter, search);

  return (
    <Screen
      scroll={false}
      nav="back"
      title="Items"
      subtitle="Goods and services you bill"
      headerExtra={<SearchBar placeholder="Name, SKU or HSN" onSearch={setSearch} />}
      collapsible={<Chips value={filter} options={FILTERS} onChange={setFilter} />}
    >
      <PagedList
        query={q}
        tabBar={false}
        renderItem={(p) => {
          const low = p.track_stock && p.stock !== null && p.low_stock_level !== null && p.stock <= p.low_stock_level;
          return (
            <View style={{ backgroundColor: colors.surface, borderRadius: 18, paddingHorizontal: 14 }}>
              <Row
                divider={false}
                onPress={() => router.push(`/items/${p.id}` as Href)}
                accessibilityLabel={`${p.name}, ${money(p.rate)}, GST ${p.gst_rate} percent`}
                leading={<IconTile icon={p.kind === 'service' ? 'construct-outline' : 'cube-outline'} bg={p.kind === 'service' ? colors.slateTint : colors.greenTint} fg={p.kind === 'service' ? colors.slateText : colors.greenText} />}
                title={p.name}
                subtitle={[p.hsn_sac ? `HSN ${p.hsn_sac}` : null, `GST ${p.gst_rate}%`, p.stock !== null ? `${p.stock} ${p.unit} in stock` : p.unit].filter(Boolean).join(' · ')}
                trailing={
                  <View style={{ alignItems: 'flex-end', gap: 4 }}>
                    <AppText variant="money">{money(p.rate)}</AppText>
                    {!p.is_active ? <Pill label="Archived" tone="draft" /> : low ? <Pill label="Low stock" tone="due" /> : null}
                  </View>
                }
              />
            </View>
          );
        }}
        empty={
          search || filter !== 'all' ? (
            <EmptyState icon="search-outline" title="No items match" body={filter === 'archived' ? 'Items used on documents are archived when deleted. None are archived.' : 'Try another name, SKU or HSN code.'} />
          ) : (
            <EmptyState icon="cube-outline" title="No items yet" body="Save the goods and services you sell to fill invoices in a tap." action="Add an item" onAction={() => router.push('/items/new' as Href)} />
          )
        }
      />
    </Screen>
  );
}
