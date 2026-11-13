import { Stack } from 'expo-router';

import { colors } from '@/theme';

export default function AppLayout() {
  return (
    <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: colors.ground } }}>
      <Stack.Screen name="(tabs)" />
      <Stack.Screen name="invoices/new" options={{ presentation: 'modal' }} />
      <Stack.Screen name="invoices/[id]/payment" options={{ presentation: 'modal' }} />
      <Stack.Screen name="invoices/[id]/issued" options={{ presentation: 'fullScreenModal', gestureEnabled: false }} />
    </Stack>
  );
}
