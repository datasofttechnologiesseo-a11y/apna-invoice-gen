import { FlashList } from '@shopify/flash-list';
import type { InfiniteData, UseInfiniteQueryResult } from '@tanstack/react-query';
import { ReactElement } from 'react';
import { ActivityIndicator, RefreshControl, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import type { Paginated } from '@/api/types';
import { colors, TAB_BAR_HEIGHT } from '@/theme';

import { ErrorState, Loading } from './blocks';

/**
 * Infinite, pull-to-refresh list for any paginated endpoint. Every list screen
 * (invoices, quotations, parties…) uses this, so paging/refresh/error states
 * behave the same everywhere.
 */
export function PagedList<T extends { id: number }>({
  query,
  renderItem,
  empty,
  tabBar = true,
}: {
  query: UseInfiniteQueryResult<InfiniteData<Paginated<T>>>;
  renderItem: (item: T) => ReactElement;
  empty: ReactElement;
  tabBar?: boolean;
}) {
  const insets = useSafeAreaInsets();
  if (query.isLoading) return <Loading />;
  if (query.error) return <ErrorState message={(query.error as Error).message} onRetry={() => query.refetch()} />;
  const items = query.data?.pages.flatMap((p) => p.data) ?? [];
  return (
    <FlashList
      data={items}
      keyExtractor={(i) => String(i.id)}
      renderItem={({ item }) => <View style={{ paddingHorizontal: 16, paddingBottom: 8 }}>{renderItem(item)}</View>}
      contentContainerStyle={{ paddingTop: 14, paddingBottom: (tabBar ? TAB_BAR_HEIGHT : 0) + insets.bottom + 24 }}
      onEndReached={() => query.hasNextPage && !query.isFetchingNextPage && query.fetchNextPage()}
      onEndReachedThreshold={0.4}
      refreshControl={<RefreshControl refreshing={query.isRefetching && !query.isFetchingNextPage} onRefresh={() => void query.refetch()} tintColor={colors.teal} />}
      ListEmptyComponent={empty}
      ListFooterComponent={query.isFetchingNextPage ? <ActivityIndicator color={colors.teal} style={{ margin: 16 }} /> : null}
      keyboardDismissMode="on-drag"
      showsVerticalScrollIndicator
    />
  );
}
