import { Href, router } from 'expo-router';
import { View } from 'react-native';

import { AppText } from '@/components/app-text';
import { Card, IconTile, Loading, Overline } from '@/components/blocks';
import { Icon, IconName } from '@/components/icon';
import { PressableScale } from '@/components/pressable-scale';
import { Screen } from '@/components/screen';
import { Pill } from '@/components/status-pill';
import { useReportsHub } from '@/hooks/workspace';
import { colors, shadow } from '@/theme';

type Report = { icon: IconName; title: string; sub: string; href?: Href; bg: string; fg: string };

const SECTIONS: { title: string; items: Report[] }[] = [
  {
    title: 'Business',
    items: [
      { icon: 'trending-up', title: 'Profit & loss', sub: 'Revenue, expenses and profit for a period', href: '/reports/pnl' as Href, bg: colors.greenTint, fg: colors.greenText },
      { icon: 'hourglass-outline', title: 'Receivables ageing', sub: 'Who owes you, and for how long', href: '/reports/aging' as Href, bg: colors.amberTint, fg: colors.amberText },
      { icon: 'wallet-outline', title: 'Expenses', sub: 'By category and month, CSV/PDF', href: '/expenses' as Href, bg: colors.redTint, fg: colors.redText },
    ],
  },
  {
    title: 'GST',
    items: [{ icon: 'document-attach-outline', title: 'GST returns', sub: 'GSTR-1, GSTR-3B, GSTR-2B, HSN', href: '/gst' as Href, bg: colors.tealTint, fg: colors.tealDark }],
  },
];

const REGISTER_LOOK: Report[] = [
  { icon: 'albums-outline', title: '', sub: '', bg: colors.slateTint, fg: colors.slateText },
  { icon: 'list-outline', title: '', sub: '', bg: colors.tealTint, fg: colors.teal },
];

/** All reports in one list; the registers come from the server (GET /reports) and open reports/[report]. */
export default function Reports() {
  const hub = useReportsHub();
  // Server groups → sections, so a register added on the web appears here without an app update.
  const groups = new Map<string, Report[]>();
  (hub.data?.data ?? []).forEach((r, i) => {
    const look = REGISTER_LOOK[i % REGISTER_LOOK.length]!;
    const list = groups.get(r.group) ?? [];
    list.push({ ...look, title: r.title, sub: r.uses_dates ? 'By date range · Excel, CSV, PDF' : 'Excel, CSV, PDF', href: `/reports/${r.key}` as Href });
    groups.set(r.group, list);
  });
  // A server group with the same name as a built-in section (e.g. "GST") joins it.
  const sections = SECTIONS.map((sec) => ({ ...sec, items: [...sec.items, ...(groups.get(sec.title) ?? [])] }));
  for (const [title, items] of groups) if (!SECTIONS.some((sec) => sec.title === title)) sections.push({ title, items });

  return (
    <Screen nav="back" title="Reports" onRefresh={() => hub.refetch()} refreshing={hub.isRefetching}>
      {hub.isLoading ? <Loading label="Loading registers…" /> : null}
      {sections.map((s) => (
        <View key={s.title} style={{ gap: 10 }}>
          <Overline>{s.title}</Overline>
          {s.items.map((r) => {
            const href = r.href;
            const body = (
              <Card style={{ flexDirection: 'row', alignItems: 'center', opacity: href ? 1 : 0.75 }}>
                <IconTile icon={r.icon} bg={r.bg} fg={r.fg} />
                <View style={{ flex: 1, gap: 2 }}>
                  <AppText variant="h3">{r.title}</AppText>
                  <AppText variant="small" color={colors.textMuted}>
                    {r.sub}
                  </AppText>
                </View>
                {href ? <Icon name="chevron-forward" size={18} color={colors.textMuted} /> : <Pill label="Soon" tone="draft" />}
              </Card>
            );
            return href ? (
              <PressableScale key={r.title} accessibilityRole="button" accessibilityLabel={`${r.title}. ${r.sub}`} onPress={() => router.push(href)} scaleTo={0.98} style={shadow.soft}>
                {body}
              </PressableScale>
            ) : (
              <View key={r.title} accessible accessibilityLabel={`${r.title}. ${r.sub}`}>
                {body}
              </View>
            );
          })}
        </View>
      ))}
    </Screen>
  );
}
