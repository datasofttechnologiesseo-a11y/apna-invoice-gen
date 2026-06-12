import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import { AppHeader, HeaderAction } from '../../components/AppHeader';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import {
  createCustomer,
  deleteCustomer,
  getCustomers,
  updateCustomer,
} from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Button, Card, EmptyState, TextField } from '../../components/ui';
import { StatePicker } from '../../components/StatePicker';
import { colors } from '../../theme';
import type { Customer } from '../../api/types';
import type { CustomersStackParamList } from '../../navigation/types';

export default function CustomersScreen() {
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const navigation = useNavigation<NativeStackNavigationProp<CustomersStackParamList, 'CustomersList'>>();
  const [editing, setEditing] = useState<Customer | null | 'new'>(null);

  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['customers'],
    queryFn: () => getCustomers(),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <AppHeader title="Customers" right={<HeaderAction label="+ New" onPress={() => setEditing('new')} />} />

      {isLoading ? (
        <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />
      ) : isError ? (
        <Text style={styles.error}>{apiErrorMessage(error)}</Text>
      ) : (
        <FlatList
          data={data}
          keyExtractor={(c) => String(c.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          refreshing={isRefetching}
          onRefresh={refetch}
          ListEmptyComponent={<EmptyState title="No customers" subtitle="Add your first customer." />}
          renderItem={({ item }) => (
            <Card style={styles.row}>
              <Pressable style={{ flex: 1 }} onPress={() => setEditing(item)}>
                <Text style={styles.name}>{item.name}</Text>
                <Text style={styles.sub}>
                  {item.state?.name ?? '—'}
                  {item.gstin ? ` · ${item.gstin}` : ''}
                </Text>
                {item.phone ? <Text style={styles.sub}>{item.phone}</Text> : null}
              </Pressable>
              <Pressable
                style={styles.ledgerBtn}
                onPress={() => navigation.navigate('CustomerLedger', { id: item.id, name: item.name })}
              >
                <Text style={styles.ledgerBtnText}>Ledger</Text>
              </Pressable>
            </Card>
          )}
        />
      )}

      <CustomerFormModal
        editing={editing}
        onClose={() => setEditing(null)}
        onSaved={() => {
          setEditing(null);
          qc.invalidateQueries({ queryKey: ['customers'] });
          qc.invalidateQueries({ queryKey: ['dashboard'] });
        }}
      />
    </View>
  );
}

function CustomerFormModal({
  editing,
  onClose,
  onSaved,
}: {
  editing: Customer | null | 'new';
  onClose: () => void;
  onSaved: () => void;
}) {
  const visible = editing !== null;
  const isEdit = editing !== null && editing !== 'new';
  const existing = isEdit ? (editing as Customer) : null;

  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [gstin, setGstin] = useState('');
  const [stateId, setStateId] = useState<number | null>(null);
  const [stateName, setStateName] = useState('');
  const [statePicker, setStatePicker] = useState(false);

  useEffect(() => {
    if (existing) {
      setName(existing.name);
      setPhone(existing.phone ?? '');
      setEmail(existing.email ?? '');
      setGstin(existing.gstin ?? '');
      setStateId(existing.state_id);
      setStateName(existing.state?.name ?? '');
    } else if (editing === 'new') {
      setName('');
      setPhone('');
      setEmail('');
      setGstin('');
      setStateId(null);
      setStateName('');
    }
  }, [editing]); // eslint-disable-line react-hooks/exhaustive-deps

  const saveMut = useMutation({
    mutationFn: () => {
      const payload = {
        name: name.trim(),
        phone: phone || undefined,
        email: email || undefined,
        gstin: gstin || undefined,
        state_id: stateId!,
      } as never;
      return existing ? updateCustomer(existing.id, payload) : createCustomer(payload);
    },
    onSuccess: onSaved,
    onError: (e) => alert(apiErrorMessage(e)),
  });

  const deleteMut = useMutation({
    mutationFn: () => deleteCustomer(existing!.id),
    onSuccess: onSaved,
    onError: (e) => Alert.alert('Cannot delete', apiErrorMessage(e)),
  });

  function save() {
    if (!name.trim()) return alert('Name is required');
    if (!stateId) return alert('Please pick a state');
    saveMut.mutate();
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.bg }}>
        <View style={styles.modalHeader}>
          <Text style={styles.modalTitle}>{isEdit ? 'Edit customer' : 'New customer'}</Text>
          <Text style={styles.modalClose} onPress={onClose}>
            Close
          </Text>
        </View>
        <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
          <TextField label="Name *" value={name} onChangeText={setName} placeholder="Customer name" />
          <Text style={styles.label}>State *</Text>
          <Pressable style={styles.picker} onPress={() => setStatePicker(true)}>
            <Text style={stateName ? styles.pickerText : styles.pickerPlaceholder}>
              {stateName || 'Select state'}
            </Text>
          </Pressable>
          <View style={{ height: 14 }} />
          <TextField label="GSTIN" value={gstin} onChangeText={setGstin} autoCapitalize="characters" placeholder="Optional" />
          <TextField label="Phone" value={phone} onChangeText={setPhone} keyboardType="phone-pad" placeholder="Optional" />
          <TextField label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" placeholder="Optional" />
          <Button title={isEdit ? 'Save changes' : 'Save customer'} onPress={save} loading={saveMut.isPending} />

          {isEdit ? (
            <Button
              title="Delete customer"
              variant="danger"
              style={{ marginTop: 12 }}
              loading={deleteMut.isPending}
              onPress={() =>
                Alert.alert('Delete customer?', 'This cannot be undone.', [
                  { text: 'Cancel', style: 'cancel' },
                  { text: 'Delete', style: 'destructive', onPress: () => deleteMut.mutate() },
                ])
              }
            />
          ) : null}
        </ScrollView>

        <StatePicker
          visible={statePicker}
          onClose={() => setStatePicker(false)}
          onPick={(id, n) => {
            setStateId(id);
            setStateName(n);
            setStatePicker(false);
          }}
        />
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    marginBottom: 8,
  },
  title: { fontSize: 26, fontWeight: '800', color: colors.text },
  newBtn: { backgroundColor: colors.primary, paddingHorizontal: 16, paddingVertical: 8, borderRadius: 10 },
  newBtnText: { color: '#fff', fontWeight: '700' },
  row: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  name: { fontSize: 16, fontWeight: '700', color: colors.text },
  sub: { fontSize: 13, color: colors.muted, marginTop: 2 },
  ledgerBtn: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginLeft: 8,
  },
  ledgerBtnText: { fontSize: 13, fontWeight: '700', color: colors.primary },
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    paddingTop: 50,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  modalTitle: { fontSize: 20, fontWeight: '800', color: colors.text },
  modalClose: { fontSize: 16, color: colors.primary, fontWeight: '600' },
  label: { fontSize: 13, fontWeight: '600', color: colors.text, marginBottom: 6 },
  picker: { borderWidth: 1, borderColor: colors.border, borderRadius: 10, padding: 14, backgroundColor: colors.card },
  pickerText: { fontSize: 16, color: colors.text },
  pickerPlaceholder: { fontSize: 16, color: colors.muted },
});
