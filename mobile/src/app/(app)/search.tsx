import { Href, router } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, View } from 'react-native';

import type { SearchItem, SearchScope } from '@/api/workspace';
import { AppText } from '@/components/app-text';
import { Avatar, Card, EmptyState, ErrorState, IconTile, Row, Segmented, SectionTitle } from '@/components/blocks';
import { Screen } from '@/components/screen';
import { SearchBar } from '@/components/search-bar';
import { Pill } from '@/components/status-pill';
import { useSearch } from '@/hooks/workspace';
import { colors } from '@/theme';

const SCOPES: { value: SearchScope; label: string }[] = [
  { value: 'invoices', label: 'Invoices' },
  { value: 'customers', label: 'Customers' },
];

/** Server tones (SearchSuggestions::statusTone) → app pill tones. */
const TONE = { paid: 'paid', danger: 'late', muted: 'draft', due: 'due' } as const;

/** App-wide search: the same suggestions as the web search box. Invoices open the invoice, customers their ledger. */
export default function Search() {
  const [q, setQ] = useState('');
  const [scope, setScope] = useState<SearchScope>('invoices');
  const res = useSearch(q, scope);
  const groups = (res.data?.groups ?? []).filter((g) => g.items.length);

  const open = (it: SearchItem) => router.push((it.type === 'invoice' ? `/invoices/${it.id}` : `/parties/${it.id}`) as Href);

  return (
    <Screen
      nav="back"
      title="Search"
      headerExtra={
        <View style={{ gap: 10 }}>
          <SearchBar autoFocus placeholder={scope === 'invoices' ? 'Invoice number, customer, amount' : 'Name, phone, GSTIN, email'} onSearch={setQ} />
          <Segmented value={scope} options={SCOPES} onChange={setScope} />
        </View>
      }
    >
      {!q ? (
        <EmptyState icon="search-outline" title={scope === 'invoices' ? 'Find an invoice' : 'Find a customer'} body={scope === 'invoices' ? 'Search by number, customer name or amount.' : 'Search by name, phone, email or GSTIN.'} />
      ) : res.isLoading ? (
        <ActivityIndicator color={colors.teal} style={{ marginTop: 32 }} />
      ) : res.error ? (
        <ErrorState message={(res.error as Error).message} onRetry={() => res.refetch()} />
      ) : !groups.length ? (
        <EmptyState icon="search-outline" title="Nothing found" body={`No ${scope} match “${q}”.`} />
      ) : (
        groups.map((g) => (
          <View key={g.label} style={{ gap: 8 }}>
            <SectionTitle title={g.label} />
            <Card style={{ paddingVertical: 2 }}>
              {g.items.map((it, i) => (
                <Row
                  key={`${it.type}${it.id}`}
                  divider={i < g.items.length - 1}
                  onPress={() => open(it)}
                  leading={it.type === 'customer' ? <Avatar name={it.title} size={38} /> : <IconTile icon="document-text-outline" size={38} />}
                  title={it.title}
                  subtitle={it.subtitle ?? undefined}
                  trailing={
                    <View style={{ alignItems: 'flex-end', gap: 4 }}>
                      {it.meta ? <AppText variant={it.type === 'invoice' ? 'money' : 'small'} color={it.type === 'invoice' ? colors.text : colors.textMuted}>{it.meta}</AppText> : null}
                      {it.badge ? <Pill label={it.badge} tone={TONE[(it.tone ?? 'due') as keyof typeof TONE] ?? 'due'} /> : null}
                    </View>
                  }
                />
              ))}
            </Card>
          </View>
        ))
      )}
    </Screen>
  );
}
