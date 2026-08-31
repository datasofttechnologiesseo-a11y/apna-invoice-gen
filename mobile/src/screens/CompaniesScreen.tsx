import React, { useState } from 'react';
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
import { createCompany, getCompanies, switchCompany } from '../api/endpoints';
import { apiErrorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Button, Card, EmptyState, TextField } from '../components/ui';
import { StatePicker } from '../components/StatePicker';
import { colors } from '../theme';
import type { Company } from '../api/types';

export default function CompaniesScreen() {
  const qc = useQueryClient();
  const { refresh } = useAuth();
  const [creating, setCreating] = useState(false);

  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['companies'],
    queryFn: getCompanies,
  });

  const switchMut = useMutation({
    mutationFn: (id: number) => switchCompany(id),
    onSuccess: async () => {
      await refresh();
      // The active company drives almost every other query — reset them all.
      qc.invalidateQueries();
    },
    onError: (e) => Alert.alert('Could not switch', apiErrorMessage(e)),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <View style={styles.header}>
        <Text style={styles.title}>Companies</Text>
        <Pressable style={styles.newBtn} onPress={() => setCreating(true)}>
          <Text style={styles.newBtnText}>+ New</Text>
        </Pressable>
      </View>

      {isLoading ? (
        <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />
      ) : isError ? (
        <Text style={styles.error}>{apiErrorMessage(error)}</Text>
      ) : (
        <FlatList
          data={data!.companies}
          keyExtractor={(c) => String(c.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          refreshing={isRefetching}
          onRefresh={refetch}
          ListEmptyComponent={<EmptyState title="No companies" subtitle="Create your first company." />}
          renderItem={({ item }) => {
            const isActive = item.id === data!.activeCompanyId;
            return (
              <Card style={isActive ? { ...styles.row, ...styles.rowActive } : styles.row}>
                <View style={{ flex: 1 }}>
                  <View style={styles.rowTitleLine}>
                    <Text style={styles.name}>{item.name}</Text>
                    {isActive ? (
                      <View style={styles.activePill}>
                        <Text style={styles.activePillText}>Active</Text>
                      </View>
                    ) : null}
                  </View>
                  <Text style={styles.sub}>
                    {item.state?.name ?? 'No state'}
                    {item.gstin ? ` · ${item.gstin}` : ''}
                  </Text>
                  <Text style={styles.sub}>
                    {(item.invoices_count ?? 0)} invoices · {(item.customers_count ?? 0)} customers
                  </Text>
                </View>
                {!isActive ? (
                  <Button
                    title="Switch"
                    variant="secondary"
                    style={styles.switchBtn}
                    loading={switchMut.isPending && switchMut.variables === item.id}
                    onPress={() => switchMut.mutate(item.id)}
                  />
                ) : null}
              </Card>
            );
          }}
        />
      )}

      <CompanyCreateModal
        visible={creating}
        onClose={() => setCreating(false)}
        onCreated={async () => {
          setCreating(false);
          await refresh();
          qc.invalidateQueries();
        }}
      />
    </View>
  );
}

function CompanyCreateModal({
  visible,
  onClose,
  onCreated,
}: {
  visible: boolean;
  onClose: () => void;
  onCreated: () => void;
}) {
  const [name, setName] = useState('');
  const [prefix, setPrefix] = useState('INV');
  const [gstin, setGstin] = useState('');
  const [stateId, setStateId] = useState<number | null>(null);
  const [stateName, setStateName] = useState('');
  const [statePicker, setStatePicker] = useState(false);

  function reset() {
    setName('');
    setPrefix('INV');
    setGstin('');
    setStateId(null);
    setStateName('');
  }

  const mut = useMutation({
    mutationFn: () =>
      createCompany({
        name: name.trim(),
        state_id: stateId!,
        country: 'India',
        default_currency: 'INR',
        invoice_prefix: prefix.trim() || 'INV',
        invoice_number_padding: 4,
        gstin: gstin.trim() || null,
      }),
    onSuccess: () => {
      reset();
      onCreated();
    },
    onError: (e) => alert(apiErrorMessage(e)),
  });

  function save() {
    if (!name.trim()) return alert('Business name is required');
    if (!stateId) return alert('State is required (drives GST on every invoice)');
    mut.mutate();
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.bg }}>
        <View style={styles.modalHeader}>
          <Text style={styles.modalTitle}>New company</Text>
          <Text style={styles.modalClose} onPress={onClose}>
            Close
          </Text>
        </View>
        <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
          <Text style={styles.hint}>
            Creating a company switches you to it. You can finish bank, address and logo details under
            Edit company afterwards.
          </Text>
          <TextField label="Business name *" value={name} onChangeText={setName} placeholder="e.g. Acme Traders" />
          <Text style={styles.label}>State * (drives CGST/SGST vs IGST)</Text>
          <Pressable style={styles.picker} onPress={() => setStatePicker(true)}>
            <Text style={stateName ? styles.pickerText : styles.pickerPlaceholder}>
              {stateName || 'Select state'}
            </Text>
          </Pressable>
          <View style={{ height: 14 }} />
          <TextField label="GSTIN" value={gstin} onChangeText={(v) => setGstin(v.toUpperCase())} autoCapitalize="characters" placeholder="Optional" />
          <TextField label="Invoice number prefix" value={prefix} onChangeText={setPrefix} autoCapitalize="characters" />
          <Button title="Create company" onPress={save} loading={mut.isPending} style={{ marginTop: 8 }} />
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
    paddingTop: 8,
    marginBottom: 8,
  },
  title: { fontSize: 26, fontWeight: '800', color: colors.text },
  newBtn: { backgroundColor: colors.primary, paddingHorizontal: 16, paddingVertical: 8, borderRadius: 10 },
  newBtnText: { color: '#fff', fontWeight: '700' },
  row: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  rowActive: { borderColor: colors.primary, borderWidth: 1.5 },
  rowTitleLine: { flexDirection: 'row', alignItems: 'center' },
  name: { fontSize: 16, fontWeight: '700', color: colors.text },
  activePill: { backgroundColor: colors.primary + '22', borderRadius: 999, paddingHorizontal: 8, paddingVertical: 2, marginLeft: 8 },
  activePillText: { fontSize: 11, fontWeight: '700', color: colors.primary },
  sub: { fontSize: 13, color: colors.muted, marginTop: 2 },
  switchBtn: { height: 40, paddingHorizontal: 16, marginLeft: 8 },
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
  hint: { fontSize: 13, color: colors.muted, marginBottom: 14, lineHeight: 18 },
  label: { fontSize: 13, fontWeight: '600', color: colors.text, marginBottom: 6 },
  picker: { borderWidth: 1, borderColor: colors.border, borderRadius: 10, padding: 14, backgroundColor: colors.card },
  pickerText: { fontSize: 16, color: colors.text },
  pickerPlaceholder: { fontSize: 16, color: colors.muted },
});
