import React from 'react';
import Svg, { Circle, Path } from 'react-native-svg';

export type IconName = 'home' | 'invoices' | 'customers' | 'more';

/**
 * Thin monochrome line icons for the bottom tab bar, drawn inline with
 * react-native-svg (a core Expo module) so there's no font/lazy-require
 * dependency to break the bundler. `color` tints the whole glyph.
 */
export function TabIcon({ name, size = 24, color }: { name: IconName; size?: number; color: string }) {
  const stroke = {
    stroke: color,
    strokeWidth: 1.8,
    fill: 'none',
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
  };

  switch (name) {
    case 'home':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24">
          <Path d="M3 10.75 12 3l9 7.75" {...stroke} />
          <Path d="M5 9.5V21h14V9.5" {...stroke} />
          <Path d="M9.5 21v-6h5v6" {...stroke} />
        </Svg>
      );
    case 'invoices':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24">
          <Path d="M6 2.5h12v19l-3-2-3 2-3-2-3 2z" {...stroke} />
          <Path d="M9.5 8h5" {...stroke} />
          <Path d="M9.5 12h5" {...stroke} />
        </Svg>
      );
    case 'customers':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24">
          <Circle cx="9" cy="8" r="3.3" {...stroke} />
          <Path d="M3.4 20a5.6 5.6 0 0 1 11.2 0" {...stroke} />
          <Path d="M16 5.4a3.2 3.2 0 0 1 0 5.9" {...stroke} />
          <Path d="M15.8 14.7a5.6 5.6 0 0 1 4.8 5.3" {...stroke} />
        </Svg>
      );
    case 'more':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24">
          <Circle cx="5" cy="12" r="1.75" fill={color} />
          <Circle cx="12" cy="12" r="1.75" fill={color} />
          <Circle cx="19" cy="12" r="1.75" fill={color} />
        </Svg>
      );
  }
}
