import React from 'react';
import { ActivityIndicator, FlatList, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { getActivity } from '../api/endpoints';
import { apiErrorMessage } from '../api/client';
import { Card, EmptyState } from '../components/ui';
import { colors } from '../theme';

function formatWhen(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleString();
}

export default function ActivityScreen() {
  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['activity'],
    queryFn: getActivity,
  });

  if (isLoading) {
    return <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />;
  }
  if (isError) {
    return <Text style={styles.error}>{apiErrorMessage(error)}</Text>;
  }

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: colors.bg }}
      data={data}
      keyExtractor={(l) => String(l.id)}
      refreshing={isRefetching}
      onRefresh={refetch}
      contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
      ListEmptyComponent={<EmptyState title="No activity yet" subtitle="Actions you take will show up here." />}
      renderItem={({ item }) => (
        <Card style={styles.row}>
          <View style={styles.dot} />
          <View style={{ flex: 1 }}>
            <Text style={styles.desc}>{item.description}</Text>
            <Text style={styles.meta}>
              {item.user_name ? `${item.user_name} · ` : ''}
              {formatWhen(item.created_at)}
            </Text>
          </View>
        </Card>
      )}
    />
  );
}

const styles = StyleSheet.create({
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  row: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 8 },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.primary, marginTop: 6, marginRight: 12 },
  desc: { fontSize: 14, color: colors.text, lineHeight: 19 },
  meta: { fontSize: 12, color: colors.muted, marginTop: 4 },
});
