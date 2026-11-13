import { Pressable, StyleSheet, View } from 'react-native';

import { colors, fonts, radius } from '@/theme';

import { AppText } from './app-text';
import { Icon } from './icon';

/**
 * A form field that opens a picker (state, unit…). Looks like TextField so
 * forms stay even; pair it with PickerSheet.
 */
export function SelectField({ label, value, placeholder, error, hint, onPress }: { label: string; value?: string | null; placeholder: string; error?: string; hint?: string; onPress: () => void }) {
  return (
    <View style={styles.wrap}>
      <AppText variant="label" color={colors.textBody}>
        {label}
      </AppText>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${label}: ${value || placeholder}`}
        onPress={onPress}
        style={({ pressed }) => [styles.box, { borderColor: error ? colors.red : colors.borderStrong, opacity: pressed ? 0.7 : 1 }]}
      >
        <AppText style={styles.text} color={value ? colors.text : colors.textMuted} numberOfLines={1}>
          {value || placeholder}
        </AppText>
        <Icon name="chevron-down" color={colors.textMuted} />
      </Pressable>
      {error ? (
        <AppText variant="small" color={colors.redText} accessibilityLiveRegion="polite">
          {error}
        </AppText>
      ) : hint ? (
        <AppText variant="small" color={colors.textMuted}>
          {hint}
        </AppText>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: 6 },
  box: { minHeight: 52, borderWidth: 1.5, borderRadius: radius.md, backgroundColor: colors.surface, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 10 },
  text: { flex: 1, fontFamily: fonts.medium, fontSize: 16 },
});
