import React, { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text } from 'react-native';
import { useMutation } from '@tanstack/react-query';
import { deleteAccount, updatePassword, updateProfile } from '../api/endpoints';
import { apiErrorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Button, Card, TextField } from '../components/ui';
import { colors } from '../theme';

export default function ProfileScreen() {
  const { user, refresh, signOut } = useAuth();

  const [name, setName] = useState(user?.name ?? '');
  const [email, setEmail] = useState(user?.email ?? '');

  const [currentPw, setCurrentPw] = useState('');
  const [newPw, setNewPw] = useState('');
  const [confirmPw, setConfirmPw] = useState('');

  const [delPw, setDelPw] = useState('');

  const profileMut = useMutation({
    mutationFn: () => updateProfile({ name: name.trim(), email: email.trim() }),
    onSuccess: async () => {
      await refresh();
      Alert.alert('Saved', 'Profile updated.');
    },
    onError: (e) => Alert.alert('Could not save', apiErrorMessage(e)),
  });

  const passwordMut = useMutation({
    mutationFn: () =>
      updatePassword({ current_password: currentPw, password: newPw, password_confirmation: confirmPw }),
    onSuccess: () => {
      setCurrentPw('');
      setNewPw('');
      setConfirmPw('');
      Alert.alert('Done', 'Password updated.');
    },
    onError: (e) => Alert.alert('Could not change password', apiErrorMessage(e)),
  });

  const deleteMut = useMutation({
    mutationFn: () => deleteAccount(delPw),
    onSuccess: async () => {
      Alert.alert('Account deleted', 'Your account has been removed.');
      await signOut();
    },
    onError: (e) => Alert.alert('Could not delete', apiErrorMessage(e)),
  });

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.bg }} contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
      <Card style={{ marginBottom: 16 }}>
        <Text style={styles.sectionTitle}>Profile</Text>
        <TextField label="Name" value={name} onChangeText={setName} />
        <TextField label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" />
        <Button title="Save profile" onPress={() => profileMut.mutate()} loading={profileMut.isPending} />
      </Card>

      <Card style={{ marginBottom: 16 }}>
        <Text style={styles.sectionTitle}>Change password</Text>
        <TextField label="Current password" value={currentPw} onChangeText={setCurrentPw} secureTextEntry />
        <TextField label="New password" value={newPw} onChangeText={setNewPw} secureTextEntry />
        <TextField label="Confirm new password" value={confirmPw} onChangeText={setConfirmPw} secureTextEntry />
        <Button
          title="Update password"
          variant="secondary"
          loading={passwordMut.isPending}
          onPress={() => {
            if (newPw.length < 8) return Alert.alert('Password too short', 'Use at least 8 characters.');
            if (newPw !== confirmPw) return Alert.alert('Passwords do not match');
            passwordMut.mutate();
          }}
        />
      </Card>

      <Card style={{ marginBottom: 40 }}>
        <Text style={styles.sectionTitle}>Delete account</Text>
        <Text style={styles.warn}>This permanently deletes your account and all data. This cannot be undone.</Text>
        <TextField label="Confirm with your password" value={delPw} onChangeText={setDelPw} secureTextEntry />
        <Button
          title="Delete my account"
          variant="danger"
          loading={deleteMut.isPending}
          onPress={() => {
            if (!delPw) return Alert.alert('Enter your password to confirm');
            Alert.alert('Delete account?', 'This is permanent.', [
              { text: 'Cancel', style: 'cancel' },
              { text: 'Delete', style: 'destructive', onPress: () => deleteMut.mutate() },
            ]);
          }}
        />
      </Card>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  sectionTitle: { fontSize: 16, fontWeight: '800', color: colors.text, marginBottom: 12 },
  warn: { fontSize: 13, color: colors.danger, marginBottom: 12, lineHeight: 18 },
});
