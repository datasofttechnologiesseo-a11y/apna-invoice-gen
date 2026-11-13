import * as Haptics from 'expo-haptics';
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import Animated, { FadeIn, useAnimatedStyle, useSharedValue, withDelay, withSpring, withTiming } from 'react-native-reanimated';

import { colors, fonts } from '@/theme';
import { money, moneyShort } from '@/utils/format';

import { AppText } from './app-text';

/**
 * Lightweight charts drawn with plain views + Reanimated (no SVG dependency),
 * so they render identically on iOS/Android and animate on the UI thread.
 * Every amount is shown compact (₹2.4L) with the exact figure on tap and in
 * the accessibility label, so large numbers never break the layout.
 */

export type BarSeries = { key: string; label: string; color: string };
export type BarPoint = { label: string; full?: string; values: Record<string, number> };

function GrowBar({ ratio, color, delay, dim }: { ratio: number; color: string; delay: number; dim: boolean }) {
  const h = useSharedValue(0);
  useEffect(() => {
    h.set(withDelay(delay, withSpring(ratio, { damping: 16, stiffness: 120 })));
  }, [ratio, delay, h]);
  const style = useAnimatedStyle(() => ({ height: `${Math.max(h.value * 100, h.value > 0 ? 2 : 0)}%` }));
  return <Animated.View style={[styles.bar, { backgroundColor: color, opacity: dim ? 0.35 : 1 }, style]} />;
}

/** Grouped vertical bars (e.g. billed vs collected per month). Tap a group for exact values. */
export function BarChart({ data, series, height = 150 }: { data: BarPoint[]; series: BarSeries[]; height?: number }) {
  const [sel, setSel] = useState<number | null>(null);
  const max = Math.max(1, ...data.flatMap((d) => series.map((s) => d.values[s.key] ?? 0)));
  const shown = sel ?? data.length - 1;
  const point = data[shown];
  return (
    <View style={{ gap: 12 }}>
      {point ? (
        <Animated.View key={shown} entering={FadeIn.duration(160)} style={styles.tooltip}>
          <AppText variant="label" color={colors.textMuted}>
            {point.full ?? point.label}
          </AppText>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', columnGap: 16, rowGap: 4 }}>
            {series.map((s) => (
              <View key={s.key} style={styles.legendItem}>
                <View style={[styles.dot, { backgroundColor: s.color }]} />
                <AppText variant="small" color={colors.textMuted}>
                  {s.label}
                </AppText>
                <AppText style={{ fontFamily: fonts.bold, fontSize: 13 }} numberOfLines={1}>
                  {money(point.values[s.key] ?? 0)}
                </AppText>
              </View>
            ))}
          </View>
        </Animated.View>
      ) : null}
      <View style={[styles.plot, { height }]}>
        {[0.25, 0.5, 0.75, 1].map((g) => (
          <View key={g} style={[styles.grid, { bottom: `${g * 100}%` }]} />
        ))}
        <AppText variant="small" color={colors.textMuted} style={styles.maxLabel}>
          {moneyShort(max)}
        </AppText>
        {data.map((d, i) => (
          <Pressable
            key={d.label + i}
            style={styles.group}
            accessibilityRole="button"
            accessibilityLabel={`${d.full ?? d.label}: ${series.map((s) => `${s.label} ${money(d.values[s.key] ?? 0)}`).join(', ')}`}
            onPress={() => {
              Haptics.selectionAsync().catch(() => {});
              setSel(i);
            }}
          >
            <View style={styles.bars}>
              {series.map((s, k) => (
                <GrowBar key={s.key} ratio={(d.values[s.key] ?? 0) / max} color={s.color} delay={i * 50 + k * 30} dim={shown !== i} />
              ))}
            </View>
          </Pressable>
        ))}
      </View>
      <View style={styles.xAxis}>
        {data.map((d, i) => (
          <AppText key={d.label + i} variant="small" align="center" color={shown === i ? colors.text : colors.textMuted} style={{ flex: 1, fontFamily: shown === i ? fonts.bold : fonts.body, fontSize: 11 }} numberOfLines={1}>
            {d.label}
          </AppText>
        ))}
      </View>
    </View>
  );
}

export type Slice = { key: string; label: string; value: number; color: string; count?: number };

function GrowSegment({ flex, color, delay }: { flex: number; color: string; delay: number }) {
  const f = useSharedValue(0);
  useEffect(() => {
    f.set(withDelay(delay, withTiming(flex, { duration: 600 })));
  }, [flex, delay, f]);
  const style = useAnimatedStyle(() => ({ flexGrow: f.value }));
  return <Animated.View style={[{ backgroundColor: color, flexBasis: 0 }, style]} />;
}

