import React, { useState } from 'react';
import { LayoutChangeEvent, Pressable, StyleSheet, View } from 'react-native';
import Svg, { Defs, FeDropShadow, Filter, Path } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { BottomTabBarProps } from '@react-navigation/bottom-tabs';
import { colors } from '../theme';
import { TabIcon, type IconName } from './icons';

interface TabItem {
  route: string;
  icon: IconName;
}

// Only these four show in the bar. Everything secondary (Quotes, Products,
// Finance, account/settings…) lives behind "More", which stays highlighted
// whenever the active screen is one of those secondary destinations.
const ITEMS: TabItem[] = [
  { route: 'Home', icon: 'home' },
  { route: 'Invoices', icon: 'invoices' },
  { route: 'Customers', icon: 'customers' },
  { route: 'More', icon: 'more' },
];
const PRIMARY_ROUTES = ['Home', 'Invoices', 'Customers'];

// ── Geometry (tweak here) ───────────────────────────────────────────────
const SIDE = 9; // small margin from screen edges (wide floating pill)
const TOP_PAD = 42; // room above the pill for the raised circle
const BAR_H = 70; // pill height
const CR = 30; // pill corner radius (very rounded ends)
const INSET = 70; // icon-centre inset from the pill ends
const RB = 29; // active circle radius (54px)
const CCY = -8; // circle centre, relative to the bar's top edge (negative = above)
const NOTCH_HALF = 49; // half-width of the scoop (wider than the circle)
const NOTCH_DEPTH = 26; // how deep the scoop dips

// Floating pill outline with a smooth bezier scoop centred at `cx`. Both control
// points sit at the half-width (one on the top lip, one on the floor), so the
// straight bar edge eases down in a rounded cosine-like bowl that wraps the
// circle — not a pinched V.
function barPath(w: number, cx: number): string {
  const n = NOTCH_HALF;
  const d = NOTCH_DEPTH;
  return [
    `M0 ${CR}`,
    `Q0 0 ${CR} 0`,
    `L${cx - n} 0`,
    `C${cx - n * 0.6} 0 ${cx - n * 0.6} ${d} ${cx} ${d}`,
    `C${cx + n * 0.6} ${d} ${cx + n * 0.6} 0 ${cx + n} 0`,
    `L${w - CR} 0`,
    `Q${w} 0 ${w} ${CR}`,
    `L${w} ${BAR_H - CR}`,
    `Q${w} ${BAR_H} ${w - CR} ${BAR_H}`,
    `L${CR} ${BAR_H}`,
    `Q0 ${BAR_H} 0 ${BAR_H - CR}`,
    'Z',
  ].join(' ');
}

export default function BottomTabBar({ state, navigation }: BottomTabBarProps) {
  const insets = useSafeAreaInsets();
  const bottom = Math.max(insets.bottom, 12);
  const [width, setWidth] = useState(0);

  const onLayout = (e: LayoutChangeEvent) => setWidth(e.nativeEvent.layout.width);

  const currentRoute = state.routes[state.index]?.name ?? 'Home';
  const activeRoute = PRIMARY_ROUTES.includes(currentRoute) ? currentRoute : 'More';
  const activeIndex = Math.max(0, ITEMS.findIndex((i) => i.route === activeRoute));

  const pillW = width - SIDE * 2;
  const centres =
    width > 0 ? ITEMS.map((_, i) => INSET + ((pillW - INSET * 2) / (ITEMS.length - 1)) * i) : ITEMS.map(() => 0);
  const notchCx = centres[activeIndex];

  return (
    <View style={[styles.dock, { paddingTop: TOP_PAD, paddingBottom: bottom }]} onLayout={onLayout}>
      {width > 0 ? (
        <>
          <Svg
            width={pillW}
            height={BAR_H + 22}
            style={{ marginHorizontal: SIDE, backgroundColor: 'transparent' }}
          >
            <Defs>
              {/* Shadow follows the notched pill shape (no rectangular box). */}
              <Filter id="pillShadow" x="-20%" y="-20%" width="140%" height="170%">
                <FeDropShadow dx="0" dy="5" stdDeviation="5" floodColor="#1e3a8a" floodOpacity="0.35" />
              </Filter>
            </Defs>
            <Path d={barPath(pillW, notchCx)} fill={colors.primary} filter="url(#pillShadow)" />
          </Svg>

          {ITEMS.map((item, i) => {
            const focused = activeRoute === item.route;
            const target = state.routes.find((r) => r.name === item.route);
            const cx = SIDE + centres[i];

            const onPress = () => {
              const event = navigation.emit({ type: 'tabPress', target: target?.key, canPreventDefault: true });
              if (!event.defaultPrevented) navigation.navigate(item.route as never);
            };

            if (focused) {
              return (
                <Pressable
                  key={item.route}
                  onPress={onPress}
                  hitSlop={6}
                  style={[styles.activeBtn, { left: cx - RB, top: TOP_PAD + CCY - RB }]}
                >
                  <TabIcon name={item.icon} size={26} color="#fff" />
                </Pressable>
              );
            }
            return (
              <Pressable
                key={item.route}
                onPress={onPress}
                hitSlop={12}
                style={[styles.inactiveBtn, { left: cx - 24, top: TOP_PAD + (BAR_H - 48) / 2 }]}
              >
                <TabIcon name={item.icon} size={23} color="rgba(255,255,255,0.8)" />
              </Pressable>
            );
          })}
        </>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  dock: {
    backgroundColor: colors.bg,
  },
  inactiveBtn: {
    position: 'absolute',
    width: 48,
    height: 48,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
  },
  // Big circle, no border — the even gap comes from the concentric notch.
  // No Android `elevation` (it draws a light square box behind the circle on
  // our near-white background); iOS keeps a soft round shadow.
  activeBtn: {
    position: 'absolute',
    width: RB * 2,
    height: RB * 2,
    borderRadius: RB,
    backgroundColor: colors.primary, // blue circle; the light notch gap separates it from the blue bar
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 5 },
    shadowOpacity: 0.45,
    shadowRadius: 8,
    elevation: 10, // dark round shadow on Android (bar is blue now, so no white-box artifact)
  },
});
