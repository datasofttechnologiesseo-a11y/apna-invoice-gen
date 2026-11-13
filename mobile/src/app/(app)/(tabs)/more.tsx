import Constants from 'expo-constants';
import { Href, router } from 'expo-router';
import * as Updates from 'expo-updates';
import * as WebBrowser from 'expo-web-browser';
import { Alert, StyleSheet, View } from 'react-native';

import type { Can } from '@/api/types';
import { useAuth } from '@/auth/auth-context';
import { AppText } from '@/components/app-text';
import { Avatar, Card, IconTile, Overline, Row } from '@/components/blocks';
import { Icon, IconName } from '@/components/icon';
import { PressableScale } from '@/components/pressable-scale';
import { Screen } from '@/components/screen';
import { LINKS } from '@/config';
import { useCan } from '@/hooks/role';
import { colors, fonts, shadow } from '@/theme';

type HubItem = {
  icon: IconName;
  label: string;
  sub: string;
  href: Href;
  bg: string;
  fg: string;
  area?: keyof Can;
};

/** Every module in one place — mirrors the web sidebar. */
const HUB: { title: string; items: HubItem[] }[] = [
  {
    title: 'Sell',
    items: [
      {
        icon: 'cube',
        label: 'Items & stock',
        sub: 'Products, services',
        href: '/items' as Href,
        bg: colors.greenTint,
        fg: colors.greenText,
      },
      {
        icon: 'chatbox-ellipses',
        label: 'Quotations',
        sub: 'Estimates',
        href: '/quotations',
        bg: colors.amberTint,
        fg: colors.amberText,
      },
    ],
  },
  {
    title: 'Buy & spend',
    items: [
      {
        icon: 'cart',
        label: 'Purchases',
        sub: 'Bills from vendors',
        href: '/purchases' as Href,
        bg: colors.tealTint,
        fg: colors.teal,
        area: 'purchases',
      },
      {
        icon: 'storefront',
        label: 'Vendors',
        sub: 'Suppliers',
        href: '/vendors' as Href,
        bg: colors.slateTint,
        fg: colors.navy,
        area: 'vendors',
      },
      {
        icon: 'wallet',
        label: 'Expenses',
        sub: 'Rent, salary, fuel',
        href: '/expenses' as Href,
        bg: colors.redTint,
        fg: colors.redText,
        area: 'expenses',
      },
      {
        icon: 'receipt',
        label: 'Cash memos',
        sub: 'Small purchases',
        href: '/cash-memos' as Href,
        bg: colors.amberTint,
        fg: colors.amberText,
        area: 'cash_memos',
      },
    ],
  },
  {
    title: 'GST & reports',
    items: [
      {
        icon: 'document-attach',
        label: 'GST returns',
        sub: 'GSTR-1, 2B, 3B, HSN',
        href: '/gst' as Href,
        bg: colors.tealTint,
        fg: colors.tealDark,
        area: 'gst',
      },
      {
        icon: 'bar-chart',
        label: 'Reports',
        sub: 'P&L, ageing, registers',
        href: '/reports' as Href,
        bg: colors.greenTint,
        fg: colors.greenText,
        area: 'reports',
      },
    ],
  },
  {
    title: 'Business',
    items: [
      {
        icon: 'business',
        label: 'Company',
        sub: 'Profile, bank, logo',
        href: '/settings' as Href,
        bg: colors.slateTint,
        fg: colors.navy,
        area: 'company_settings',
      },
      {
        icon: 'people',
        label: 'Team',
        sub: 'Staff & roles',
        href: '/settings/team' as Href,
        bg: colors.tealTint,
        fg: colors.teal,
        area: 'team',
      },
      {
        icon: 'options',
        label: 'Invoice settings',
        sub: 'Numbering, terms',
        href: '/settings/invoice' as Href,
        bg: colors.amberTint,
        fg: colors.amberText,
        area: 'company_settings',
      },
      {
        icon: 'notifications',
        label: 'Notifications',
        sub: 'Alerts & reminders',
        href: '/notifications' as Href,
        bg: colors.redTint,
        fg: colors.redText,
      },
    ],
  },
];

type Item = {
  icon: IconName;
  label: string;
  sub?: string;
  onPress: () => void;
  tone?: 'amber' | 'slate' | 'red';
};

function Group({ title, items }: { title: string; items: Item[] }) {
  return (
    <View style={{ gap: 8 }}>
      <Overline>{title}</Overline>
      <Card style={{ paddingVertical: 2 }}>
        {items.map((it, i) => {
          const tone =
            it.tone === 'amber'
              ? [colors.amberTint, colors.amberText]
              : it.tone === 'slate'
                ? [colors.slateTint, colors.navy]
                : it.tone === 'red'
                  ? [colors.redTint, colors.redText]
                  : [colors.tealTint, colors.teal];
          return (
            <Row
              key={it.label}
              divider={i < items.length - 1}
              onPress={it.onPress}
              leading={<IconTile icon={it.icon} bg={tone[0]} fg={tone[1]} size={34} />}
              title={it.label}
              subtitle={it.sub}
              trailing={<Icon name="chevron-forward" size={18} color={colors.textMuted} />}
            />
          );
        })}
      </Card>
    </View>
  );
}

