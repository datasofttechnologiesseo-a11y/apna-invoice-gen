import React from 'react';
import Svg, { Circle, Path } from 'react-native-svg';

export type IconName =
  | 'home'
  | 'invoices'
  | 'quotes'
  | 'customers'
  | 'products'
  | 'finance'
  | 'settings'
  | 'more';

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
    case 'quotes':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24">
          <Path d="M6 3h8l4 4v14H6z" {...stroke} />
          <Path d="M14 3v4h4" {...stroke} />
          <Path d="M9 12.5h6" {...stroke} />
          <Path d="M9 16h6" {...stroke} />
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
    case 'products':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24">
          <Path d="M12 2.5 21 7v10l-9 4.5L3 17V7z" {...stroke} />
          <Path d="M3 7l9 4.5L21 7" {...stroke} />
          <Path d="M12 11.5V21.5" {...stroke} />
        </Svg>
      );
    case 'finance':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24">
          <Path d="M3.5 20.5h17" {...stroke} />
          <Path d="M7 20.5v-7" {...stroke} />
          <Path d="M12 20.5V5" {...stroke} />
          <Path d="M17 20.5v-10" {...stroke} />
        </Svg>
      );
    case 'settings':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24">
          <Circle cx="12" cy="12" r="3" {...stroke} />
          <Path
            d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"
            {...stroke}
          />
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
