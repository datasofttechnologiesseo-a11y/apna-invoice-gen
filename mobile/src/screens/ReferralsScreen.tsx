import React from 'react';
import { ActivityIndicator, Linking, Share, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { getReferrals } from '../api/endpoints';
import { apiErrorMessage } from '../api/client';
import { Button, Card, EmptyState } from '../components/ui';
import { colors } from '../theme';

export default function ReferralsScreen() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['referrals'],
    queryFn: getReferrals,
  });

  if (isLoading) {
    return <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />;
  }
  if (isError || !data) {
    return <Text style={styles.error}>{apiErrorMessage(error)}</Text>;
  }

  async function shareInvite() {
    try {
      await Share.share({ message: data!.share_text });
    } catch {
      // user dismissed the sheet — nothing to do
    }
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <View style={{ padding: 16 }}>
        <Card style={styles.codeCard}>
          <Text style={styles.codeLabel}>Your referral code</Text>
          <Text style={styles.code}>{data.code}</Text>
          <Text style={styles.codeSub}>Friends who sign up with this code count as your referrals.</Text>
          <Button title="Share invite" onPress={shareInvite} style={{ marginTop: 16 }} />
          <Button
            title="Share on WhatsApp"
            variant="secondary"
            onPress={() => Linking.openURL(data.wa_share)}
            style={{ marginTop: 10 }}
          />
        </Card>

        <View style={styles.statsRow}>
          <Stat label="Total" value={data.stats.total} />
          <Stat label="Pending" value={data.stats.pending} color={colors.warning} />
          <Stat label="Rewarded" value={data.stats.rewarded} color={colors.success} />
        </View>

        <Text style={styles.sectionTitle}>Your referrals</Text>
        {data.referrals.length === 0 ? (
          <EmptyState title="No referrals yet" subtitle="Share your code to get started." />
        ) : (
          data.referrals.map((r, i) => (
            <Card key={i} style={styles.row}>
              <View style={{ flex: 1 }}>
                <Text style={styles.name}>{r.name ?? '—'}</Text>
                <Text style={styles.sub}>{r.signed_up_at ?? ''}</Text>
              </View>
              <View style={[styles.pill, r.reward_status === 'rewarded' ? styles.pillRewarded : styles.pillPending]}>
                <Text style={[styles.pillText, { color: r.reward_status === 'rewarded' ? colors.success : colors.warning }]}>
                  {r.reward_status}
                </Text>
              </View>
            </Card>
          ))
        )}
      </View>
    </View>
  );
}

function Stat({ label, value, color }: { label: string; value: number; color?: string }) {
  return (
    <Card style={styles.statCard}>
      <Text style={[styles.statValue, color ? { color } : null]}>{value}</Text>
      <Text style={styles.statLabel}>{label}</Text>
    </Card>
  );
}

const styles = StyleSheet.create({
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  codeCard: { alignItems: 'center', paddingVertical: 24 },
  codeLabel: { fontSize: 13, color: colors.muted },
  code: { fontSize: 30, fontWeight: '800', color: colors.primary, marginTop: 6, letterSpacing: 2 },
  codeSub: { fontSize: 13, color: colors.muted, marginTop: 8, textAlign: 'center' },
  statsRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 14 },
  statCard: { width: '31.5%', alignItems: 'center', paddingVertical: 16 },
  statValue: { fontSize: 22, fontWeight: '800', color: colors.text },
  statLabel: { fontSize: 12, color: colors.muted, marginTop: 4 },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: colors.text, marginTop: 20, marginBottom: 10 },
  row: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  name: { fontSize: 15, fontWeight: '700', color: colors.text },
  sub: { fontSize: 12, color: colors.muted, marginTop: 2 },
  pill: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4 },
  pillPending: { backgroundColor: colors.warning + '22' },
  pillRewarded: { backgroundColor: colors.success + '22' },
  pillText: { fontSize: 12, fontWeight: '700', textTransform: 'capitalize' },
});
