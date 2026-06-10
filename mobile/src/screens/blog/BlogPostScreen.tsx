import React from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { useQuery } from '@tanstack/react-query';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { getBlogPost } from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Centered } from '../../components/ui';
import { colors } from '../../theme';
import type { SettingsStackParamList } from '../../navigation/types';

type Props = NativeStackScreenProps<SettingsStackParamList, 'BlogPost'>;

// Wrap the rendered Markdown HTML in a mobile-friendly reading template.
function buildHtml(post: { title: string; published_at: string | null; reading_minutes: number; author: string | null; body_html: string }): string {
  return `<!DOCTYPE html><html><head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
<style>
  :root { color-scheme: light; }
  body { margin: 0; padding: 20px; font-family: -apple-system, Roboto, "Segoe UI", sans-serif; color: #0f172a; font-size: 17px; line-height: 1.65; -webkit-text-size-adjust: 100%; }
  h1.post-title { font-size: 26px; line-height: 1.25; margin: 0 0 8px; }
  .post-meta { color: #64748b; font-size: 13px; margin-bottom: 24px; }
  h1, h2, h3 { line-height: 1.3; margin-top: 28px; }
  h2 { font-size: 21px; } h3 { font-size: 18px; }
  a { color: #2563eb; }
  img { max-width: 100%; height: auto; border-radius: 10px; }
  pre { background: #f1f5f9; padding: 14px; border-radius: 10px; overflow-x: auto; font-size: 14px; }
  code { background: #f1f5f9; padding: 2px 5px; border-radius: 4px; font-size: 14px; }
  pre code { background: none; padding: 0; }
  blockquote { border-left: 4px solid #e2e8f0; margin: 16px 0; padding: 4px 16px; color: #475569; }
  table { border-collapse: collapse; width: 100%; font-size: 14px; }
  th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
</style></head>
<body>
  <h1 class="post-title">${post.title}</h1>
  <div class="post-meta">${[post.author, post.published_at, post.reading_minutes ? post.reading_minutes + ' min read' : null].filter(Boolean).join(' · ')}</div>
  ${post.body_html}
</body></html>`;
}

export default function BlogPostScreen({ route }: Props) {
  const { slug } = route.params;
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['blog-post', slug],
    queryFn: () => getBlogPost(slug),
  });

  if (isLoading) {
    return (
      <Centered>
        <ActivityIndicator size="large" color={colors.primary} />
      </Centered>
    );
  }
  if (isError || !data) {
    return (
      <Centered>
        <Text style={styles.error}>{apiErrorMessage(error)}</Text>
      </Centered>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.card }}>
      <WebView
        originWhitelist={['*']}
        source={{ html: buildHtml(data) }}
        style={{ flex: 1, backgroundColor: colors.card }}
        showsVerticalScrollIndicator
      />
    </View>
  );
}

const styles = StyleSheet.create({
  error: { color: colors.danger, padding: 24, textAlign: 'center' },
});
