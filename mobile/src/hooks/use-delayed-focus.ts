import { RefObject, useEffect } from 'react';
import { Platform } from 'react-native';

/**
 * Focus an input once its screen or sheet has finished sliding in.
 *
 * Native `autoFocus` fires during the open animation. On Android the keyboard
 * then opens mid-transition, the window resizes, and focus hops from field to
 * field so typed letters land in the wrong inputs (or nowhere). Every input in
 * the app uses this instead of `autoFocus`.
 */
export function useDelayedFocus(ref: RefObject<{ focus: () => void } | null>, enabled: boolean | undefined) {
  useEffect(() => {
    if (!enabled) return;
    const t = setTimeout(() => ref.current?.focus(), Platform.OS === 'android' ? 450 : 300);
    return () => clearTimeout(t);
  }, [enabled, ref]);
}
