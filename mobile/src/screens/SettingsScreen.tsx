import React, { useState } from 'react';
import { ScrollView, StyleSheet, Text } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { useAuth } from '../auth/AuthContext';
import { Button, Card } from '../components/ui';
import { CompanyEditModal } from './CompanyEditModal';
import { colors } from '../theme';
import type { SettingsStackParamList } from '../navigation/types';

type Nav = NativeStackNavigationProp<SettingsStackParamList, 'SettingsHome'>;

export default function SettingsScreen() {
  const { user, company, signOut } = useAuth();
  const insets = useSafeAreaInsets();
  const navigation = useNavigation<Nav>();
  const [editCompany, setEditCompany] = useState(false);

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.bg }}
      contentContainerStyle={{ padding: 16, paddingTop: insets.top + 12 }}
    >
      <Text style={styles.title}>Settings</Text>

      <Card style={{ marginBottom: 16 }}>
        <Text style={styles.label}>Signed in as</Text>
        <Text style={styles.value}>{user?.name}</Text>
        <Text style={styles.sub}>{user?.email}</Text>
        <Button title="Profile & account" variant="secondary" onPress={() => navigation.navigate('Profile')} style={{ marginTop: 14 }} />
        <Button title="Activity log" variant="secondary" onPress={() => navigation.navigate('Activity')} style={{ marginTop: 10 }} />
        <Button title="Backups" variant="secondary" onPress={() => navigation.navigate('Backup')} style={{ marginTop: 10 }} />
      </Card>

      <Card style={{ marginBottom: 16 }}>
        <Text style={styles.label}>Active company</Text>
        <Text style={styles.value}>{company?.name ?? '—'}</Text>
        {company?.gstin ? <Text style={styles.sub}>GSTIN: {company.gstin}</Text> : null}
        {!company?.is_onboarded ? (
          <Text style={styles.warn}>Finish company setup to issue compliant invoices.</Text>
        ) : null}
        <Button title="Edit company" variant="secondary" onPress={() => setEditCompany(true)} style={{ marginTop: 14 }} />
        <Button title="Switch / manage companies" variant="secondary" onPress={() => navigation.navigate('Companies')} style={{ marginTop: 10 }} />
      </Card>

      <Card style={{ marginBottom: 16 }}>
        <Text style={styles.label}>More</Text>
        <Button title="Refer & earn" variant="secondary" onPress={() => navigation.navigate('Referrals')} style={{ marginTop: 14 }} />
        <Button title="Guides & blog" variant="secondary" onPress={() => navigation.navigate('Blog')} style={{ marginTop: 10 }} />
      </Card>

      <Button title="Log out" variant="danger" onPress={signOut} />

      <CompanyEditModal
        visible={editCompany}
        onClose={() => setEditCompany(false)}
        onSaved={() => setEditCompany(false)}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  title: { fontSize: 26, fontWeight: '800', color: colors.text, marginBottom: 20 },
  label: { fontSize: 13, color: colors.muted },
  value: { fontSize: 18, fontWeight: '700', color: colors.text, marginTop: 4 },
  sub: { fontSize: 14, color: colors.muted, marginTop: 4 },
  warn: { fontSize: 13, color: colors.warning, marginTop: 8 },
});
