import { StyleSheet, View } from 'react-native';

import { AppText } from '@/components/app-text';
import { colors, fonts } from '@/theme';
import { money } from '@/utils/format';

export type HeadsRow = { label: string; taxable?: number; igst: number; cgst: number; sgst: number; strong?: boolean; hint?: string };

/** GST return grid: one row per line of the return, columns Taxable · IGST · CGST · SGST (as on the portal). */
export function HeadsTable({ rows, taxable = true }: { rows: HeadsRow[]; taxable?: boolean }) {
  const cols = taxable ? ['Taxable', 'IGST', 'CGST', 'SGST'] : ['IGST', 'CGST', 'SGST'];
  return (
    <View style={{ gap: 2 }}>
      <View style={styles.row}>
        <View style={{ flex: 1 }} />
        {cols.map((c) => (
          <AppText key={c} variant="small" color={colors.textMuted} style={styles.num}>
            {c}
          </AppText>
        ))}
      </View>
      {rows.map((r) => (
        <View
          key={r.label}
          style={[styles.line, r.strong && styles.strong]}
          accessible
          accessibilityLabel={`${r.label}: ${taxable && r.taxable != null ? `taxable ${money(r.taxable)}, ` : ''}IGST ${money(r.igst)}, CGST ${money(r.cgst)}, SGST ${money(r.sgst)}`}
        >
          <View style={styles.row}>
            <AppText style={{ flex: 1, fontFamily: r.strong ? fonts.bold : fonts.medium, fontSize: 13 }} numberOfLines={2}>
              {r.label}
            </AppText>
            {taxable ? <Cell v={r.taxable} strong={r.strong} /> : null}
            <Cell v={r.igst} strong={r.strong} />
            <Cell v={r.cgst} strong={r.strong} />
            <Cell v={r.sgst} strong={r.strong} />
          </View>
          {r.hint ? (
            <AppText variant="small" color={colors.textMuted}>
              {r.hint}
            </AppText>
          ) : null}
        </View>
      ))}
    </View>
  );
}

function Cell({ v, strong }: { v?: number; strong?: boolean }) {
  return (
    <AppText style={[styles.num, { fontFamily: strong ? fonts.bold : fonts.medium, fontSize: 12 }]} color={v ? colors.text : colors.textMuted} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.7}>
      {v == null ? '—' : money(v, { paise: false })}
    </AppText>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  line: { paddingVertical: 8, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.border, gap: 2 },
  strong: { borderTopWidth: 1, borderTopColor: colors.borderStrong },
  num: { width: 64, textAlign: 'right', fontVariant: ['tabular-nums'] },
});
