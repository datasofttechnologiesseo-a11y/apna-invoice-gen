import { router, type Href } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { AppText } from '@/components/app-text';
import { Avatar, EmptyState, Row } from '@/components/blocks';
import { PagedList } from '@/components/paged-list';
import { Screen } from '@/components/screen';
import { SearchBar } from '@/components/search-bar';
import { useVendors } from '@/hooks/purchases';
import { colors } from '@/theme';
import { money } from '@/utils/format';

/** Vendors (suppliers) with what is still to be paid to each. */
export default function Vendors() {
  const [search, setSearch] = useState('');
  const q = useVendors(search);

  return (
    <Screen scroll={false} nav="back" title="Vendors" headerExtra={<SearchBar placeholder="Name, phone or GSTIN" onSearch={setSearch} />}>
      <PagedList
        query={q}
        tabBar={false}
        renderItem={(v) => (
          <View style={{ backgroundColor: colors.surface, borderRadius: 18, paddingHorizontal: 14 }}>
            <Row
              divider={false}
              onPress={() => router.push(`/vendors/${v.id}` as Href)}
              leading={<Avatar name={v.name} tone="dark" />}
              title={v.name}
              subtitle={[v.gstin, v.city || v.state_name, v.phone].filter(Boolean).join(' · ') || 'No GSTIN'}
              accessibilityLabel={`${v.name}${v.payable ? `, ${money(v.payable)} to pay` : ''}`}
              trailing={
                v.payable ? (
                  <View style={{ alignItems: 'flex-end' }}>
                    <AppText variant="money">{money(v.payable)}</AppText>
                    <AppText variant="small" color={colors.textMuted}>to pay</AppText>
                  </View>
                ) : (
                  <AppText variant="small" color={colors.textMuted}>
                    {v.purchase_bills_count ? `${v.purchase_bills_count} bill${v.purchase_bills_count === 1 ? '' : 's'}` : ''}
                  </AppText>
                )
              }
            />
          </View>
        )}
        empty={
          <EmptyState
            icon="storefront-outline"
            title={search ? 'No vendors match' : 'No vendors yet'}
            body={search ? 'Try a different name, phone or GSTIN.' : 'Add the suppliers you buy from to record their bills and claim GST input credit.'}
            action={search ? undefined : 'New vendor'}
            onAction={() => router.push('/vendors/new' as Href)}
          />
        }
      />
    </Screen>
  );
}
