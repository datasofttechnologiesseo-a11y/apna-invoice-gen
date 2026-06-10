import React, { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { useAuth } from '../auth/AuthContext';
import { Card } from '../components/ui';
import { CompanyEditModal } from './CompanyEditModal';
import { colors } from '../theme';
import type { SettingsStackParamList } from '../navigation/types';

type Nav = NativeStackNavigationProp<SettingsStackParamList, 'SettingsHome'>;

type IconName = keyof typeof Ionicons.glyphMap;

function initials(name?: string | null): string {
  if (!name) return '?';
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join('');
}

export default function MoreScreen() {
  const { user, company, signOut } = useAuth();
  const insets = useSafeAreaInsets();
  const navigation = useNavigation<Nav>();
  const [editCompany, setEditCompany] = useState(false);

  // Quotes / Products / Finance are sibling tabs — reach them via the tab navigator.
  const goTab = (name: string) => navigation.getParent()?.navigate(name as never);

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <ScrollView contentContainerStyle={{ padding: 16, paddingTop: insets.top + 12, paddingBottom: 32 }}>
        <Text style={styles.title}>More</Text>

        {/* Profile */}
        <Pressable onPress={() => navigation.navigate('Profile')}>
          <Card style={styles.profileCard}>
            <View style={styles.avatar}>
              <Text style={styles.avatarText}>{initials(user?.name)}</Text>
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.profileName}>{user?.name}</Text>
              <Text style={styles.profileEmail}>{user?.email}</Text>
            </View>
            <Ionicons name="chevron-forward" size={20} color={colors.muted} />
          </Card>
        </Pressable>

        {/* Active company */}
        <Card style={styles.companyCard}>
          <View style={styles.companyTop}>
            <View style={styles.companyIcon}>
              <Ionicons name="business" size={18} color={colors.primary} />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.companyLabel}>Active company</Text>
              <Text style={styles.companyName}>{company?.name ?? '—'}</Text>
              {company?.gstin ? <Text style={styles.companySub}>GSTIN: {company.gstin}</Text> : null}
            </View>
          </View>
          {!company?.is_onboarded ? (
            <Text style={styles.warn}>Finish company setup to issue compliant invoices.</Text>
          ) : null}
          <View style={styles.companyActions}>
            <Pressable style={styles.smallBtn} onPress={() => setEditCompany(true)}>
              <Text style={styles.smallBtnText}>Edit company</Text>
            </Pressable>
            <Pressable style={styles.smallBtn} onPress={() => navigation.navigate('Companies')}>
              <Text style={styles.smallBtnText}>Switch / manage</Text>
            </Pressable>
          </View>
        </Card>

        <Section title="Sales & catalogue">
          <MenuRow icon="document-text-outline" title="Quotations" subtitle="Proforma / price proposals" onPress={() => goTab('Quotes')} />
          <MenuRow icon="cube-outline" title="Products & services" subtitle="Your item catalogue" onPress={() => goTab('Products')} last />
        </Section>

        <Section title="Finance">
          <MenuRow icon="stats-chart-outline" title="Finance & GST" subtitle="P&L, aging, GSTR, expenses, cash memos" onPress={() => goTab('Finance')} last />
        </Section>

        <Section title="Account & data">
          <MenuRow icon="time-outline" title="Activity log" subtitle="Audit trail of every change" onPress={() => navigation.navigate('Activity')} />
          <MenuRow icon="cloud-download-outline" title="Backups" subtitle="Download or auto-email your data" onPress={() => navigation.navigate('Backup')} />
          <MenuRow icon="gift-outline" title="Refer & earn" subtitle="Invite other businesses" onPress={() => navigation.navigate('Referrals')} />
          <MenuRow icon="book-outline" title="Guides & blog" subtitle="Tips for GST billing" onPress={() => navigation.navigate('Blog')} last />
        </Section>

        <Pressable style={styles.logout} onPress={signOut}>
          <Ionicons name="log-out-outline" size={20} color={colors.danger} />
          <Text style={styles.logoutText}>Log out</Text>
        </Pressable>
      </ScrollView>

      <CompanyEditModal
        visible={editCompany}
        onClose={() => setEditCompany(false)}
        onSaved={() => setEditCompany(false)}
      />
    </View>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <View style={{ marginTop: 20 }}>
      <Text style={styles.sectionTitle}>{title}</Text>
      <Card style={styles.group}>{children}</Card>
    </View>
  );
}

function MenuRow({
  icon,
  title,
  subtitle,
  onPress,
  last,
}: {
  icon: IconName;
  title: string;
  subtitle?: string;
  onPress: () => void;
  last?: boolean;
}) {
  return (
    <Pressable style={[styles.row, !last && styles.rowDivider]} onPress={onPress}>
      <View style={styles.rowIcon}>
        <Ionicons name={icon} size={20} color={colors.primary} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={styles.rowTitle}>{title}</Text>
        {subtitle ? <Text style={styles.rowSub}>{subtitle}</Text> : null}
      </View>
      <Ionicons name="chevron-forward" size={18} color={colors.muted} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  title: { fontSize: 28, fontWeight: '800', color: colors.text, marginBottom: 16 },
  profileCard: { flexDirection: 'row', alignItems: 'center' },
  avatar: { width: 48, height: 48, borderRadius: 24, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center', marginRight: 14 },
  avatarText: { color: '#fff', fontSize: 18, fontWeight: '800' },
  profileName: { fontSize: 17, fontWeight: '700', color: colors.text },
  profileEmail: { fontSize: 13, color: colors.muted, marginTop: 2 },
  companyCard: { marginTop: 12 },
  companyTop: { flexDirection: 'row', alignItems: 'center' },
  companyIcon: { width: 36, height: 36, borderRadius: 10, backgroundColor: colors.primary + '15', alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  companyLabel: { fontSize: 12, color: colors.muted },
  companyName: { fontSize: 16, fontWeight: '700', color: colors.text, marginTop: 2 },
  companySub: { fontSize: 12, color: colors.muted, marginTop: 2 },
  warn: { fontSize: 13, color: colors.warning, marginTop: 10 },
  companyActions: { flexDirection: 'row', gap: 10, marginTop: 14 },
  smallBtn: { flex: 1, borderWidth: 1, borderColor: colors.border, borderRadius: 10, paddingVertical: 10, alignItems: 'center' },
  smallBtnText: { fontSize: 13, fontWeight: '700', color: colors.primary },
  sectionTitle: { fontSize: 13, fontWeight: '700', color: colors.muted, textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 8, marginLeft: 4 },
  group: { padding: 0, overflow: 'hidden' },
  row: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 14 },
  rowDivider: { borderBottomWidth: 1, borderBottomColor: colors.border },
  rowIcon: { width: 36, height: 36, borderRadius: 10, backgroundColor: colors.primary + '12', alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  rowTitle: { fontSize: 15, fontWeight: '600', color: colors.text },
  rowSub: { fontSize: 12, color: colors.muted, marginTop: 2 },
  logout: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 28, paddingVertical: 14, borderRadius: 12, borderWidth: 1, borderColor: colors.danger + '40' },
  logoutText: { fontSize: 16, fontWeight: '700', color: colors.danger },
});
