import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { View } from 'react-native';

import { Avatar, EmptyState, Row, Segmented } from '@/components/blocks';
import { Icon } from '@/components/icon';
import { PagedList } from '@/components/paged-list';
import { Screen } from '@/components/screen';
import { SearchBar } from '@/components/search-bar';
import { LINKS } from '@/config';
import { useCustomers } from '@/hooks/queries';
import { colors } from '@/theme';

export default function Parties() {
  const [tab, setTab] = useState<'customers' | 'vendors'>('customers');
  const [search, setSearch] = useState('');
  const q = useCustomers(search);

  return (
    <Screen
      scroll={false}
      title="Parties"
      headerExtra={
        <Segmented
            value={tab}
            onChange={setTab}
            options={[
              { value: 'customers', label: 'Customers' },
              { value: 'vendors', label: 'Vendors' },
          ]}
        />
      }
      collapsible={tab === 'customers' ? <SearchBar placeholder="Name, phone or GSTIN" onSearch={setSearch} /> : undefined}
    >
      {tab === 'customers' ? (
        <PagedList
          query={q}
          renderItem={(c) => (
            <View style={{ backgroundColor: colors.surface, borderRadius: 18, paddingHorizontal: 14 }}>
              <Row
                divider={false}
                onPress={() => router.push(`/parties/${c.id}`)}
                leading={<Avatar name={c.name} tone="dark" />}
                title={c.name}
                subtitle={[c.state?.name, c.phone, c.gstin].filter(Boolean).join(' · ')}
                trailing={<Icon name="chevron-forward" size={18} color={colors.textMuted} />}
              />
            </View>
          )}
          empty={<EmptyState icon="people-outline" title={search ? 'No customers match' : 'No customers yet'} body="Add a customer while making an invoice." />}
        />
      ) : (
        <EmptyState
          icon="storefront-outline"
          title="Vendors are on the web for now"
          body="Purchase invoices and vendors come to the app soon. Until then, manage them on apnainvoice.com."
          action="Open vendors on the web"
          onAction={() => WebBrowser.openBrowserAsync(`${LINKS.web}/vendors`)}
        />
      )}
    </Screen>
  );
}
