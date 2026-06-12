import React from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { getBlogPosts } from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Card, EmptyState } from '../../components/ui';
import { colors } from '../../theme';
import type { SettingsStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<SettingsStackParamList, 'Blog'>;

export default function BlogListScreen() {
  const navigation = useNavigation<Nav>();
  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['blog'],
    queryFn: () => getBlogPosts(),
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
      keyExtractor={(p) => p.slug}
      refreshing={isRefetching}
      onRefresh={refetch}
      contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
      ListEmptyComponent={<EmptyState title="No articles yet" subtitle="Check back soon for guides and tips." />}
      renderItem={({ item }) => (
        <Pressable onPress={() => navigation.navigate('BlogPost', { slug: item.slug, title: item.title })}>
          <Card style={styles.row}>
            <Text style={styles.title}>{item.title}</Text>
            {item.excerpt ? <Text style={styles.excerpt} numberOfLines={3}>{item.excerpt}</Text> : null}
            <Text style={styles.meta}>
              {item.published_at}
              {item.reading_minutes ? ` · ${item.reading_minutes} min read` : ''}
            </Text>
          </Card>
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  row: { marginBottom: 12 },
  title: { fontSize: 17, fontWeight: '800', color: colors.text },
  excerpt: { fontSize: 14, color: colors.muted, marginTop: 6, lineHeight: 20 },
  meta: { fontSize: 12, color: colors.muted, marginTop: 10 },
});
