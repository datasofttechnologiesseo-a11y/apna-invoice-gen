import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import type { InvoiceFilter } from '@/api/endpoints';
import { Chips, EmptyState } from '@/components/blocks';
import { InvoiceRow } from '@/components/invoice-row';
import { PagedList } from '@/components/paged-list';
import { Screen } from '@/components/screen';
import { SearchBar } from '@/components/search-bar';
import { useInvoices } from '@/hooks/queries';
import { colors } from '@/theme';

const FILTERS: { value: InvoiceFilter; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'outstanding', label: 'Unpaid' },
  { value: 'draft', label: 'Draft' },
  { value: 'partially_paid', label: 'Part paid' },
  { value: 'paid', label: 'Paid' },
  { value: 'cancelled', label: 'Cancelled' },
];

export default function Invoices() {
  const params = useLocalSearchParams<{ status?: InvoiceFilter; focus?: string }>();
  // A filter picked here wins until a link (e.g. Home → "Collect") brings a new ?status.
  const [picked, setPicked] = useState<{ from?: string; value: InvoiceFilter } | null>(null);
  const status: InvoiceFilter = picked && picked.from === params.status ? picked.value : (params.status ?? 'all');
  const setStatus = (value: InvoiceFilter) => setPicked({ from: params.status, value });
  const [search, setSearch] = useState('');
  const q = useInvoices(status, search);

  return (
    <Screen
      scroll={false}
      title="Invoices"
      headerExtra={<SearchBar placeholder="Search customer or invoice no." onSearch={setSearch} autoFocus={params.focus === 'search'} />}
      collapsible={<Chips value={status} options={FILTERS} onChange={setStatus} />}
    >
      <PagedList
        query={q}
        renderItem={(inv) => (
          <View style={{ backgroundColor: colors.surface, borderRadius: 18, paddingHorizontal: 14 }}>
            <InvoiceRow inv={inv} divider={false} />
          </View>
        )}
        empty={
          <EmptyState
            icon="document-text-outline"
            title={search ? 'No invoices match' : 'No invoices here yet'}
            body={search ? 'Try a different name or number.' : 'Create a GST invoice in under a minute.'}
            action={search ? undefined : 'New invoice'}
            onAction={() => router.push('/invoices/new')}
          />
        }
      />
    </Screen>
  );
}
