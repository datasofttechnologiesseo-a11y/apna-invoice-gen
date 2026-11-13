import { router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { AppText } from '@/components/app-text';
import { Avatar, Chips, EmptyState, Row } from '@/components/blocks';
import { PagedList } from '@/components/paged-list';
import { Screen } from '@/components/screen';
import { SearchBar } from '@/components/search-bar';
import { QuotePill } from '@/components/status-pill';
import { useQuotations } from '@/hooks/queries';
import { colors } from '@/theme';
import { date, money } from '@/utils/format';

const FILTERS = [
  { value: 'all', label: 'All' },
  { value: 'draft', label: 'Draft' },
  { value: 'sent', label: 'Sent' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'converted', label: 'Converted' },
  { value: 'declined', label: 'Declined' },
];

export default function Quotations() {
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const q = useQuotations(status, search);

  return (
    <Screen
      scroll={false}
      title="Quotations"
      headerExtra={
        <View style={{ gap: 12 }}>
          <SearchBar placeholder="Search customer or quote no." onSearch={setSearch} />
          <Chips dark value={status} options={FILTERS} onChange={setStatus} />
        </View>
      }
    >
      <PagedList
        query={q}
        renderItem={(qt) => (
          <View style={{ backgroundColor: colors.surface, borderRadius: 18, paddingHorizontal: 14 }}>
            <Row
              divider={false}
              onPress={() => router.push(`/quotations/${qt.id}`)}
              leading={<Avatar name={qt.customer_name} />}
              title={qt.customer_name}
              subtitle={`${qt.display_number} · ${date(qt.quote_date, true)}${qt.valid_until ? ` · valid till ${date(qt.valid_until, true)}` : ''}`}
              trailing={
                <View style={{ alignItems: 'flex-end', gap: 5 }}>
                  <AppText variant="money">{money(qt.grand_total)}</AppText>
                  <QuotePill status={qt.effective_status} />
                </View>
              }
            />
          </View>
        )}
        empty={<EmptyState icon="chatbox-ellipses-outline" title="No quotations yet" body="Quotations you create on the web or here show up in this list." />}
      />
    </Screen>
  );
}
