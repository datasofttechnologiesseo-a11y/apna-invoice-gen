import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import { TabIcon } from './icons';
import { colors } from '../theme';

/**
 * Blue app header: invoice logo on the top-left (taps to Home), settings gear on
 * the top-right (jumps to Settings). Drop it at the top of a main tab screen.
 * An optional `right` action sits just left of the gear.
 */
export function AppHeader({
  title,
  subtitle,
  right,
}: {
  title: string;
  subtitle?: string;
  right?: React.ReactNode;
}) {
  const insets = useSafeAreaInsets();
  const navigation = useNavigation<{ navigate: (name: string) => void }>();

  return (
    <View style={[styles.header, { paddingTop: insets.top + 10 }]}>
      <Pressable hitSlop={10} style={styles.logo} onPress={() => navigation.navigate('Home')}>
        <TabIcon name="home" size={22} color={colors.primary} />
      </Pressable>
      <View style={{ flex: 1 }}>
        <Text style={styles.title} numberOfLines={1}>
          {title}
        </Text>
        {subtitle ? (
          <Text style={styles.subtitle} numberOfLines={1}>
            {subtitle}
          </Text>
        ) : null}
      </View>
      {right ? <View style={styles.right}>{right}</View> : null}
      <Pressable hitSlop={10} style={styles.gear} onPress={() => navigation.navigate('Settings')}>
        <TabIcon name="settings" size={20} color="#fff" />
      </Pressable>
    </View>
  );
}

/** A pill action button styled to sit in the blue header's right slot. */
export function HeaderAction({ label, onPress }: { label: string; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} hitSlop={8} style={styles.action}>
      <Text style={styles.actionText}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  header: {
    backgroundColor: colors.primary,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingBottom: 14,
  },
  logo: {
    width: 38,
    height: 38,
    borderRadius: 11,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  gear: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: 'rgba(255,255,255,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 12,
  },
  title: { fontSize: 20, fontWeight: '800', color: '#fff' },
  subtitle: { fontSize: 13, color: 'rgba(255,255,255,0.85)', marginTop: 2 },
  right: { marginLeft: 12 },
  action: {
    backgroundColor: 'rgba(255,255,255,0.22)',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 10,
  },
  actionText: { color: '#fff', fontWeight: '700', fontSize: 14 },
});