/** One rounded bar split into parts (status mix, spend mix) with a tappable legend. */
export function StackedBar({ slices, onPress, showCount }: { slices: Slice[]; onPress?: (s: Slice) => void; showCount?: boolean }) {
  const total = slices.reduce((a, s) => a + s.value, 0);
  const visible = slices.filter((s) => s.value > 0);
  return (
    <View style={{ gap: 14 }}>
      <View style={styles.stack}>
        {visible.length ? visible.map((s, i) => <GrowSegment key={s.key} flex={s.value} color={s.color} delay={i * 80} />) : <View style={{ flex: 1, backgroundColor: colors.slateTint }} />}
      </View>
      <View style={{ gap: 2 }}>
        {slices.map((s) => (
          <Pressable
            key={s.key}
            disabled={!onPress}
            onPress={() => onPress?.(s)}
            accessibilityRole={onPress ? 'button' : undefined}
            style={({ pressed }) => [styles.legendRow, { opacity: pressed ? 0.6 : 1 }]}
          >
            <View style={[styles.dot, { backgroundColor: s.color, width: 10, height: 10 }]} />
            <AppText style={{ flex: 1, fontFamily: fonts.medium, fontSize: 14 }} numberOfLines={1}>
              {s.label}
              {showCount && s.count != null ? <AppText variant="small" color={colors.textMuted}>{`  ${s.count}`}</AppText> : null}
            </AppText>
            <AppText variant="small" color={colors.textMuted} style={{ width: 42, textAlign: 'right' }}>
              {total ? `${Math.round((s.value / total) * 100)}%` : '0%'}
            </AppText>
            <AppText style={{ fontFamily: fonts.bold, fontSize: 14, minWidth: 72, textAlign: 'right' }} numberOfLines={1} accessibilityLabel={money(s.value)}>
              {moneyShort(s.value)}
            </AppText>
          </Pressable>
        ))}
      </View>
    </View>
  );
}

/** Horizontal bars, one per row (ageing buckets, top customers). */
export function RowBars({ rows, color = colors.teal, onPress }: { rows: { key: string; label: string; value: number; hint?: string; color?: string }[]; color?: string; onPress?: (key: string) => void }) {
  const max = Math.max(1, ...rows.map((r) => r.value));
  return (
    <View style={{ gap: 12 }}>
      {rows.map((r, i) => (
        <Pressable key={r.key} disabled={!onPress} onPress={() => onPress?.(r.key)} style={({ pressed }) => ({ gap: 6, opacity: pressed ? 0.6 : 1 })} accessibilityLabel={`${r.label} ${money(r.value)}`}>
          <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 8 }}>
            <AppText style={{ flex: 1, fontFamily: fonts.medium, fontSize: 14 }} numberOfLines={1}>
              {r.label}
              {r.hint ? <AppText variant="small" color={colors.textMuted}>{`  ${r.hint}`}</AppText> : null}
            </AppText>
            <AppText style={{ fontFamily: fonts.bold, fontSize: 14 }}>{moneyShort(r.value)}</AppText>
          </View>
          <View style={styles.track}>
            <GrowWidth ratio={r.value / max} color={r.color ?? color} delay={i * 70} />
          </View>
        </Pressable>
      ))}
    </View>
  );
}

function GrowWidth({ ratio, color, delay }: { ratio: number; color: string; delay: number }) {
  const w = useSharedValue(0);
  useEffect(() => {
    w.set(withDelay(delay, withTiming(ratio, { duration: 650 })));
  }, [ratio, delay, w]);
  const style = useAnimatedStyle(() => ({ width: `${Math.max(w.value * 100, w.value > 0 ? 2 : 0)}%` }));
  return <Animated.View style={[{ height: '100%', borderRadius: 99, backgroundColor: color }, style]} />;
}

/** Thin progress line (collected vs billed). */
export function Progress({ ratio, color = colors.amber, track = 'rgba(255,255,255,0.18)' }: { ratio: number; color?: string; track?: string }) {
  return (
    <View style={[styles.track, { backgroundColor: track, height: 8 }]}>
      <GrowWidth ratio={Math.min(1, Math.max(0, ratio))} color={color} delay={150} />
    </View>
  );
}

const styles = StyleSheet.create({
  tooltip: { gap: 6 },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  dot: { width: 8, height: 8, borderRadius: 4 },
  plot: { flexDirection: 'row', alignItems: 'flex-end', gap: 6, paddingTop: 4 },
  grid: { position: 'absolute', left: 0, right: 0, height: StyleSheet.hairlineWidth, backgroundColor: colors.border },
  maxLabel: { position: 'absolute', top: -14, right: 0, fontSize: 10 },
  group: { flex: 1, height: '100%', justifyContent: 'flex-end' },
  bars: { flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'center', gap: 3, height: '100%' },
  bar: { width: 11, borderTopLeftRadius: 6, borderTopRightRadius: 6 },
  xAxis: { flexDirection: 'row', gap: 6, marginTop: -4 },
  stack: { flexDirection: 'row', height: 14, borderRadius: 99, overflow: 'hidden', gap: 2, backgroundColor: colors.surface },
  legendRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 7 },
  track: { height: 10, borderRadius: 99, backgroundColor: colors.slateTint, overflow: 'hidden' },
});
