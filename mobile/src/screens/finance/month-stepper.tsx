import * as Haptics from 'expo-haptics';
import { Pressable, StyleSheet, View } from 'react-native';

import { AppText } from '@/components/app-text';
import { Icon } from '@/components/icon';
import { colors, fonts, radius } from '@/theme';

import { monthLabel, shiftMonth, thisMonth } from './periods';

/** "‹ November 2026 ›" — steps one month (or quarter) at a time; never past the current month. */
export function MonthStepper({ value, onChange, label, step = 1 }: { value: string; onChange: (ym: string) => void; label?: string; step?: number }) {
  const atEnd = value >= thisMonth();
  const go = (by: number) => {
    Haptics.selectionAsync().catch(() => {});
    onChange(shiftMonth(value, by));
  };
  return (
    <View style={styles.wrap} accessibilityRole="adjustable" accessibilityLabel={`Return period ${label ?? monthLabel(value)}`}>
      <Pressable accessibilityRole="button" accessibilityLabel="Previous period" onPress={() => go(-step)} style={styles.btn} hitSlop={6}>
        <Icon name="chevron-back" size={20} color={colors.tealDark} />
      </Pressable>
      <AppText style={styles.label} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.75}>
        {label ?? monthLabel(value)}
      </AppText>
      <Pressable accessibilityRole="button" accessibilityLabel="Next period" accessibilityState={{ disabled: atEnd }} disabled={atEnd} onPress={() => go(step)} style={[styles.btn, atEnd && { opacity: 0.35 }]} hitSlop={6}>
        <Icon name="chevron-forward" size={20} color={colors.tealDark} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', alignItems: 'center', height: 48, borderRadius: radius.md, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border },
  btn: { width: 48, height: 48, alignItems: 'center', justifyContent: 'center' },
  label: { flex: 1, textAlign: 'center', fontFamily: fonts.bold, fontSize: 15 },
});
