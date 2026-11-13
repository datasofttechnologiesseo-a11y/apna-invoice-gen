/** Small shared building blocks: cards, rows, section titles, states, chips. */
import { ReactNode } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, View, ViewStyle } from 'react-native';

import { colors, fonts, radius, shadow, space } from '@/theme';
import { initials } from '@/utils/format';

import { AppText } from './app-text';
import { Button } from './button';
import { Icon, IconName } from './icon';

export function Card({ children, style, raised }: { children: ReactNode; style?: ViewStyle; raised?: boolean }) {
  return <View style={[styles.card, raised ? shadow.card : null, style]}>{children}</View>;
}

export function SectionTitle({ title, action, onAction }: { title: string; action?: string; onAction?: () => void }) {
  return (
    <View style={styles.sectionTitle}>
      <AppText variant="h3" accessibilityRole="header">
        {title}
      </AppText>
      {action ? (
        <Pressable accessibilityRole="button" onPress={onAction} hitSlop={10}>
          <AppText variant="label" color={colors.tealDark}>
            {action}
          </AppText>
        </Pressable>
      ) : null}
    </View>
  );
}

export function Overline({ children }: { children: string }) {
  return (
    <AppText variant="overline" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
      {children}
    </AppText>
  );
}

export function Avatar({ name, size = 44, tone = 'tint' }: { name: string; size?: number; tone?: 'tint' | 'dark' | 'amber' }) {
  const bg = tone === 'dark' ? colors.navy : tone === 'amber' ? colors.amber : colors.tealTint;
  const fg = tone === 'dark' ? colors.onDark : tone === 'amber' ? colors.navy : colors.teal;
  return (
    <View style={{ width: size, height: size, borderRadius: size * 0.32, backgroundColor: bg, alignItems: 'center', justifyContent: 'center' }}>
      <AppText style={{ fontFamily: fonts.bold, fontSize: size * 0.32 }} color={fg}>
        {initials(name)}
      </AppText>
    </View>
  );
}

/** One tappable list line: leading avatar/icon, title + subtitle, trailing content. */
export function Row({
  title,
  subtitle,
  leading,
  trailing,
  onPress,
  divider = true,
  accessibilityLabel,
}: {
  title: string;
  subtitle?: string;
  leading?: ReactNode;
  trailing?: ReactNode;
  onPress?: () => void;
  divider?: boolean;
  accessibilityLabel?: string;
}) {
  return (
    <Pressable
      disabled={!onPress}
      accessibilityRole={onPress ? 'button' : undefined}
      accessibilityLabel={accessibilityLabel}
      onPress={onPress}
      style={({ pressed }) => [styles.row, divider ? styles.rowDivider : null, { opacity: pressed ? 0.7 : 1 }]}
    >
      {leading}
      <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
        <AppText style={{ fontFamily: fonts.semibold, fontSize: 15 }} numberOfLines={1}>
          {title}
        </AppText>
        {subtitle ? (
          <AppText variant="small" color={colors.textMuted} numberOfLines={1}>
            {subtitle}
          </AppText>
        ) : null}
      </View>
      {trailing}
    </Pressable>
  );
}

export function IconTile({ icon, bg = colors.tealTint, fg = colors.teal, size = 40 }: { icon: IconName; bg?: string; fg?: string; size?: number }) {
  return (
    <View style={{ width: size, height: size, borderRadius: size * 0.3, backgroundColor: bg, alignItems: 'center', justifyContent: 'center' }}>
      <Icon name={icon} size={size * 0.48} color={fg} />
    </View>
  );
}

export function Loading({ label = 'Loading…' }: { label?: string }) {
  return (
    <View style={styles.state} accessibilityLabel={label}>
      <ActivityIndicator color={colors.teal} size="large" />
    </View>
  );
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <View style={styles.state}>
      <IconTile icon="cloud-offline-outline" bg={colors.redTint} fg={colors.redText} size={56} />
      <AppText variant="h3" align="center">
        Could not load this
      </AppText>
      <AppText variant="small" color={colors.textMuted} align="center" style={{ maxWidth: 280 }}>
        {message}
      </AppText>
      {onRetry ? <Button label="Try again" kind="soft" size="md" onPress={onRetry} /> : null}
    </View>
  );
}

