import { Href, router } from 'expo-router';
import { View } from 'react-native';

import { AppText } from '@/components/app-text';
import { Card, IconTile, Overline } from '@/components/blocks';
import type { IconName } from '@/components/icon';
import { Icon } from '@/components/icon';
import { PressableScale } from '@/components/pressable-scale';
import { Screen } from '@/components/screen';
import { Pill } from '@/components/status-pill';
import { useGstr1, useGstr3b } from '@/hooks/finance';
import { ReturnPeriodPicker, useReturnPeriod } from '@/screens/gst/return-period';
import { colors, fonts, shadow } from '@/theme';
import { date, money } from '@/utils/format';

/** GST hub for one return period: GSTR-1, GSTR-3B, GSTR-2B and HSN. Accepts ?month=YYYY-MM&frequency=. */
export default function GstHub() {
  const p = useReturnPeriod();
  const g1 = useGstr1(p.range.from, p.range.to);
  const g3 = useGstr3b(p.query);
  const refreshing = g1.isRefetching || g3.isRefetching;

  const outwardTax = g1.data ? g1.data.gstr3b_outward.igst + g1.data.gstr3b_outward.cgst + g1.data.gstr3b_outward.sgst : 0;

  return (
    <Screen
      nav="back"
      title="GST returns"
      subtitle={p.label}
      onRefresh={() => {
        g1.refetch();
        g3.refetch();
      }}
      refreshing={refreshing}
    >
      <ReturnPeriodPicker p={p} />

      <ReturnCard
        icon="arrow-up-circle-outline"
        title="GSTR-1"
        sub="Sales you made (outward supplies)"
        loading={g1.isLoading}
        error={g1.error as Error | null}
        figure={g1.data ? money(g1.data.gstr3b_outward.taxable) : undefined}
        figureLabel="taxable value"
        detail={g1.data ? `${g1.data.gstr3b_outward.invoices} invoices · ${g1.data.gstr3b_outward.credit_notes} credit notes · tax ${money(outwardTax)}` : undefined}
        onPress={() => router.push(`/gst/gstr1?${p.qs}` as Href)}
      />
      <ReturnCard
        icon="calculator-outline"
        title="GSTR-3B"
        sub="Summary return and tax to pay"
        loading={g3.isLoading}
        error={g3.error as Error | null}
        figure={g3.data ? money(g3.data.net_cash.total) : undefined}
        figureLabel="to pay in cash"
        detail={g3.data ? `ITC ${money(g3.data.itc.total)} · due ${date(g3.data.period.due_date)}` : undefined}
        tone={g3.data && g3.data.net_cash.total > 0 ? 'due' : 'paid'}
        onPress={() => router.push(`/gst/gstr3b?${p.qs}` as Href)}
      />

      <Overline>Purchases and HSN</Overline>
      <ReturnCard icon="cloud-download-outline" title="GSTR-2B" sub="Match supplier invoices with your purchases" onPress={() => router.push(`/gst/gstr2b?${p.qs}` as Href)} />
      <ReturnCard icon="list-outline" title="HSN summary" sub="Table 12 — HSN/SAC-wise outward supplies" onPress={() => router.push(`/gst/hsn?${p.qs}` as Href)} />

      <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
        Figures come from your issued invoices, credit notes, purchase bills, expenses and cash memos for {p.label}. File on the GST portal.
      </AppText>
    </Screen>
  );
}

function ReturnCard({
  icon,
  title,
  sub,
  figure,
  figureLabel,
  detail,
  loading,
  error,
  tone,
  onPress,
}: {
  icon: IconName;
  title: string;
  sub: string;
  figure?: string;
  figureLabel?: string;
  detail?: string;
  loading?: boolean;
  error?: Error | null;
  tone?: 'due' | 'paid';
  onPress: () => void;
}) {
  return (
    <PressableScale accessibilityRole="button" accessibilityLabel={`${title}. ${sub}${figure ? `. ${figure} ${figureLabel}` : ''}`} onPress={onPress} scaleTo={0.98} style={shadow.soft}>
      <Card>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
          <IconTile icon={icon} />
          <View style={{ flex: 1, gap: 2 }}>
            <AppText variant="h3">{title}</AppText>
            <AppText variant="small" color={colors.textMuted} numberOfLines={2}>
              {sub}
            </AppText>
          </View>
          <Icon name="chevron-forward" size={18} color={colors.textMuted} />
        </View>
        {loading ? (
          <AppText variant="small" color={colors.textMuted}>Working out the figures…</AppText>
        ) : error ? (
          <AppText variant="small" color={colors.redText}>{error.message}</AppText>
        ) : figure ? (
          <View style={{ gap: 4 }}>
            <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 8 }}>
              <AppText variant="moneyLg">{figure}</AppText>
              <AppText style={{ fontFamily: fonts.medium, fontSize: 13, flex: 1 }} color={colors.textMuted}>
                {figureLabel}
              </AppText>
              {tone ? <Pill label={tone === 'due' ? 'Tax due' : 'Nothing to pay'} tone={tone} /> : null}
            </View>
            {detail ? <AppText variant="small" color={colors.textMuted}>{detail}</AppText> : null}
          </View>
        ) : null}
      </Card>
    </PressableScale>
  );
}
