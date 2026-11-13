/**
 * Design tokens — the single source of colour, type, spacing and shape.
 * Values come from the web dashboard (tailwind.config.js) and
 * docs/mobile/design/README.md. Never hard-code these in a screen.
 */
export const colors = {
  teal: '#167C88',
  tealDark: '#13636C',
  tealDeep: '#114E55',
  tealTint: '#EEFBFC',
  tealSoft: '#D4F4F7',
  tealLight: '#A9E8EF',
  tealBright: '#70D5E1',
  navy: '#0E2843',
  amber: '#F59E0B',
  amberTint: '#FEF3C7',
  amberText: '#92400E',
  green: '#16A34A',
  greenTint: '#DCFCE7',
  greenText: '#166534',
  red: '#DC2626',
  redTint: '#FEE2E2',
  redText: '#B91C1C',
  ground: '#F3F5F7',
  surface: '#FFFFFF',
  text: '#0E2843',
  textBody: '#344054',
  textMuted: '#5B6576',
  border: '#E4E7EC',
  borderStrong: '#878FA0',
  divider: '#EEF1F4',
  slateTint: '#E8EDF2',
  slateText: '#334155',
  onDark: '#FFFFFF',
  onDarkMuted: '#D4F4F7',
  scrim: 'rgba(10,47,51,0.55)',
} as const;

export const gradient = {
  hero: ['#167C88', '#13636C', '#0E2843'] as const,
  heroLocations: [0, 0.5, 1] as const,
};

export const fonts = {
  body: 'Inter_400Regular',
  medium: 'Inter_500Medium',
  semibold: 'Inter_600SemiBold',
  bold: 'Inter_700Bold',
  display: 'PlusJakartaSans_700Bold',
  displayHeavy: 'PlusJakartaSans_800ExtraBold',
} as const;

export const space = { xs: 4, sm: 8, md: 12, lg: 16, xl: 20, xxl: 24 } as const;

export const radius = { sm: 10, md: 14, lg: 18, xl: 22, sheet: 28, pill: 999 } as const;

export const type = {
  hero: { fontFamily: fonts.displayHeavy, fontSize: 34, letterSpacing: -0.6 },
  h1: { fontFamily: fonts.displayHeavy, fontSize: 26, letterSpacing: -0.4 },
  h2: { fontFamily: fonts.display, fontSize: 18 },
  h3: { fontFamily: fonts.display, fontSize: 15 },
  body: { fontFamily: fonts.body, fontSize: 15 },
  label: { fontFamily: fonts.semibold, fontSize: 13 },
  small: { fontFamily: fonts.body, fontSize: 12 },
  overline: { fontFamily: fonts.bold, fontSize: 12, letterSpacing: 0.9, textTransform: 'uppercase' as const },
} as const;

export const shadow = {
  card: {
    shadowColor: colors.navy,
    shadowOpacity: 0.12,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 8 },
    elevation: 4,
  },
  soft: {
    shadowColor: colors.navy,
    shadowOpacity: 0.05,
    shadowRadius: 4,
    shadowOffset: { width: 0, height: 1 },
    elevation: 1,
  },
} as const;

/** Bottom space screens leave for the floating tab bar. */
export const TAB_BAR_HEIGHT = 96;
