import { ReactNode } from 'react';
import { Modal, Pressable, StyleSheet, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-controller';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import type { TeamRole } from '@/api/workspace';
import { AppText } from '@/components/app-text';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { colors, fonts, radius } from '@/theme';

/** Radio list of team roles with the web's help text under each (CompanyMember::ROLES). */
export function RoleOptions({ roles, value, onChange, error }: { roles: TeamRole[]; value: string; onChange: (key: string) => void; error?: string }) {
  return (
    <View style={{ gap: 8 }} accessibilityRole="radiogroup">
      {roles.map((r) => {
        const on = r.key === value;
        return (
          <Pressable
            key={r.key}
            accessibilityRole="radio"
            accessibilityState={{ checked: on }}
            accessibilityLabel={`${r.label}. ${r.help}`}
            onPress={() => onChange(r.key)}
            style={[styles.option, on ? styles.optionOn : null]}
          >
            <Icon name={on ? 'radio-button-on' : 'radio-button-off'} size={22} color={on ? colors.teal : colors.borderStrong} />
            <View style={{ flex: 1, gap: 2 }}>
              <AppText style={{ fontFamily: fonts.semibold, fontSize: 15 }}>{r.label}</AppText>
              <AppText variant="small" color={colors.textMuted}>
                {r.help}
              </AppText>
            </View>
          </Pressable>
        );
      })}
      {error ? (
        <AppText variant="small" color={colors.redText}>
          {error}
        </AppText>
      ) : null}
    </View>
  );
}

/** Page sheet holding a short form (invite, change role); the keyboard never covers the button. */
export function FormSheet({ visible, title, subtitle, onClose, footer, children }: { visible: boolean; title: string; subtitle?: string; onClose: () => void; footer: ReactNode; children: ReactNode }) {
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={onClose}>
      <View style={[styles.root, { paddingBottom: insets.bottom + 12 }]}>
        <View style={styles.top}>
          <View style={{ flex: 1, gap: 2 }}>
            <AppText variant="h2" accessibilityRole="header">
              {title}
            </AppText>
            {subtitle ? (
              <AppText variant="small" color={colors.textMuted}>
                {subtitle}
              </AppText>
            ) : null}
          </View>
          <Button kind="outline" size="md" icon="close" accessibilityLabel="Close" onPress={onClose} />
        </View>
        <KeyboardAwareScrollView bottomOffset={24} keyboardShouldPersistTaps="handled" contentContainerStyle={{ padding: 16, gap: 14 }}>
          {children}
          {footer}
        </KeyboardAwareScrollView>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.ground },
  top: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 16, paddingTop: 20, backgroundColor: colors.surface },
  option: { flexDirection: 'row', gap: 12, alignItems: 'flex-start', padding: 14, borderRadius: radius.md, borderWidth: 1.5, borderColor: colors.border, backgroundColor: colors.surface },
  optionOn: { borderColor: colors.teal, backgroundColor: colors.tealTint },
});
