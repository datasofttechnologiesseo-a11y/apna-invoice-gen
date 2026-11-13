import { useState } from 'react';
import { Alert, View } from 'react-native';

import { ApiError } from '@/api/client';
import { authApi } from '@/api/endpoints';
import { useAuth } from '@/auth/auth-context';
import { AppText } from '@/components/app-text';
import { Card, KeyValue } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { colors } from '@/theme';

/**
 * Profile + in-app account deletion. Apple (5.1.1(v)) and Google Play both
 * require that an account created in the app can be deleted from the app.
 * Uses DELETE /api/profile — the same AccountErasureService as the web.
 */
export default function Account() {
  const { user, company, forget } = useAuth();
  const [deleting, setDeleting] = useState(false);
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string>();
  const [busy, setBusy] = useState(false);

  const erase = () =>
    Alert.alert(
      'Delete your account?',
      'Your profile and business data will be erased. GST invoice records are kept only as long as tax law requires, then deleted. This cannot be undone.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete account',
          style: 'destructive',
          onPress: async () => {
            setBusy(true);
            setError(undefined);
            try {
              const res = await authApi.deleteAccount(password);
              await forget();
              Alert.alert('Account deleted', res.message);
            } catch (e) {
              const err = e as ApiError;
              setError(err.fieldErrors.password ?? err.message);
            } finally {
              setBusy(false);
            }
          },
        },
      ],
    );

  return (
    <Screen nav="back" title="Profile & account">
      <Card>
        <KeyValue label="Name" value={user?.name ?? ''} />
        <KeyValue label="Email" value={user?.email ?? ''} />
        {user?.phone ? <KeyValue label="Mobile" value={user.phone} /> : null}
        {company ? <KeyValue label="Business" value={company.name} /> : null}
        {company?.gstin ? <KeyValue label="GSTIN" value={company.gstin} /> : null}
      </Card>
      <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
        To change your name, email or password, use Settings → Profile on apnainvoice.com.
      </AppText>

      <Card style={{ marginTop: 12 }}>
        <AppText variant="h3" color={colors.redText}>
          Delete account
        </AppText>
        <AppText variant="small" color={colors.textBody} style={{ fontSize: 14, lineHeight: 20 }}>
          Permanently erase your account and personal data. Download a backup first from the web if you need your records.
        </AppText>
        {deleting ? (
          <View style={{ gap: 12 }}>
            <TextField label="Confirm with your password" value={password} onChangeText={setPassword} secureTextEntry error={error} autoComplete="current-password" />
            <Button label="Delete my account" kind="danger" loading={busy} disabled={!password} onPress={erase} />
            <Button label="Cancel" kind="ghost" size="md" onPress={() => setDeleting(false)} />
          </View>
        ) : (
          <Button label="Delete my account" kind="danger" icon="trash-outline" onPress={() => setDeleting(true)} />
        )}
      </Card>
    </Screen>
  );
}
