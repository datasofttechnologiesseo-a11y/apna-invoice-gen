import { Image } from 'expo-image';
import { Redirect, router } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useRef, useState } from 'react';
import { NativeScrollEvent, NativeSyntheticEvent, ScrollView, ScrollViewInstance, StyleSheet, useWindowDimensions, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AppText } from '@/components/app-text';
import { Button } from '@/components/button';
import { useAuth } from '@/auth/auth-context';
import { colors, fonts, shadow } from '@/theme';

const SLIDES = [
  {
    image: require('@/assets/images/onboarding-create.jpg'),
    title: 'GST invoices in 30 seconds',
    body: 'Pick a customer, add items, done. CGST, SGST or IGST is worked out for you — the same invoice your CA sees on the web.',
  },
  {
    image: require('@/assets/images/onboarding-share.jpg'),
    title: 'Share on WhatsApp, get paid by UPI',
    body: 'Send the PDF or a link in one tap. Your UPI details print on the bill, and reminders go out when a payment is late.',
  },
  {
    image: require('@/assets/images/onboarding-reports.jpg'),
    title: 'GSTR-1, 3B and P&L ready for your CA',
    body: 'Month-end in two taps. Your data is backed up and works on apnainvoice.com too.',
  },
];

export default function Welcome() {
  const { seenWelcome, markWelcomeSeen } = useAuth();
  const { width } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const [page, setPage] = useState(0);
  const pager = useRef<ScrollViewInstance>(null);

  if (seenWelcome) return <Redirect href="/sign-in" />;

  const last = page === SLIDES.length - 1;
  const go = (path: '/sign-in' | '/sign-up') => {
    markWelcomeSeen();
    router.replace(path);
  };
  const onScroll = (e: NativeSyntheticEvent<NativeScrollEvent>) => setPage(Math.round(e.nativeEvent.contentOffset.x / width));

  return (
    <View style={styles.root}>
      <StatusBar style="dark" />
      <View style={[styles.top, { paddingTop: insets.top + 10 }]}>
        <View style={styles.brand}>
          <Image source={require('@/assets/images/icon.png')} style={styles.logo} accessibilityIgnoresInvertColors />
          <AppText style={{ fontFamily: fonts.displayHeavy, fontSize: 17 }}>
            Apna <AppText style={{ fontFamily: fonts.displayHeavy, fontSize: 17 }} color={colors.teal}>Invoice</AppText>
          </AppText>
        </View>
        {!last ? <Button label="Skip" kind="ghost" size="sm" onPress={() => go('/sign-in')} /> : null}
      </View>

      <ScrollView ref={pager} horizontal pagingEnabled showsHorizontalScrollIndicator={false} onMomentumScrollEnd={onScroll} style={{ flexGrow: 0 }}>
        {SLIDES.map((s) => (
          <View key={s.title} style={{ width, alignItems: 'center' }}>
            <Image source={s.image} style={{ width: Math.min(width - 30, 380), aspectRatio: 1 }} contentFit="cover" accessibilityLabel={s.title} />
          </View>
        ))}
      </ScrollView>

      <View style={[styles.sheet, { paddingBottom: insets.bottom + 20 }]}>
        <View style={styles.dots} accessibilityLabel={`Page ${page + 1} of ${SLIDES.length}`}>
          {SLIDES.map((_, i) => (
            <View key={i} style={[styles.dot, i === page ? styles.dotOn : null]} />
          ))}
        </View>
        <AppText variant="h1" style={{ fontSize: 27, lineHeight: 32 }}>
          {SLIDES[page]!.title}
        </AppText>
        <AppText color="#475467" style={{ fontSize: 15, lineHeight: 23 }}>
          {SLIDES[page]!.body}
        </AppText>
        <View style={{ flex: 1 }} />
        {last ? (
          <>
            <Button label="Create free account" onPress={() => go('/sign-up')} />
            <Button label="I already have an account" kind="soft" onPress={() => go('/sign-in')} />
          </>
        ) : (
          <Button
            label="Next"
            icon="arrow-forward"
            onPress={() => {
              // Programmatic scrolls don't fire onMomentumScrollEnd on iOS, so move the page here.
              const next = Math.min(page + 1, SLIDES.length - 1);
              setPage(next);
              pager.current?.scrollTo({ x: next * width, animated: true });
            }}
          />
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.tealTint },
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingBottom: 6 },
  brand: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  logo: { width: 32, height: 32, borderRadius: 9 },
  sheet: { flex: 1, backgroundColor: colors.surface, borderTopLeftRadius: 32, borderTopRightRadius: 32, padding: 24, gap: 12, ...shadow.card },
  dots: { flexDirection: 'row', gap: 6, marginBottom: 4 },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.tealLight },
  dotOn: { width: 24, backgroundColor: colors.teal },
});
