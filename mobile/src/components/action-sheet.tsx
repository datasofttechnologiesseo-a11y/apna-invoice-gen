import { BottomSheet, RNHostView } from '@expo/ui';
import { StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, fonts } from '@/theme';

import { AppText } from './app-text';
import { Icon, IconName } from './icon';
import { PressableScale } from './pressable-scale';

export type SheetAction = { key: string; label: string; hint?: string; icon: IconName; danger?: boolean; onPress: () => void };

/**
 * Native bottom sheet with a list of actions ("more" menus, export formats,
 * team member options). Closes itself before running the chosen action.
 */
export function ActionSheet({ visible, title, subtitle, actions, onClose }: { visible: boolean; title: string; subtitle?: string; actions: SheetAction[]; onClose: () => void }) {
  const insets = useSafeAreaInsets();
  const pick = (a: SheetAction) => {
    onClose();
    // let the sheet start closing before an alert / share sheet / next screen opens
    setTimeout(a.onPress, 250);
  };
  return (
    <BottomSheet isPresented={visible} onDismiss={onClose} containerColor={colors.surface} contentPadding={0}>
      <RNHostView matchContents={{ vertical: true }}>
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 12 }]}>
          <View style={{ gap: 2, marginBottom: 6 }}>
            <AppText variant="h2" accessibilityRole="header">
              {title}
            </AppText>
            {subtitle ? (
              <AppText variant="small" color={colors.textMuted}>
                {subtitle}
              </AppText>
            ) : null}
          </View>
          {actions.map((a, i) => (
            <PressableScale
              key={a.key}
              scaleTo={0.97}
              accessibilityRole="button"
              accessibilityLabel={a.hint ? `${a.label}. ${a.hint}` : a.label}
              onPress={() => pick(a)}
              style={[styles.row, i < actions.length - 1 ? styles.divider : null]}
            >
              <View style={[styles.icon, { backgroundColor: a.danger ? colors.redTint : colors.tealTint }]}>
                <Icon name={a.icon} size={19} color={a.danger ? colors.redText : colors.teal} />
              </View>
              <View style={{ flex: 1, gap: 1 }}>
                <AppText style={{ fontFamily: fonts.semibold, fontSize: 15 }} color={a.danger ? colors.redText : colors.text}>
                  {a.label}
                </AppText>
                {a.hint ? (
                  <AppText variant="small" color={colors.textMuted}>
                    {a.hint}
                  </AppText>
                ) : null}
              </View>
            </PressableScale>
          ))}
        </View>
      </RNHostView>
    </BottomSheet>
  );
}

const styles = StyleSheet.create({
  sheet: { paddingHorizontal: 20, paddingTop: 22, gap: 2 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 58, paddingVertical: 8 },
  divider: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.border },
  icon: { width: 38, height: 38, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
});
