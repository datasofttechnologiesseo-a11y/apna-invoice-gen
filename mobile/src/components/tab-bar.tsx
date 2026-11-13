import * as Haptics from 'expo-haptics';
import type { BottomTabBarProps } from 'expo-router/js-tabs';
import { Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, fonts } from '@/theme';

import { AppText } from './app-text';
import { Icon, IconName } from './icon';

const ICONS: Record<string, [IconName, IconName]> = {
  index: ['home', 'home-outline'],
  invoices: ['document-text', 'document-text-outline'],
  quotations: ['chatbox-ellipses', 'chatbox-ellipses-outline'],
  parties: ['people', 'people-outline'],
  more: ['menu', 'menu-outline'],
};

/** Navy floating bar; the active tab rises into an amber bubble (design direction B). */
export function TabBar({ state, descriptors, emitter, navigateToTab }: BottomTabBarProps) {
  const insets = useSafeAreaInsets();
  return (
    <View pointerEvents="box-none" style={[styles.wrap, { paddingBottom: Math.max(insets.bottom, 12) }]}>
      <View style={styles.bar}>
        {state.routes.map((route, i) => {
          const focused = state.index === i;
          const { options } = descriptors[route.key]!;
          const label = (options.title ?? route.name) as string;
          const [on, off] = ICONS[route.name] ?? ['ellipse', 'ellipse-outline'];
          return (
            <Pressable
              key={route.key}
              accessibilityRole="tab"
              accessibilityState={{ selected: focused }}
              accessibilityLabel={label}
              onPress={() => {
                const e = emitter.emit({ type: 'tabPress', target: route.key, canPreventDefault: true });
                if (!focused && !e.defaultPrevented) {
                  Haptics.selectionAsync().catch(() => {});
                  navigateToTab(route.key);
                }
              }}
              style={styles.item}
            >
              {focused ? (
                <View style={styles.bubble}>
                  <Icon name={on} size={22} color={colors.navy} />
                </View>
              ) : (
                <Icon name={off} size={22} color="#B8C7D6" />
              )}
              <AppText style={{ fontFamily: focused ? fonts.bold : fonts.semibold, fontSize: 11 }} color={focused ? colors.onDark : '#B8C7D6'} numberOfLines={1}>
                {label}
              </AppText>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { position: 'absolute', left: 0, right: 0, bottom: 0, paddingHorizontal: 12 },
  bar: {
    height: 68,
    borderRadius: 26,
    backgroundColor: colors.navy,
    flexDirection: 'row',
    alignItems: 'center',
    shadowColor: colors.navy,
    shadowOpacity: 0.45,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 10 },
    elevation: 16,
  },
  item: { flex: 1, height: 68, alignItems: 'center', justifyContent: 'center', gap: 4 },
  bubble: {
    marginTop: -34,
    width: 54,
    height: 54,
    borderRadius: 27,
    backgroundColor: colors.amber,
    borderWidth: 6,
    borderColor: colors.ground,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
