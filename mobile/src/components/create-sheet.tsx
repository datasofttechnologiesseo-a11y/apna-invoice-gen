import { BottomSheet, RNHostView } from '@expo/ui';
import * as Haptics from 'expo-haptics';
import { Href, router } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, { useAnimatedStyle, withSpring } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, fonts, shadow } from '@/theme';

import { AppText } from './app-text';
import { Icon, IconName } from './icon';
import { PressableScale } from './pressable-scale';

type Action = { label: string; hint: string; icon: IconName; href: Href; bg: string; fg: string };

/** Everything the + button can create. One list, used by the sheet and by tests/docs. */
export const CREATE_ACTIONS: Action[] = [
  { label: 'Invoice', hint: 'GST tax invoice', icon: 'document-text', href: '/invoices/new', bg: colors.tealTint, fg: colors.teal },
  { label: 'Quotation', hint: 'Estimate', icon: 'chatbox-ellipses', href: '/quotations/new' as Href, bg: colors.amberTint, fg: colors.amberText },
  { label: 'Customer', hint: 'Add a party', icon: 'person-add', href: '/parties/new' as Href, bg: colors.slateTint, fg: colors.navy },
  { label: 'Item', hint: 'Goods, service', icon: 'cube', href: '/items/new' as Href, bg: colors.greenTint, fg: colors.greenText },
  { label: 'Purchase bill', hint: 'From a vendor', icon: 'cart', href: '/purchases/new' as Href, bg: colors.tealTint, fg: colors.tealDark },
  { label: 'Expense', hint: 'Rent, fuel…', icon: 'wallet', href: '/expenses/new' as Href, bg: colors.redTint, fg: colors.redText },
  { label: 'Cash memo', hint: 'Small buys', icon: 'receipt', href: '/cash-memos/new' as Href, bg: colors.amberTint, fg: colors.amberText },
  { label: 'Vendor', hint: 'Supplier', icon: 'storefront', href: '/vendors/new' as Href, bg: colors.slateTint, fg: colors.slateText },
];

/** Floating + button above the tab bar; opens the native create sheet. */
export function CreateFab({ visible }: { visible: boolean }) {
  const [open, setOpen] = useState(false);
  const insets = useSafeAreaInsets();
  const style = useAnimatedStyle(() => ({
    transform: [{ scale: withSpring(visible ? 1 : 0, { damping: 15, stiffness: 220 }) }],
  }));

  const go = (href: Href) => {
    setOpen(false);
    // let the sheet start closing before the next screen slides in
    setTimeout(() => router.push(href), 120);
  };

  return (
    <>
      <Animated.View pointerEvents={visible ? 'box-none' : 'none'} style={[styles.fabWrap, { bottom: Math.max(insets.bottom, 12) + 84 }, style]}>
        <PressableScale
          scaleTo={0.9}
          accessibilityRole="button"
          accessibilityLabel="Create new"
          onPress={() => {
            Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
            setOpen(true);
          }}
          style={[styles.fab, shadow.card]}
        >
          <Icon name="add" size={30} color={colors.navy} />
        </PressableScale>
      </Animated.View>
      <BottomSheet isPresented={open} onDismiss={() => setOpen(false)} containerColor={colors.surface} contentPadding={0}>
        <RNHostView matchContents={{ vertical: true }}>
          <View style={[styles.sheet, { paddingBottom: insets.bottom + 12 }]}>
            <AppText variant="h2">Create new</AppText>
            <View style={styles.grid}>
              {CREATE_ACTIONS.map((a) => (
                <PressableScale key={a.label} accessibilityRole="button" accessibilityLabel={`New ${a.label}`} onPress={() => go(a.href)} style={styles.tile}>
                  <View style={[styles.tileIcon, { backgroundColor: a.bg }]}>
                    <Icon name={a.icon} size={22} color={a.fg} />
                  </View>
                  <AppText style={{ fontFamily: fonts.semibold, fontSize: 13 }} numberOfLines={1}>
                    {a.label}
                  </AppText>
                  <AppText variant="small" color={colors.textMuted} numberOfLines={1} style={{ fontSize: 11 }}>
                    {a.hint}
                  </AppText>
                </PressableScale>
              ))}
            </View>
          </View>
        </RNHostView>
      </BottomSheet>
    </>
  );
}

const styles = StyleSheet.create({
  fabWrap: { position: 'absolute', right: 20 },
  fab: { width: 60, height: 60, borderRadius: 22, backgroundColor: colors.amber, alignItems: 'center', justifyContent: 'center', borderWidth: 3, borderColor: colors.surface },
  sheet: { paddingHorizontal: 20, paddingTop: 8, gap: 16 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', rowGap: 18 },
  tile: { width: '25%', alignItems: 'center', gap: 4, paddingHorizontal: 2 },
  tileIcon: { width: 54, height: 54, borderRadius: 18, alignItems: 'center', justifyContent: 'center', marginBottom: 2 },
});
