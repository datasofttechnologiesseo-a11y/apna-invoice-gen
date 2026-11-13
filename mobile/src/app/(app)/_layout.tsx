import { Stack } from 'expo-router';

import { colors } from '@/theme';

/** Create/edit forms slide up as modals; everything else pushes. */
const MODALS = [
  'invoices/new',
  'invoices/[id]/payment',
  'invoices/[id]/credit-note',
  'invoices/[id]/edit',
  'quotations/new',
  'quotations/[id]/edit',
  'parties/new',
  'parties/[id]/edit',
  'items/new',
  'expenses/new',
  'cash-memos/new',
  'cash-memos/[id]/edit',
  'purchases/new',
  'purchases/[id]/edit',
  'purchases/[id]/payment',
  'vendors/new',
  'vendors/[id]/edit',
];

export default function AppLayout() {
  return (
    <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: colors.ground } }}>
      <Stack.Screen name="(tabs)" />
      {MODALS.map((name) => (
        <Stack.Screen key={name} name={name} options={{ presentation: 'modal' }} />
      ))}
      <Stack.Screen name="invoices/[id]/issued" options={{ presentation: 'fullScreenModal', gestureEnabled: false }} />
    </Stack>
  );
}
