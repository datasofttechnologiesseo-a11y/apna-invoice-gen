import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-controller';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AppText } from '@/components/app-text';
import { Button } from '@/components/button';
import { colors, gradient } from '@/theme';

/** Gradient top + white sheet used by log in and create account. Keyboard-aware. */
export function AuthShell({ title, subtitle, back, children }: { title: string; subtitle: string; back?: boolean; children: ReactNode }) {
  const insets = useSafeAreaInsets();
  return (
    <LinearGradient colors={gradient.hero} locations={gradient.heroLocations} style={{ flex: 1 }}>
      <StatusBar style="light" />
      <KeyboardAwareScrollView bottomOffset={24} keyboardShouldPersistTaps="handled" contentContainerStyle={{ flexGrow: 1 }} showsVerticalScrollIndicator={false}>
        <View style={[styles.top, { paddingTop: insets.top + 16 }]}>
          {back ? (
            <Button kind="glass" size="md" icon="chevron-back" accessibilityLabel="Back" onPress={() => (router.canGoBack() ? router.back() : router.replace('/sign-in'))} style={{ alignSelf: 'flex-start' }} />
          ) : (
            <Image source={require('@/assets/images/icon.png')} style={styles.logo} accessibilityLabel="Apna Invoice logo" />
          )}
          <AppText variant="h1" color={colors.onDark} accessibilityRole="header" style={{ fontSize: 28 }}>
            {title}
          </AppText>
          <AppText color={colors.onDarkMuted}>{subtitle}</AppText>
        </View>
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 24 }]}>{children}</View>
      </KeyboardAwareScrollView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  top: { paddingHorizontal: 24, paddingBottom: 28, gap: 10 },
  logo: { width: 56, height: 56, borderRadius: 16, marginBottom: 4 },
  sheet: { flex: 1, backgroundColor: colors.surface, borderTopLeftRadius: 32, borderTopRightRadius: 32, padding: 24, gap: 16 },
});
