import { forwardRef, ReactNode, useCallback, useRef, useState } from 'react';
import { StyleSheet, TextInput, TextInputInstance, TextInputProps, View } from 'react-native';

import { useDelayedFocus } from '@/hooks/use-delayed-focus';
import { colors, fonts, radius } from '@/theme';

import { AppText } from './app-text';

export type TextFieldProps = TextInputProps & {
  label: string;
  error?: string;
  hint?: string;
  prefix?: ReactNode;
  suffix?: ReactNode;
};

/** Labelled input with the server's 422 message shown underneath. */
export const TextField = forwardRef<TextInputInstance, TextFieldProps>(function TextField(
  { label, error, hint, prefix, suffix, style, onFocus, onBlur, autoFocus, ...rest },
  ref,
) {
  const [focused, setFocused] = useState(false);
  // autoFocus is applied after the screen has opened (see useDelayedFocus).
  const inner = useRef<TextInputInstance | null>(null);
  useDelayedFocus(inner, autoFocus);
  const setRef = useCallback(
    (node: TextInputInstance | null) => {
      inner.current = node;
      if (typeof ref === 'function') ref(node);
      else if (ref) ref.current = node;
    },
    [ref],
  );
  const borderColor = error ? colors.red : focused ? colors.teal : colors.borderStrong;
  return (
    <View style={styles.wrap}>
      <AppText variant="label" color={colors.textBody}>
        {label}
      </AppText>
      {/* Focus shows as a border colour only. Never toggle shadow/elevation here: on
          Android (Fabric) that rebuilds this view, the input loses focus, and typing
          lands in the first field on the screen or nowhere. */}
      <View style={[styles.box, { borderColor }]}>
        {prefix}
        <TextInput
          ref={setRef}
          accessibilityLabel={label}
          placeholderTextColor="#8A94A6"
          style={[styles.input, style]}
          onFocus={(e) => {
            setFocused(true);
            onFocus?.(e);
          }}
          onBlur={(e) => {
            setFocused(false);
            onBlur?.(e);
          }}
          maxFontSizeMultiplier={1.4}
          {...rest}
        />
        {suffix}
      </View>
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
});

const styles = StyleSheet.create({
  wrap: { gap: 6 },
  box: {
    minHeight: 52,
    borderWidth: 1.5,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    gap: 10,
  },
  input: { flex: 1, fontFamily: fonts.medium, fontSize: 16, color: colors.text, paddingVertical: 12 },
});
