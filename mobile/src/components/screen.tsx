import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { ReactNode } from 'react';
import { RefreshControl, StyleSheet, View, ViewStyle } from 'react-native';
import { KeyboardAwareScrollView, KeyboardStickyView } from 'react-native-keyboard-controller';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, gradient, space, TAB_BAR_HEIGHT } from '@/theme';

import { AppText } from './app-text';
import { Button } from './button';

export type ScreenProps = {
  title?: string;
  subtitle?: string;
  /** Show a back arrow ('back') or close cross ('close'). Root tabs pass nothing. */
  nav?: 'back' | 'close';
  /** Buttons at the right of the top bar. */
  right?: ReactNode;
  /** Extra content inside the gradient header (KPIs, search, chips). */
  headerExtra?: ReactNode;
  /** Plain white header instead of the gradient (auth/detail flows). */
  plain?: boolean;
  /** false = children manage their own scrolling (FlashList screens). */
  scroll?: boolean;
  onRefresh?: () => void;
  refreshing?: boolean;
  /** Sticky bottom bar (Save, totals). Rides on top of the keyboard. */
  footer?: ReactNode;
  /** Leaves room for the floating tab bar. */
  tabBar?: boolean;
  contentStyle?: ViewStyle;
  children: ReactNode;
};

/**
 * Every screen is built on this: safe areas, the brand top bar, a scroll view
 * that keeps the focused input above the keyboard, pull-to-refresh, and a
 * sticky footer. Change it here and every screen follows.
 */
export function Screen({
  title,
  subtitle,
  nav,
  right,
  headerExtra,
  plain,
  scroll = true,
  onRefresh,
  refreshing = false,
  footer,
  tabBar,
  contentStyle,
  children,
}: ScreenProps) {
  const insets = useSafeAreaInsets();
  const bottomPad = (tabBar ? TAB_BAR_HEIGHT + insets.bottom : 0) + (footer ? 8 : insets.bottom + space.xl);
  const fg = plain ? colors.text : colors.onDark;

  const bar = (title || nav || right) && (
    <View style={styles.bar}>
      {nav ? (
        <Button
          kind={plain ? 'outline' : 'glass'}
          size="md"
          icon={nav === 'close' ? 'close' : 'chevron-back'}
          accessibilityLabel={nav === 'close' ? 'Close' : 'Back'}
          onPress={() => (router.canGoBack() ? router.back() : router.replace('/'))}
        />
      ) : null}
      <View style={{ flex: 1 }}>
        {title ? (
          <AppText variant={nav ? 'h2' : 'h1'} color={fg} numberOfLines={1} accessibilityRole="header" style={nav ? { fontSize: 20 } : null}>
            {title}
          </AppText>
        ) : null}
        {subtitle ? (
          <AppText variant="small" color={plain ? colors.textMuted : colors.onDarkMuted} numberOfLines={1} style={{ fontSize: 13 }}>
            {subtitle}
          </AppText>
        ) : null}
      </View>
      {right}
    </View>
  );

  const header = plain ? (
    <View style={[styles.header, { paddingTop: insets.top + 8, backgroundColor: colors.ground }]}>
      {bar}
      {headerExtra}
    </View>
  ) : (
    <LinearGradient colors={gradient.hero} locations={gradient.heroLocations} start={{ x: 0, y: 0 }} end={{ x: 0.6, y: 1 }} style={[styles.header, styles.gradient, { paddingTop: insets.top + 8 }]}>
      {bar}
      {headerExtra}
    </LinearGradient>
  );

  const refresh = onRefresh ? <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.teal} /> : undefined;

  return (
    <View style={styles.root}>
      <StatusBar style={plain ? 'dark' : 'light'} />
      {scroll ? (
        <KeyboardAwareScrollView
          bottomOffset={footer ? 120 : 24}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="interactive"
          refreshControl={refresh}
          contentContainerStyle={{ paddingBottom: bottomPad }}
          showsVerticalScrollIndicator
        >
          {header}
          <View style={[styles.content, contentStyle]}>{children}</View>
        </KeyboardAwareScrollView>
      ) : (
        <View style={{ flex: 1 }}>
          {header}
          <View style={[{ flex: 1 }, contentStyle]}>{children}</View>
        </View>
      )}
      {footer ? (
        <KeyboardStickyView offset={{ opened: insets.bottom }}>
          <View style={[styles.footer, { paddingBottom: insets.bottom + 12 }]}>{footer}</View>
        </KeyboardStickyView>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.ground },
  header: { paddingHorizontal: space.xl, paddingBottom: space.xl, gap: 14 },
  gradient: { borderBottomLeftRadius: 28, borderBottomRightRadius: 28 },
  bar: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 46 },
  content: { padding: space.lg, gap: space.md },
  footer: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    paddingHorizontal: space.xl,
    paddingTop: 14,
    gap: 10,
    shadowColor: colors.navy,
    shadowOpacity: 0.16,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: -8 },
    elevation: 12,
  },
});
