import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import { setStatusBarStyle } from 'expo-status-bar';
import { createContext, ReactNode, useCallback, useContext, useMemo, useRef, useState } from 'react';
import { NativeScrollEvent, NativeSyntheticEvent, RefreshControl, StyleProp, StyleSheet, View, ViewStyle } from 'react-native';
import { KeyboardAwareScrollView, KeyboardStickyView } from 'react-native-keyboard-controller';
import Animated, { Extrapolation, interpolate, useAnimatedStyle, useSharedValue, withTiming } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, gradient, space, TAB_BAR_HEIGHT } from '@/theme';

import { AppText } from './app-text';
import { Button } from './button';
import type { IconName } from './icon';

type ScrollEvt = NativeSyntheticEvent<NativeScrollEvent>;

type ScreenScroll = {
  /** Space the floating header takes; lists pad their top by this much. */
  topInset: number;
  onScroll: (e: ScrollEvt) => void;
  onScrollEnd: () => void;
};

const NO_SCROLL: ScreenScroll = { topInset: 0, onScroll: () => {}, onScrollEnd: () => {} };
const ScrollCtx = createContext<ScreenScroll>(NO_SCROLL);

/** For lists rendered inside <Screen scroll={false}>: wire these into the list. */
export function useScreenScroll(): ScreenScroll {
  return useContext(ScrollCtx);
}

export type ScreenProps = {
  title?: string;
  subtitle?: string;
  /** Show a back arrow ('back') or close cross ('close'). Root tabs pass nothing. */
  nav?: 'back' | 'close';
  /** Small icon actions at the right of the top bar (BarAction). Never "create" — that is the + button. */
  right?: ReactNode;
  /** Header content that always stays visible (e.g. search on Invoices). */
  headerExtra?: ReactNode;
  /** Header content that slides away while scrolling down and returns on scroll up (filters, search). */
  collapsible?: ReactNode;
  /** Brand gradient header for detail screens with a big number (invoice, customer ledger). */
  hero?: boolean;
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

function Bg({ hero, style, children }: { hero?: boolean; style: StyleProp<ViewStyle>; children: ReactNode }) {
  return hero ? (
    <LinearGradient colors={gradient.hero} locations={gradient.heroLocations} start={{ x: 0, y: 0 }} end={{ x: 0.7, y: 1 }} style={style}>
      {children}
    </LinearGradient>
  ) : (
    <View style={[style, { backgroundColor: colors.ground }]}>{children}</View>
  );
}

/**
 * Every screen is built on this: safe areas, the top bar, a scroll view that
 * keeps the focused input above the keyboard, pull-to-refresh, a sticky
 * footer, a compact bar that fades in on scroll, and (for lists) a floating
 * header whose filters slide away. Change it here and every screen follows.
 */
export function Screen({
  title,
  subtitle,
  nav,
  right,
  headerExtra,
  collapsible,
  hero,
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
  const fg = hero ? colors.onDark : colors.text;
  const scrollY = useSharedValue(0);
  const hidden = useSharedValue(0); // 0…collapseH: how far the collapsible part has slid away
  const collapseH = useSharedValue(0);
  const lastY = useRef(0);
  const [headerH, setHeaderH] = useState(0);

  useFocusEffect(
    useCallback(() => {
      setStatusBarStyle(hero ? 'light' : 'dark', true);
    }, [hero]),
  );

  const onScroll = useCallback(
    (e: ScrollEvt) => {
      const y = e.nativeEvent.contentOffset.y;
      const dy = y - lastY.current;
      lastY.current = y;
      scrollY.set(y);
      const h = collapseH.get();
      if (!h) return;
      // diff-clamp: scrolling down hides the filters, any scroll up brings them back
      hidden.set(y <= 0 ? 0 : Math.min(h, Math.max(0, hidden.get() + dy)));
    },
    [scrollY, hidden, collapseH],
  );
  const onScrollEnd = useCallback(() => {
    const h = collapseH.get();
    const v = hidden.get();
    if (h && v > 0 && v < h) hidden.set(withTiming(v > h / 2 && lastY.current > h ? h : 0, { duration: 180 }));
  }, [hidden, collapseH]);

  const ctx = useMemo(() => ({ topInset: headerH, onScroll, onScrollEnd }), [headerH, onScroll, onScrollEnd]);

  const compactStyle = useAnimatedStyle(() => ({
    opacity: interpolate(scrollY.value, [36, 72], [0, 1], Extrapolation.CLAMP),
  }));
  const collapseStyle = useAnimatedStyle(() => {
    const h = collapseH.value || 1;
    return {
      transform: [{ translateY: -hidden.value }],
      opacity: interpolate(hidden.value, [0, h * 0.7], [1, 0], Extrapolation.CLAMP),
    };
  });
  const edgeStyle = useAnimatedStyle(() => ({
    opacity: interpolate(scrollY.value, [0, 12], [0, 1], Extrapolation.CLAMP),
    transform: [{ translateY: -hidden.value }],
  }));

  const navButton = nav ? (
    <Button
      kind={hero ? 'glass' : 'outline'}
      size="md"
      icon={nav === 'close' ? 'close' : 'chevron-back'}
      accessibilityLabel={nav === 'close' ? 'Close' : 'Back'}
      onPress={() => (router.canGoBack() ? router.back() : router.replace('/'))}
    />
  ) : null;

  const bar = (title || nav || right) && (
    <View style={styles.bar}>
      {navButton}
      <View style={{ flex: 1 }}>
        {title ? (
          <AppText variant="h1" color={fg} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.7} accessibilityRole="header" style={{ fontSize: nav ? 20 : 28 }}>
            {title}
          </AppText>
        ) : null}
        {subtitle ? (
          <AppText variant="small" color={hero ? colors.onDarkMuted : colors.textMuted} numberOfLines={1} style={{ fontSize: 13 }}>
            {subtitle}
          </AppText>
        ) : null}
      </View>
      {right ? <View style={styles.right}>{right}</View> : null}
    </View>
  );

  const refresh = onRefresh ? <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.teal} progressViewOffset={insets.top} /> : undefined;

  const body = scroll ? (
    <>
      <KeyboardAwareScrollView
        bottomOffset={footer ? 120 : 24}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="interactive"
        refreshControl={refresh}
        onScroll={onScroll}
        scrollEventThrottle={16}
        contentContainerStyle={{ paddingBottom: bottomPad }}
        showsVerticalScrollIndicator
      >
        <Bg hero={hero} style={[styles.header, hero && styles.heroShape, { paddingTop: insets.top + 8 }]}>
          {bar}
          {headerExtra}
          {collapsible}
        </Bg>
        <View style={[styles.content, hero && { paddingTop: space.lg }, contentStyle]}>{children}</View>
      </KeyboardAwareScrollView>
      {title ? (
        // compact bar: title + back stay reachable once the big header has scrolled away
        <Animated.View pointerEvents="box-none" style={[styles.compact, compactStyle]}>
          <Bg hero={hero} style={[styles.compactInner, { paddingTop: insets.top + 6 }, !hero && styles.compactEdge]}>
            {navButton}
            <AppText variant="h2" color={fg} numberOfLines={1} style={{ flex: 1, marginLeft: navButton ? 0 : 4 }}>
              {title}
            </AppText>
          </Bg>
        </Animated.View>
      ) : null}
    </>
  ) : (
    <ScrollCtx.Provider value={ctx}>
      <View style={[{ flex: 1 }, contentStyle]}>{headerH ? children : null}</View>
      {/* floating header: lists scroll underneath it */}
      <View pointerEvents="box-none" style={styles.float} onLayout={(e) => setHeaderH(Math.round(e.nativeEvent.layout.height))}>
        <Bg hero={hero} style={[styles.floatTop, { paddingTop: insets.top + 8 }]}>
          {bar}
          {headerExtra}
        </Bg>
        {collapsible ? (
          <Animated.View style={[styles.collapse, collapseStyle]}>
            <View
              onLayout={(e) => {
                const h = Math.round(e.nativeEvent.layout.height);
                if (h && Math.abs(h - collapseH.get()) > 1) collapseH.set(h);
              }}
              style={{ paddingHorizontal: space.xl, paddingBottom: 12 }}
            >
              {collapsible}
            </View>
          </Animated.View>
        ) : null}
        <Animated.View pointerEvents="none" style={[styles.edge, edgeStyle]} />
      </View>
    </ScrollCtx.Provider>
  );

  return (
    <View style={styles.root}>
      {body}
      {/* fixed backdrop behind the status bar so its icons never sit on scrolled content */}
      {hero ? (
        <LinearGradient pointerEvents="none" colors={gradient.hero} locations={gradient.heroLocations} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={[styles.statusBg, { height: insets.top }]} />
      ) : (
        <View pointerEvents="none" style={[styles.statusBg, { height: insets.top, backgroundColor: colors.ground }]} />
      )}
      {footer ? (
        <KeyboardStickyView offset={{ opened: insets.bottom }}>
          <View style={[styles.footer, { paddingBottom: insets.bottom + 12 }]}>{footer}</View>
        </KeyboardStickyView>
      ) : null}
    </View>
  );
}