export function EmptyState({ icon, title, body, action, onAction }: { icon: IconName; title: string; body?: string; action?: string; onAction?: () => void }) {
  return (
    <View style={styles.state}>
      <IconTile icon={icon} size={60} />
      <AppText variant="h3" align="center">
        {title}
      </AppText>
      {body ? (
        <AppText variant="small" color={colors.textMuted} align="center" style={{ maxWidth: 280 }}>
          {body}
        </AppText>
      ) : null}
      {action ? <Button label={action} size="md" onPress={onAction} /> : null}
    </View>
  );
}

/** Horizontal filter chips. `dark` = on the gradient header. */
export function Chips<T extends string>({
  value,
  options,
  onChange,
  dark,
}: {
  value: T;
  options: { value: T; label: string }[];
  onChange: (v: T) => void;
  dark?: boolean;
}) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }} style={{ flexGrow: 0 }}>
      {options.map((o) => {
        const on = o.value === value;
        return (
          <Pressable
            key={o.value}
            accessibilityRole="button"
            accessibilityState={{ selected: on }}
            onPress={() => onChange(o.value)}
            style={[
              styles.chip,
              { backgroundColor: on ? colors.amber : dark ? 'rgba(255,255,255,0.14)' : colors.surface },
            ]}
          >
            <AppText style={{ fontFamily: on ? fonts.bold : fonts.semibold, fontSize: 13 }} color={on ? colors.navy : dark ? colors.onDark : colors.text}>
              {o.label}
            </AppText>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

/** Two-to-four way switch (Customers / Vendors …). */
export function Segmented<T extends string>({ value, options, onChange, dark }: { value: T; options: { value: T; label: string }[]; onChange: (v: T) => void; dark?: boolean }) {
  return (
    <View style={[styles.seg, { backgroundColor: dark ? 'rgba(255,255,255,0.12)' : colors.ground }]} accessibilityRole="tablist">
      {options.map((o) => {
        const on = o.value === value;
        return (
          <Pressable key={o.value} accessibilityRole="tab" accessibilityState={{ selected: on }} onPress={() => onChange(o.value)} style={[styles.segItem, on ? styles.segOn : null]}>
            <AppText style={{ fontFamily: on ? fonts.bold : fonts.semibold, fontSize: 14 }} color={on ? colors.navy : dark ? colors.onDark : colors.textMuted}>
              {o.label}
            </AppText>
          </Pressable>
        );
      })}
    </View>
  );
}

export function KeyValue({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <View style={styles.kv}>
      <AppText variant={strong ? 'h3' : 'body'} color={strong ? colors.text : colors.textBody} style={{ flex: 1, fontSize: strong ? 15 : 14 }}>
        {label}
      </AppText>
      <AppText variant={strong ? 'moneyLg' : 'money'} style={strong ? null : { fontFamily: fonts.medium, fontSize: 14 }}>
        {value}
      </AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: colors.surface, borderRadius: radius.lg + 2, padding: space.lg, gap: space.md },
  sectionTitle: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 4, marginTop: 6 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 60, paddingVertical: 10 },
  rowDivider: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.border },
  state: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, padding: 32, minHeight: 280 },
  chip: { height: 36, paddingHorizontal: 14, borderRadius: 999, alignItems: 'center', justifyContent: 'center' },
  seg: { flexDirection: 'row', padding: 4, borderRadius: 14 },
  segItem: { flex: 1, height: 40, borderRadius: 11, alignItems: 'center', justifyContent: 'center' },
  segOn: { backgroundColor: colors.surface },
  kv: { flexDirection: 'row', alignItems: 'baseline', gap: 12 },
});
