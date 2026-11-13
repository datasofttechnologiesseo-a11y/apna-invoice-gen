import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import { setStatusBarStyle } from 'expo-status-bar';
import { createContext, ReactNode, useCallback, useContext } from 'react';
import { NativeScrollEvent, NativeSyntheticEvent, RefreshControl, StyleSheet, View, ViewStyle } from 'react-native';
import { KeyboardAwareScrollView, KeyboardStickyView } from 'react-native-keyboard-controller';
import Animated, { Extrapolation, interpolate, SharedValue, useAnimatedStyle, useSharedValue } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, gradient, space, TAB_BAR_HEIGHT } from '@/theme';

import { AppText } from './app-text';
import { Button } from './button';

/** Lists inside a Screen report their scroll offset here so the header can collapse. */
const ScrollCtx = createContext<SharedValue<number> | null>(null);

/** onScroll handler for any list rendered inside <Screen>. */
export function useScreenScroll() {
  const y = useContext(ScrollCtx);
  return useCallback(
    (e: NativeSyntheticEvent<NativeScrollEvent>) => {
      y?.set(e.nativeEvent.contentOffset.y);
    },
    [y],
  );
}

export type ScreenProps = {
  title?: string;
  subtitle?: string;
  /** Show a back arrow ('back') or close cross ('close'). Root tabs pass nothing. */
  nav?: 'back' | 'close';
  /** Buttons at the right of the top bar. */
  right?: ReactNode;
  /** Header content that always stays visible (e.g. search on Invoices). */
  headerExtra?: ReactNode;
  /** Header content that slides away as the list scrolls up (filters, search). */
  collapsible?: ReactNode;
  /** Plain light header instead of the gradient. */
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
 * that keeps the focused input above the keyboard, pull-to-refresh, a sticky
 * footer, and a header that collapses on scroll. Change it here and every
 * screen follows.
 */
export function Screen({
  title,
  subtitle,
  nav,
  right,
  headerExtra,
  collapsible,
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
  const scrollY = useSharedValue(0);
  const collapseH = useSharedValue(0);

  // Re-apply this screen's status bar whenever it comes into focus, so going
  // back to a gradient screen never leaves dark icons on the dark header.
  useFocusEffect(
    useCallback(() => {
      setStatusBarStyle(plain ? 'dark' : 'light', true);
    }, [plain]),
  );

  const titleStyle = useAnimatedStyle(() => {
    const range = Math.max(collapseH.value, 60);
    return { transform: [{ scale: interpolate(scrollY.value, [0, range], [1, nav ? 1 : 0.86], Extrapolation.CLAMP) }] };
  });

  const collapseStyle = useAnimatedStyle(() => {
    const h = collapseH.value;
    if (!h) return {};
    const y = scrollY.value;
    return {
      height: interpolate(y, [0, h], [h, 0], Extrapolation.CLAMP),
      opacity: interpolate(y, [0, h * 0.6], [1, 0], Extrapolation.CLAMP),
      transform: [{ translateY: interpolate(y, [0, h], [0, -14], Extrapolation.CLAMP) }],
      // the header's 14px gap above the collapsible part also folds away
      marginTop: interpolate(y, [0, h], [0, -14], Extrapolation.CLAMP),
    };
  });

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
      <Animated.View style={[{ flex: 1, transformOrigin: 'left center' }, titleStyle]}>
        {title ? (
          <AppText variant={nav ? 'h2' : 'h1'} color={fg} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.7} accessibilityRole="header" style={nav ? { fontSize: 20 } : null}>
            {title}
          </AppText>
        ) : null}
        {subtitle ? (
          <AppText variant="small" color={plain ? colors.textMuted : colors.onDarkMuted} numberOfLines={1} style={{ fontSize: 13 }}>
            {subtitle}
          </AppText>
        ) : null}
      </Animated.View>
      {right}
    </View>
  );

  const collapsePart = collapsible ? (
    <Animated.View style={[{ overflow: 'hidden' }, collapseStyle]}>
      <View
        onLayout={(e) => {
          const h = Math.round(e.nativeEvent.layout.height);
          if (h && Math.abs(h - collapseH.get()) > 1) collapseH.set(h);
        }}
      >
        {collapsible}
      </View>
    </Animated.View>
  ) : null;

  const header = plain ? (
    <View style={[styles.header, { paddingTop: insets.top + 8, backgroundColor: colors.ground }]}>
      {bar}
      {headerExtra}
      {collapsePart}
    </View>
  ) : (
    <LinearGradient colors={gradient.hero} locations={gradient.heroLocations} start={{ x: 0, y: 0 }} end={{ x: 0.6, y: 1 }} style={[styles.header, styles.gradient, { paddingTop: insets.top + 8 }]}>
      {bar}
      {headerExtra}
      {collapsePart}
    </LinearGradient>
  );

  const refresh = onRefresh ? <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.teal} /> : undefined;

  return (
    <ScrollCtx.Provider value={scrollY}>
      <View style={styles.root}>
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
        {/* Fixed backdrop behind the status bar: content scrolling under it never
            leaves the light status-bar icons on a white background. */}
        {plain ? (
          <View pointerEvents="none" style={[styles.statusBg, { height: insets.top, backgroundColor: colors.ground }]} />
        ) : (
          <LinearGradient pointerEvents="none" colors={gradient.hero} locations={gradient.heroLocations} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={[styles.statusBg, { height: insets.top }]} />
        )}
        {footer ? (
          <KeyboardStickyView offset={{ opened: insets.bottom }}>
            <View style={[styles.footer, { paddingBottom: insets.bottom + 12 }]}>{footer}</View>
          </KeyboardStickyView>
        ) : null}
      </View>
    </ScrollCtx.Provider>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.ground },
  statusBg: { position: 'absolute', top: 0, left: 0, right: 0 },
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
