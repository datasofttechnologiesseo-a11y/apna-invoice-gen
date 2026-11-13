import * as Haptics from 'expo-haptics';
import { ActivityIndicator, StyleSheet, View, ViewStyle } from 'react-native';

import { colors, fonts, radius } from '@/theme';

import { AppText } from './app-text';
import { PressableScale } from './pressable-scale';
import { Icon, IconName } from './icon';

type Kind = 'primary' | 'accent' | 'soft' | 'outline' | 'danger' | 'ghost' | 'glass';

const look: Record<Kind, { bg: string; fg: string; border?: string }> = {
  primary: { bg: colors.teal, fg: colors.onDark },
  accent: { bg: colors.amber, fg: colors.navy },
  soft: { bg: colors.tealTint, fg: colors.tealDark },
  outline: { bg: colors.surface, fg: colors.navy, border: '#D0D5DD' },
  danger: { bg: '#FEF2F2', fg: colors.redText },
  ghost: { bg: 'transparent', fg: colors.tealDark },
  glass: { bg: 'rgba(255,255,255,0.14)', fg: colors.onDark },
};

export type ButtonProps = {
  label?: string;
  onPress?: () => void;
  kind?: Kind;
  icon?: IconName;
  loading?: boolean;
  disabled?: boolean;
  size?: 'lg' | 'md' | 'sm';
  style?: ViewStyle;
  accessibilityLabel?: string;
};

const heights = { lg: 56, md: 46, sm: 38 };

export function Button({ label, onPress, kind = 'primary', icon, loading, disabled, size = 'lg', style, accessibilityLabel }: ButtonProps) {
  const l = look[kind];
  const inactive = disabled || loading;
  return (
    <PressableScale
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ disabled: !!inactive, busy: !!loading }}
      disabled={inactive}
      hitSlop={size === 'sm' ? 4 : 0}
      onPress={() => {
        Haptics.selectionAsync().catch(() => {});
        onPress?.();
      }}
      style={[
        styles.base,
        {
          height: heights[size],
          minWidth: label ? undefined : heights[size],
          paddingHorizontal: label ? (size === 'sm' ? 12 : 18) : 0,
          borderRadius: size === 'lg' ? 18 : radius.md,
          backgroundColor: l.bg,
          borderWidth: l.border ? 1.5 : 0,
          borderColor: l.border,
          opacity: inactive ? 0.55 : 1,
        },
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={l.fg} />
      ) : (
        <View style={styles.row}>
          {icon ? <Icon name={icon} size={size === 'sm' ? 16 : 20} color={l.fg} /> : null}
          {label ? (
            <AppText style={{ fontFamily: fonts.bold, fontSize: size === 'sm' ? 13 : size === 'md' ? 15 : 16 }} color={l.fg} numberOfLines={1}>
              {label}
            </AppText>
          ) : null}
        </View>
      )}
    </PressableScale>
  );
}

const styles = StyleSheet.create({
  base: { alignItems: 'center', justifyContent: 'center' },
  row: { flexDirection: 'row', alignItems: 'center', gap: 8 },
});
