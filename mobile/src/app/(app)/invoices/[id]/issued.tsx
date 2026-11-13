import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useState } from 'react';
import { Alert, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ApiError } from '@/api/client';
import { invoicesApi } from '@/api/endpoints';
import { AppText } from '@/components/app-text';
import { Card, KeyValue, Loading } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { useInvoice } from '@/hooks/queries';
import { colors, gradient, shadow } from '@/theme';
import { money } from '@/utils/format';
import { downloadAndShare, openWhatsApp, shareText } from '@/utils/share';

/** Success screen right after an invoice is issued — the moment to send it. */
export default function Issued() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useInvoice(Number(id));
  const insets = useSafeAreaInsets();
  const [busy, setBusy] = useState<string | null>(null);
  const inv = q.data;
  if (!inv) return <Loading />;

  const run = async (k: string, fn: () => Promise<unknown>) => {
    setBusy(k);
    try {
      await fn();
    } catch (e) {
      Alert.alert('Could not share', (e as ApiError).message);
    } finally {
      setBusy(null);
    }
  };
  const done = () => router.replace(`/invoices/${inv.id}`);

  return (
    <View style={{ flex: 1, backgroundColor: colors.ground }}>
      <StatusBar style="light" />
      <ScrollView contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}>
        <LinearGradient colors={gradient.hero} locations={gradient.heroLocations} style={[styles.hero, { paddingTop: insets.top + 40 }]}>
          <View style={styles.tick}>
            <Icon name="checkmark" size={40} color={colors.navy} />
          </View>
          <AppText variant="h1" color={colors.onDark} accessibilityRole="header">
            Invoice issued
          </AppText>
          <AppText color={colors.onDarkMuted} align="center">
            {inv.display_number} · {inv.customer.name} · {money(inv.grand_total)}
          </AppText>
        </LinearGradient>

        <View style={{ paddingHorizontal: 24, marginTop: -90, gap: 16 }}>
          <Card raised>
            <AppText variant="overline" color={colors.tealDark}>
              {inv.document_title}
            </AppText>
            {inv.items.slice(0, 3).map((it) => (
              <KeyValue key={it.id} label={`${it.description} × ${it.quantity}`} value={money(it.amount)} />
            ))}
            <KeyValue label="GST" value={money(inv.total_tax)} />
            <KeyValue label="Total" value={money(inv.grand_total)} strong />
          </Card>

          <AppText variant="label" color={colors.textMuted} align="center">
            Send it now
          </AppText>
          <Button
            label="Send on WhatsApp"
            icon="logo-whatsapp"
            loading={busy === 'wa'}
            onPress={() =>
              run('wa', async () => {
                const l = await invoicesApi.shareLink(inv.id);
                await openWhatsApp(l.whatsapp_link ?? inv.whatsapp_link, `${inv.display_number}: ${l.url}`);
              })
            }
          />
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <Button label="PDF" icon="document-outline" kind="soft" size="md" style={{ flex: 1 }} loading={busy === 'pdf'} onPress={() => run('pdf', () => downloadAndShare(invoicesApi.pdfPath(inv.id), `${inv.display_number}.pdf`))} />
            <Button
              label="Link"
              icon="link-outline"
              kind="soft"
              size="md"
              style={{ flex: 1 }}
              loading={busy === 'link'}
              onPress={() => run('link', async () => shareText((await invoicesApi.shareLink(inv.id)).url))}
            />
          </View>
          <Button label="Done" kind="ghost" onPress={done} />
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  hero: { alignItems: 'center', gap: 10, paddingBottom: 120, paddingHorizontal: 24, borderBottomLeftRadius: 36, borderBottomRightRadius: 36 },
  tick: { width: 80, height: 80, borderRadius: 40, backgroundColor: colors.amber, alignItems: 'center', justifyContent: 'center', borderWidth: 8, borderColor: 'rgba(245,158,11,0.35)', ...shadow.card },
});
