import { Text, TextProps, TextStyle } from 'react-native';

import { colors, fonts, type } from '@/theme';

type Variant = keyof typeof type | 'money' | 'moneyLg';

const variants: Record<Variant, TextStyle> = {
  ...type,
  money: { fontFamily: fonts.bold, fontSize: 15, fontVariant: ['tabular-nums'] },
  moneyLg: { fontFamily: fonts.displayHeavy, fontSize: 22, fontVariant: ['tabular-nums'] },
};

export type AppTextProps = TextProps & { variant?: Variant; color?: string; align?: TextStyle['textAlign'] };

/** The only Text in the app — fonts and sizes come from theme tokens. */
export function AppText({ variant = 'body', color = colors.text, align, style, ...rest }: AppTextProps) {
  return <Text maxFontSizeMultiplier={1.6} style={[variants[variant], { color, textAlign: align }, style]} {...rest} />;
}
