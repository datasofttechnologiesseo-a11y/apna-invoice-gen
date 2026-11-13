import Constants from 'expo-constants';
import { router } from 'expo-router';
import * as Updates from 'expo-updates';
import * as WebBrowser from 'expo-web-browser';
import { Alert, View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { AppText } from '@/components/app-text';
import { Avatar, Card, IconTile, Overline, Row } from '@/components/blocks';
import { Icon, IconName } from '@/components/icon';
import { Screen } from '@/components/screen';
import { LINKS } from '@/config';
import { colors } from '@/theme';

type Item = { icon: IconName; label: string; sub?: string; onPress: () => void; tone?: 'amber' | 'slate' | 'red' };

const web = (path: string) => () => WebBrowser.openBrowserAsync(`${LINKS.web}${path}`);

function Group({ title, items }: { title: string; items: Item[] }) {
  return (
    <View style={{ gap: 8 }}>
      <Overline>{title}</Overline>
      <Card style={{ paddingVertical: 2 }}>
        {items.map((it, i) => {
          const tone = it.tone === 'amber' ? [colors.amberTint, colors.amberText] : it.tone === 'slate' ? [colors.slateTint, colors.navy] : it.tone === 'red' ? [colors.redTint, colors.redText] : [colors.tealTint, colors.teal];
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
  const version = Constants.expoConfig?.version ?? '1.0.0';

  const confirmSignOut = () =>
    Alert.alert('Log out?', 'You can log back in any time with your email and password.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Log out', style: 'destructive', onPress: () => signOut() },
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
    <Screen
      tabBar
      title="More"
      headerExtra={
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12, borderRadius: 18, backgroundColor: 'rgba(255,255,255,0.12)' }}>
          <Avatar name={user?.name ?? 'A'} tone="amber" size={46} />
          <View style={{ flex: 1 }}>
            <AppText variant="h3" color={colors.onDark} numberOfLines={1}>
              {user?.name}
            </AppText>
            <AppText variant="small" color={colors.onDarkMuted} numberOfLines={1}>
              {company?.name ?? user?.email}
            </AppText>
          </View>
        </View>
      }
    >
      <Group
        title="On the web for now"
        items={[
          { icon: 'cart-outline', label: 'Purchases & vendors', sub: 'Purchase invoices, payments made', onPress: web('/purchases'), tone: 'amber' },
          { icon: 'wallet-outline', label: 'Expenses & cash memos', onPress: web('/finance/expenses'), tone: 'amber' },
          { icon: 'cube-outline', label: 'Items & stock', onPress: web('/products'), tone: 'slate' },
          { icon: 'receipt-outline', label: 'GST returns', sub: 'GSTR-1, 2B match, 3B, HSN', onPress: web('/finance/gstr1'), tone: 'amber' },
          { icon: 'bar-chart-outline', label: 'Reports', sub: 'P&L, aging, registers — Excel/PDF', onPress: web('/reports') },
          { icon: 'business-outline', label: 'Company settings & team', onPress: web('/settings'), tone: 'slate' },
        ]}
      />
      <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4, marginTop: -4 }}>
        These open apnainvoice.com. They move into the app in the next updates.
      </AppText>

      <Group
        title="Account"
        items={[
          { icon: 'person-circle-outline', label: 'Profile & account', sub: user?.email, onPress: () => router.push('/account') },
          { icon: 'cloud-download-outline', label: 'Check for app updates', sub: `Version ${version}`, onPress: checkUpdates, tone: 'slate' },
        ]}
      />

      <Group
        title="Help & legal"
        items={[
          { icon: 'help-buoy-outline', label: 'Help & support', sub: 'Contact the Apna Invoice team', onPress: () => WebBrowser.openBrowserAsync(LINKS.contact) },
          { icon: 'shield-checkmark-outline', label: 'Privacy policy', onPress: () => WebBrowser.openBrowserAsync(LINKS.privacy), tone: 'slate' },
          { icon: 'document-outline', label: 'Terms of service', onPress: () => WebBrowser.openBrowserAsync(LINKS.terms), tone: 'slate' },
          { icon: 'log-out-outline', label: 'Log out', onPress: confirmSignOut, tone: 'red' },
        ]}
      />
      <AppText variant="small" color={colors.textMuted} align="center" style={{ marginTop: 8 }}>
        Apna Invoice {version} · Made in India
      </AppText>
    </Screen>
  );
}
