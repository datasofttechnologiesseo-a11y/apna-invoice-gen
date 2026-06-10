import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { BottomTabBarProps } from '@react-navigation/bottom-tabs';
import { colors } from '../theme';

type IconName = keyof typeof Ionicons.glyphMap;

interface TabItem {
  route: string;
  label: string;
  active: IconName;
  inactive: IconName;
}

// Only these four appear in the bar. Everything else (Quotes, Products, Finance,
// account/settings…) lives behind "More", which stays highlighted whenever the
// active screen is one of those secondary destinations.
const ITEMS: TabItem[] = [
  { route: 'Home', label: 'Home', active: 'home', inactive: 'home-outline' },
  { route: 'Invoices', label: 'Invoices', active: 'receipt', inactive: 'receipt-outline' },
  { route: 'Customers', label: 'Customers', active: 'people', inactive: 'people-outline' },
  { route: 'More', label: 'More', active: 'ellipsis-horizontal', inactive: 'ellipsis-horizontal-outline' },
];

const PRIMARY_ROUTES = ['Home', 'Invoices', 'Customers'];

export default function BottomTabBar({ state, navigation }: BottomTabBarProps) {
  const insets = useSafeAreaInsets();

  const currentRoute = state.routes[state.index]?.name ?? 'Home';
  // Secondary routes (Quotes/Products/Finance/…) light up the "More" tab.
  const activeRoute = PRIMARY_ROUTES.includes(currentRoute) ? currentRoute : 'More';

  return (
    <View style={[styles.bar, { paddingBottom: Math.max(insets.bottom, 10) }]}>
      {ITEMS.map((item) => {
        const focused = activeRoute === item.route;
        const target = state.routes.find((r) => r.name === item.route);

        const onPress = () => {
          const event = navigation.emit({
            type: 'tabPress',
            target: target?.key,
            canPreventDefault: true,
          });
          if (!event.defaultPrevented) {
            navigation.navigate(item.route as never);
          }
        };

        return (
          <Pressable key={item.route} style={styles.item} onPress={onPress} hitSlop={6}>
            <View style={[styles.iconWrap, focused && styles.iconWrapActive]}>
              <Ionicons
                name={focused ? item.active : item.inactive}
                size={22}
                color={focused ? colors.primary : colors.muted}
              />
            </View>
            <Text style={[styles.label, focused ? styles.labelActive : null]} numberOfLines={1}>
              {item.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    backgroundColor: colors.card,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    paddingTop: 8,
    // Soft lift above the content.
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: -2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 8,
  },
  item: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  iconWrap: {
    paddingHorizontal: 18,
    paddingVertical: 5,
    borderRadius: 999,
    marginBottom: 3,
  },
  iconWrapActive: { backgroundColor: colors.primary + '18' },
  label: { fontSize: 11, fontWeight: '600', color: colors.muted },
  labelActive: { color: colors.primary, fontWeight: '700' },
});