export default function More() {
  const { user, company, signOut } = useAuth();
  const can = useCan();
  const version = Constants.expoConfig?.version ?? '1.0.0';

  const confirmSignOut = () =>
    Alert.alert('Log out?', 'You can log back in any time with your email and password.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Log out',
        style: 'destructive',
        onPress: () => signOut(),
      },
    ]);

  const checkUpdates = async () => {
    if (!Updates.isEnabled) return Alert.alert('Updates', 'Updates are turned off in development builds.');
    try {
      const r = await Updates.checkForUpdateAsync();
      if (!r.isAvailable) Alert.alert('You are up to date', `Apna Invoice ${version} is the latest version.`);
      else {
        await Updates.fetchUpdateAsync();
        await Updates.reloadAsync();
      }
    } catch {
      Alert.alert('Could not check', 'Check your internet connection and try again.');
    }
  };

  return (
    <Screen tabBar title="More">
      <PressableScale onPress={() => router.push('/account')} style={[styles.profile, shadow.soft]} accessibilityRole="button" accessibilityLabel="Profile and account">
        <Avatar name={user?.name ?? 'A'} tone="amber" size={48} />
        <View style={{ flex: 1 }}>
          <AppText variant="h3" numberOfLines={1}>
            {user?.name}
          </AppText>
          <AppText variant="small" color={colors.textMuted} numberOfLines={1}>
            {company?.name ?? user?.email}
          </AppText>
        </View>
        <Icon name="chevron-forward" size={18} color={colors.textMuted} />
      </PressableScale>

      {HUB.map((h) => ({
        ...h,
        items: h.items.filter((it) => !it.area || can[it.area]),
      }))
        .filter((h) => h.items.length)
        .map((section) => (
          <View key={section.title} style={{ gap: 10 }}>
            <Overline>{section.title}</Overline>
            <View style={styles.grid}>
              {section.items.map((it) => (
                <PressableScale key={it.label} accessibilityRole="button" accessibilityLabel={it.label} onPress={() => router.push(it.href)} style={[styles.tile, shadow.soft]}>
                  <IconTile icon={it.icon} bg={it.bg} fg={it.fg} size={40} />
                  <AppText
                    style={{
                      fontFamily: fonts.semibold,
                      fontSize: 14,
                    }}
                    numberOfLines={1}
                  >
                    {it.label}
                  </AppText>
                  <AppText variant="small" color={colors.textMuted} numberOfLines={1}>
                    {it.sub}
                  </AppText>
                </PressableScale>
              ))}
            </View>
          </View>
        ))}

      <Group
        title="Account"
        items={[
          {
            icon: 'person-circle-outline',
            label: 'Profile & account',
            sub: user?.email,
            onPress: () => router.push('/account'),
          },
          {
            icon: 'cloud-download-outline',
            label: 'Check for app updates',
            sub: `Version ${version}`,
            onPress: checkUpdates,
            tone: 'slate',
          },
        ]}
      />

      <Group
        title="Help & legal"
        items={[
          {
            icon: 'help-buoy-outline',
            label: 'Help & support',
            sub: 'Contact the Apna Invoice team',
            onPress: () => WebBrowser.openBrowserAsync(LINKS.contact),
          },
          {
            icon: 'shield-checkmark-outline',
            label: 'Privacy policy',
            onPress: () => WebBrowser.openBrowserAsync(LINKS.privacy),
            tone: 'slate',
          },
          {
            icon: 'document-outline',
            label: 'Terms of service',
            onPress: () => WebBrowser.openBrowserAsync(LINKS.terms),
            tone: 'slate',
          },
          {
            icon: 'log-out-outline',
            label: 'Log out',
            onPress: confirmSignOut,
            tone: 'red',
          },
        ]}
      />
      <AppText variant="small" color={colors.textMuted} align="center" style={{ marginTop: 8 }}>
        Apna Invoice {version} · {Updates.updateId ? `update ${Updates.updateId.slice(0, 8)}` : 'built-in'} · Made in India
      </AppText>
    </Screen>
  );
}

const styles = StyleSheet.create({
  profile: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 22,
    backgroundColor: colors.surface,
  },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  tile: {
    flexBasis: '47%',
    flexGrow: 1,
    backgroundColor: colors.surface,
    borderRadius: 20,
    padding: 14,
    gap: 4,
  },
});
