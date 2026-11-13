import { router, useLocalSearchParams } from 'expo-router';
import { Linking, View } from 'react-native';

import { AppText } from '@/components/app-text';
import { Card, ErrorState, Loading } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { useLedger } from '@/hooks/queries';
import { colors, fonts, shadow } from '@/theme';
import { date, money } from '@/utils/format';

/** Customer ledger: running balance of invoices, payments and credit notes (same as web). */
export default function Ledger() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useLedger(Number(id));
  const l = q.data;

  if (q.isLoading) return <Screen nav="back" title="Customer"><Loading /></Screen>;
  if (q.error || !l) return <Screen nav="back" title="Customer"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;

  const c = l.customer;
  const phone = c.phone?.replace(/\D/g, '').slice(-10);

  return (
    <Screen
      nav="back"
      title={c.name}
      subtitle={[c.gstin, c.city, c.state?.name].filter(Boolean).join(' · ')}
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      headerExtra={
        phone ? (
          <View style={{ flexDirection: 'row', gap: 8, paddingBottom: 30 }}>
            <Button label="Call" icon="call-outline" kind="glass" size="sm" onPress={() => Linking.openURL(`tel:+91${phone}`)} />
            <Button label="WhatsApp" icon="logo-whatsapp" kind="glass" size="sm" onPress={() => Linking.openURL(`https://wa.me/91${phone}`)} />
          </View>
        ) : (
          <View style={{ height: 30 }} />
        )
      }
      footer={<Button label={`New invoice for ${c.name}`} icon="add" onPress={() => router.push('/invoices/new')} />}
    >
      <View style={[{ backgroundColor: colors.surface, borderRadius: 22, padding: 16, marginTop: -44, flexDirection: 'row', alignItems: 'center' }, shadow.card]}>
        <View style={{ flex: 1 }}>
          <AppText variant="small" color={colors.textMuted}>To collect</AppText>
          <AppText variant="hero" style={{ fontSize: 28 }}>{money(l.totals.outstanding)}</AppText>
          <AppText variant="small" color={colors.textMuted}>
            Invoiced {money(l.totals.invoiced)} · received {money(l.totals.received)}
          </AppText>
        </View>
      </View>

      <Card style={{ padding: 0, overflow: 'hidden', gap: 0 }}>
        <View style={{ flexDirection: 'row', paddingHorizontal: 16, paddingVertical: 10, backgroundColor: colors.tealTint }}>
          <AppText variant="overline" color={colors.tealDark} style={{ flex: 1.6 }}>Entry</AppText>
          <AppText variant="overline" color={colors.tealDark} style={{ flex: 1, textAlign: 'right' }}>Amount</AppText>
          <AppText variant="overline" color={colors.tealDark} style={{ flex: 1, textAlign: 'right' }}>Balance</AppText>
        </View>
        {l.entries.length ? (
          [...l.entries].reverse().map((e, i) => (
            <View key={`${e.ref}-${i}`} style={{ flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 12, borderTopWidth: 1, borderTopColor: colors.divider }}>
              <View style={{ flex: 1.6 }}>
                <AppText style={{ fontFamily: fonts.semibold, fontSize: 14 }}>{e.ref}</AppText>
                <AppText variant="small" color={colors.textMuted}>{date(e.date, true)} · {e.particulars}</AppText>
              </View>
              <AppText style={{ flex: 1, textAlign: 'right', fontFamily: fonts.bold, fontSize: 14 }} color={e.credit > 0 ? colors.greenText : colors.text}>
                {e.credit > 0 ? `−${money(e.credit)}` : money(e.debit)}
              </AppText>
              <AppText style={{ flex: 1, textAlign: 'right', fontSize: 13 }} color={colors.textBody}>{money(e.balance)}</AppText>
            </View>
          ))
        ) : (
          <AppText color={colors.textMuted} style={{ padding: 16 }}>No entries yet.</AppText>
        )}
      </Card>
    </Screen>
  );
}
