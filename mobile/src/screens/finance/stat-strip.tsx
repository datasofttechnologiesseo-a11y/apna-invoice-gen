import { StyleSheet, View } from 'react-native';

import { AppText } from '@/components/app-text';
import { colors, fonts, radius } from '@/theme';
import { money, moneyShort } from '@/utils/format';

export type Stat = { label: string; value: number; tone?: 'plain' | 'good' | 'bad' | 'accent'; count?: boolean };

/** A row of 2–4 compact figures (totals strip). Full amount in the accessibility label. */
export function StatStrip({ stats }: { stats: Stat[] }) {
  return (
    <View style={styles.row}>
      {stats.map((s) => {
        const fg = s.tone === 'good' ? colors.greenText : s.tone === 'bad' ? colors.redText : s.tone === 'accent' ? colors.tealDark : colors.text;
        return (
          <View key={s.label} style={styles.tile} accessible accessibilityLabel={`${s.label} ${s.count ? s.value : money(s.value)}`}>
            <AppText variant="small" color={colors.textMuted} numberOfLines={1}>
              {s.label}
            </AppText>
            <AppText style={styles.value} color={fg} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.7}>
              {s.count ? String(s.value) : moneyShort(s.value)}
            </AppText>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: 8 },
  tile: { flex: 1, backgroundColor: colors.surface, borderRadius: radius.md, paddingHorizontal: 12, paddingVertical: 10, gap: 2, borderWidth: 1, borderColor: colors.border },
  value: { fontFamily: fonts.bold, fontSize: 17, fontVariant: ['tabular-nums'] },
});
