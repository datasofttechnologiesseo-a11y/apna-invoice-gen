import { ComponentProps } from 'react';
import { Pressable, StyleProp, ViewStyle } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withSpring } from 'react-native-reanimated';

const AnimatedPressable = Animated.createAnimatedComponent(Pressable);

/** Spring used by every tap in the app, so presses feel the same everywhere. */
export const PRESS_SPRING = { damping: 18, stiffness: 420, mass: 0.6 };

type Props = Omit<ComponentProps<typeof Pressable>, 'style'> & {
  style?: StyleProp<ViewStyle>;
  /** How far it shrinks while held (default 0.96). */
  scaleTo?: number;
};

/** Pressable that springs down while held and back on release (runs on the UI thread). */
export function PressableScale({ style, scaleTo = 0.96, onPressIn, onPressOut, disabled, ...rest }: Props) {
  const s = useSharedValue(1);
  const anim = useAnimatedStyle(() => ({ transform: [{ scale: s.value }] }));
  return (
    <AnimatedPressable
      {...rest}
      disabled={disabled}
      onPressIn={(e) => {
        if (!disabled) s.set(withSpring(scaleTo, PRESS_SPRING));
        onPressIn?.(e);
      }}
      onPressOut={(e) => {
        s.set(withSpring(1, PRESS_SPRING));
        onPressOut?.(e);
      }}
      style={[style, anim]}
    />
  );
}