/** Round icon action for the top bar (search, bell, share…). */
export function BarAction({ icon, label, onPress, hero, badge }: { icon: IconName; label: string; onPress: () => void; hero?: boolean; badge?: boolean }) {
  return (
    <View>
      <Button kind={hero ? 'glass' : 'outline'} size="md" icon={icon} accessibilityLabel={label} onPress={onPress} />
      {badge ? <View style={styles.badge} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.ground },
  statusBg: { position: 'absolute', top: 0, left: 0, right: 0 },
  header: { paddingHorizontal: space.xl, paddingBottom: space.lg, gap: 14 },
  heroShape: { borderBottomLeftRadius: 28, borderBottomRightRadius: 28, paddingBottom: space.xl },
  bar: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 46 },
  right: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  content: { padding: space.lg, paddingTop: 4, gap: space.md },
  compact: { position: 'absolute', top: 0, left: 0, right: 0 },
  compactInner: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: space.lg, paddingBottom: 8, minHeight: 52 },
  compactEdge: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.border },
  float: { position: 'absolute', top: 0, left: 0, right: 0 },
  floatTop: { paddingHorizontal: space.xl, paddingBottom: 12, gap: 12, zIndex: 2 },
  collapse: { backgroundColor: colors.ground, zIndex: 1 },
  edge: { height: 1, backgroundColor: colors.border },
  badge: { position: 'absolute', top: 8, right: 9, width: 9, height: 9, borderRadius: 5, backgroundColor: colors.red, borderWidth: 1.5, borderColor: colors.surface },
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
