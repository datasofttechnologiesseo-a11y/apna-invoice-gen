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
import {
  createProduct,
  deleteProduct,
  getProducts,
  updateProduct,
} from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Button, Card, EmptyState, TextField } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { Product } from '../../api/types';

const GST_RATES = [0, 5, 12, 18, 28];

export default function ProductsScreen() {
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const [editing, setEditing] = useState<Product | null | 'new'>(null);

  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['products'],
    queryFn: () => getProducts(),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg, paddingTop: insets.top + 8 }}>
      <View style={styles.header}>
        <Text style={styles.title}>Products</Text>
        <Pressable style={styles.newBtn} onPress={() => setEditing('new')}>
          <Text style={styles.newBtnText}>+ New</Text>
        </Pressable>
      </View>

      {isLoading ? (
        <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />
      ) : isError ? (
        <Text style={styles.error}>{apiErrorMessage(error)}</Text>
      ) : (
        <FlatList
          data={data}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          refreshing={isRefetching}
          onRefresh={refetch}
          ListEmptyComponent={<EmptyState title="No products" subtitle="Add a product or service." />}
          renderItem={({ item }) => (
            <Pressable onPress={() => setEditing(item)}>
              <Card style={styles.row}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.name}>{item.name}</Text>
                  <Text style={styles.sub}>
                    {item.kind_label}
                    {item.hsn_sac ? ` · ${item.hsn_sac}` : ''} · GST {item.gst_rate}%
                  </Text>
                </View>
                <Text style={styles.rate}>{formatINR(item.rate)}</Text>
              </Card>
            </Pressable>
          )}
        />
      )}

      <ProductFormModal
        editing={editing}
        onClose={() => setEditing(null)}
        onSaved={() => {
          setEditing(null);
          qc.invalidateQueries({ queryKey: ['products'] });
          qc.invalidateQueries({ queryKey: ['dashboard'] });
        }}
      />
    </View>
  );
}

function ProductFormModal({
  editing,
  onClose,
  onSaved,
}: {
  editing: Product | null | 'new';
  onClose: () => void;
  onSaved: () => void;
}) {
  const visible = editing !== null;
  const isEdit = editing !== null && editing !== 'new';
  const existing = isEdit ? (editing as Product) : null;

  const [name, setName] = useState('');
  const [rate, setRate] = useState('');
  const [hsn, setHsn] = useState('');
  const [kind, setKind] = useState<'goods' | 'service'>('goods');
  const [gstRate, setGstRate] = useState(18);

  useEffect(() => {
    if (existing) {
      setName(existing.name);
      setRate(String(existing.rate));
      setHsn(existing.hsn_sac ?? '');
      setKind(existing.kind);
      setGstRate(existing.gst_rate);
    } else if (editing === 'new') {
      setName('');
      setRate('');
      setHsn('');
      setKind('goods');
      setGstRate(18);
    }
  }, [editing]); // eslint-disable-line react-hooks/exhaustive-deps

  const saveMut = useMutation({
    mutationFn: () => {
      const payload = {
        name: name.trim(),
        rate: Number(rate) || 0,
        hsn_sac: hsn || undefined,
        kind,
        gst_rate: gstRate,
        unit: existing?.unit || 'NOS',
      } as never;
      return existing ? updateProduct(existing.id, payload) : createProduct(payload);
    },
    onSuccess: onSaved,
    onError: (e) => alert(apiErrorMessage(e)),
  });

  const deleteMut = useMutation({
    mutationFn: () => deleteProduct(existing!.id),
    onSuccess: (_, __, ___) => onSaved(),
    onError: (e) => Alert.alert('Cannot delete', apiErrorMessage(e)),
  });

  function save() {
    if (!name.trim()) return alert('Name is required');
    if (!rate || Number(rate) <= 0) return alert('Enter a valid rate');
    saveMut.mutate();
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.bg }}>
        <View style={styles.modalHeader}>
          <Text style={styles.modalTitle}>{isEdit ? 'Edit product' : 'New product'}</Text>
          <Text style={styles.modalClose} onPress={onClose}>
            Close
          </Text>
        </View>
        <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
          <TextField label="Name *" value={name} onChangeText={setName} placeholder="Product / service name" />

          <Text style={styles.label}>Type</Text>
          <View style={styles.kindRow}>
            {(['goods', 'service'] as const).map((k) => (
              <Pressable
                key={k}
                onPress={() => setKind(k)}
                style={[styles.kindChip, kind === k && styles.kindChipActive]}
              >
                <Text style={[styles.kindText, kind === k && styles.kindTextActive]}>
                  {k === 'goods' ? 'Goods' : 'Service'}
                </Text>
              </Pressable>
            ))}
          </View>

          <View style={{ height: 14 }} />
          <TextField label="Rate *" value={rate} onChangeText={setRate} keyboardType="decimal-pad" placeholder="0.00" />
          <TextField label="HSN/SAC" value={hsn} onChangeText={setHsn} keyboardType="number-pad" placeholder="4–8 digits (optional)" />

          <Text style={styles.label}>GST rate</Text>
          <View style={styles.gstRow}>
            {GST_RATES.map((r) => (
              <Pressable
                key={r}
                onPress={() => setGstRate(r)}
                style={[styles.gstChip, gstRate === r && styles.gstChipActive]}
              >
                <Text style={[styles.gstChipText, gstRate === r && styles.gstChipTextActive]}>{r}%</Text>
              </Pressable>
            ))}
          </View>

          <View style={{ height: 16 }} />
          <Button title={isEdit ? 'Save changes' : 'Save product'} onPress={save} loading={saveMut.isPending} />

          {isEdit ? (
            <Button
              title="Delete product"
              variant="danger"
              style={{ marginTop: 12 }}
              loading={deleteMut.isPending}
              onPress={() =>
                Alert.alert(
                  'Delete product?',
                  'If it has invoice history it will be archived instead.',
                  [
                    { text: 'Cancel', style: 'cancel' },
                    { text: 'Delete', style: 'destructive', onPress: () => deleteMut.mutate() },
                  ],
                )
              }
            />
          ) : null}
        </ScrollView>
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
  rate: { fontSize: 16, fontWeight: '700', color: colors.text },
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
  kindRow: { flexDirection: 'row', gap: 10 },
  kindChip: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    backgroundColor: colors.card,
  },
  kindChipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  kindText: { color: colors.muted, fontWeight: '600' },
  kindTextActive: { color: '#fff' },
  gstRow: { flexDirection: 'row', gap: 8, flexWrap: 'wrap' },
  gstChip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.card,
  },
  gstChipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  gstChipText: { color: colors.muted, fontWeight: '600' },
  gstChipTextActive: { color: '#fff' },
});
