import React from 'react';
import { ActivityIndicator, Alert, StyleSheet, Switch, Text, View } from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { emailBackup, getBackupStatus, toggleBackup } from '../api/endpoints';
import { downloadAndShare } from '../api/files';
import { apiErrorMessage } from '../api/client';
import { Button, Card } from '../components/ui';
import { colors } from '../theme';

export default function BackupScreen() {
  const qc = useQueryClient();
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['backup-status'],
    queryFn: getBackupStatus,
  });

  const toggleMut = useMutation({
    mutationFn: (enabled: boolean) => toggleBackup(enabled),
    onSuccess: (res) => {
      qc.setQueryData(['backup-status'], res);
    },
    onError: (e) => Alert.alert('Could not update', apiErrorMessage(e)),
  });

  const emailMut = useMutation({
    mutationFn: () => emailBackup(),
    onSuccess: (res) => Alert.alert('Sent', res.message),
    onError: (e) => Alert.alert('Could not email backup', apiErrorMessage(e)),
  });

  const [downloading, setDownloading] = React.useState(false);
  async function download() {
    setDownloading(true);
    try {
      await downloadAndShare('/backup/download', 'apna-invoice-backup.zip');
    } catch (e) {
      Alert.alert('Download failed', apiErrorMessage(e));
    } finally {
      setDownloading(false);
    }
  }

  if (isLoading) {
    return <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />;
  }
  if (isError || !data) {
    return <Text style={styles.error}>{apiErrorMessage(error)}</Text>;
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg, padding: 16 }}>
      <Card style={{ marginBottom: 16 }}>
        <View style={styles.toggleRow}>
          <View style={{ flex: 1 }}>
            <Text style={styles.title}>Weekly auto-backup</Text>
            <Text style={styles.sub}>Get a ZIP of all your data emailed every week.</Text>
          </View>
          <Switch
            value={data.auto_backup_enabled}
            onValueChange={(v) => toggleMut.mutate(v)}
            trackColor={{ true: colors.primary }}
          />
        </View>
        {data.last_backup_sent_at ? (
          <Text style={styles.last}>Last emailed: {new Date(data.last_backup_sent_at).toLocaleString()}</Text>
        ) : null}
      </Card>

      <Card>
        <Text style={styles.title}>Manual backup</Text>
        <Text style={styles.sub}>Download a full ZIP now, or have it emailed to you.</Text>
        <Button title="Download backup (ZIP)" onPress={download} loading={downloading} style={{ marginTop: 14 }} />
        <Button
          title="Email backup to me"
          variant="secondary"
          onPress={() => emailMut.mutate()}
          loading={emailMut.isPending}
          style={{ marginTop: 10 }}
        />
      </Card>
    </View>
  );
}

const styles = StyleSheet.create({
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  toggleRow: { flexDirection: 'row', alignItems: 'center' },
  title: { fontSize: 16, fontWeight: '700', color: colors.text },
  sub: { fontSize: 13, color: colors.muted, marginTop: 4 },
  last: { fontSize: 12, color: colors.muted, marginTop: 12 },
});
