import React, { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import {
  createQuotation,
  getCustomers,
  getProducts,
  getQuotation,
  updateQuotation,
} from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Button, Card, Centered, TextField } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { InvoiceItemInput } from '../../api/types';
import type { QuotationsStackParamList } from '../../navigation/types';

type Props = NativeStackScreenProps<QuotationsStackParamList, 'QuotationEdit'>;

interface LineItem extends InvoiceItemInput {
  _key: string;
}

const GST_RATES = [0, 5, 12, 18, 28];

function newLine(): LineItem {
  return {
    _key: Math.random().toString(36).slice(2),
    description: '',
    hsn_sac: '',
    quantity: 1,
    rate: 0,
    gst_rate: 18,
  };
}

export default function QuotationEditScreen({ route, navigation }: Props) {
  const editId = route.params?.id;
  const isEdit = !!editId;
  const qc = useQueryClient();

  const [customerId, setCustomerId] = useState<number | null>(null);
  const [items, setItems] = useState<LineItem[]>([newLine()]);
  const [subject, setSubject] = useState('');
  const [quoteDate] = useState(new Date().toISOString().slice(0, 10));
  const [validUntil] = useState(new Date(Date.now() + 15 * 864e5).toISOString().slice(0, 10));
  const [customerModal, setCustomerModal] = useState(false);
  const [productModalFor, setProductModalFor] = useState<string | null>(null);

  const customersQ = useQuery({ queryKey: ['customers'], queryFn: () => getCustomers() });
  const productsQ = useQuery({ queryKey: ['products'], queryFn: () => getProducts() });

  const existingQ = useQuery({
    queryKey: ['quotation', editId],
    queryFn: () => getQuotation(editId!),
    enabled: isEdit,
  });

  useEffect(() => {
    if (existingQ.data) {
      setCustomerId(existingQ.data.customer_id);
      setSubject(existingQ.data.subject ?? '');
      setItems(
        (existingQ.data.items ?? []).map((it) => ({
          _key: String(it.id),
          product_id: it.product_id,
          description: it.description,
          hsn_sac: it.hsn_sac ?? '',
          quantity: it.quantity,
          rate: it.rate,
          gst_rate: it.gst_rate,
          discount: it.discount,
        })),
      );
    }
  }, [existingQ.data]);

  const selectedCustomer = customersQ.data?.find((c) => c.id === customerId);

  const preview = useMemo(() => {
    let subtotal = 0;
    let tax = 0;
    for (const it of items) {
      const gross = (Number(it.quantity) || 0) * (Number(it.rate) || 0);
      const disc = Math.min(Number(it.discount) || 0, gross);
      const amount = gross - disc;
      subtotal += amount;
      tax += amount * ((Number(it.gst_rate) || 0) / 100);
    }
    return { subtotal, tax, grand: Math.round(subtotal + tax) };
  }, [items]);

  const saveMut = useMutation({
    mutationFn: async () => {
      const payload = {
        customer_id: customerId!,
        quote_date: quoteDate,
        valid_until: validUntil,
        subject: subject || undefined,
        items: items.map((it) => ({
          product_id: it.product_id ?? null,
          description: it.description,
          hsn_sac: it.hsn_sac,
          quantity: Number(it.quantity),
          rate: Number(it.rate),
          gst_rate: Number(it.gst_rate),
          discount: Number(it.discount) || 0,
        })),
      };
      return isEdit ? updateQuotation(editId!, payload) : createQuotation(payload);
    },
    onSuccess: (q) => {
      qc.invalidateQueries({ queryKey: ['quotations'] });
      qc.invalidateQueries({ queryKey: ['quotation', q.id] });
      navigation.replace('QuotationDetail', { id: q.id });
    },
    onError: (e) => Alert.alert('Could not save', apiErrorMessage(e)),
  });

  function updateItem(key: string, patch: Partial<LineItem>) {
    setItems((prev) => prev.map((it) => (it._key === key ? { ...it, ...patch } : it)));
  }
  function removeItem(key: string) {
    setItems((prev) => (prev.length === 1 ? prev : prev.filter((it) => it._key !== key)));
  }

  function onSave() {
    if (!customerId) return Alert.alert('Pick a customer first');
    if (items.some((it) => !it.rate || it.rate <= 0)) {
      return Alert.alert('Each line needs a rate greater than 0');
    }
    saveMut.mutate();
  }

  if (isEdit && existingQ.isLoading) {
    return (
      <Centered>
        <ActivityIndicator size="large" color={colors.primary} />
      </Centered>
    );
  }

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.bg }} contentContainerStyle={{ padding: 16 }}>
      <Text style={styles.title}>{isEdit ? 'Edit quotation' : 'New quotation'}</Text>

      <Card>
        <Text style={styles.label}>Customer</Text>
        <Pressable style={styles.picker} onPress={() => setCustomerModal(true)}>
          <Text style={selectedCustomer ? styles.pickerText : styles.pickerPlaceholder}>
            {selectedCustomer ? selectedCustomer.name : 'Select a customer'}
          </Text>
        </Pressable>
        <View style={{ height: 14 }} />
        <TextField label="Subject" value={subject} onChangeText={setSubject} placeholder="e.g. Website project" />
        <Text style={styles.meta}>Date: {quoteDate} · Valid till: {validUntil}</Text>
      </Card>

      <Text style={[styles.sectionTitle, { marginTop: 16 }]}>Line items</Text>
      {items.map((it, idx) => (
        <Card key={it._key} style={{ marginBottom: 12 }}>
          <View style={styles.itemHeader}>
            <Text style={styles.itemNum}>Item {idx + 1}</Text>
            {items.length > 1 ? (
              <Text style={styles.remove} onPress={() => removeItem(it._key)}>
                Remove
              </Text>
            ) : null}
          </View>

          <Pressable style={{ marginBottom: 12 }} onPress={() => setProductModalFor(it._key)}>
            <Text style={styles.linkPickText}>＋ Pick from products</Text>
          </Pressable>

          <TextField
            label="Description"
            value={it.description}
            onChangeText={(v) => updateItem(it._key, { description: v })}
            placeholder="Item / service description"
          />
          <View style={styles.itemRow}>
            <View style={{ flex: 1 }}>
              <TextField
                label="Qty"
                keyboardType="decimal-pad"
                value={String(it.quantity)}
                onChangeText={(v) => updateItem(it._key, { quantity: Number(v) || 0 })}
              />
            </View>
            <View style={{ flex: 1.4 }}>
              <TextField
                label="Rate"
                keyboardType="decimal-pad"
                value={String(it.rate)}
                onChangeText={(v) => updateItem(it._key, { rate: Number(v) || 0 })}
              />
            </View>
          </View>
          <TextField
            label="HSN/SAC"
            value={it.hsn_sac}
            onChangeText={(v) => updateItem(it._key, { hsn_sac: v })}
            placeholder="Optional"
          />
          <Text style={styles.label}>GST rate</Text>
          <View style={styles.gstRow}>
            {GST_RATES.map((r) => (
              <Pressable
                key={r}
                onPress={() => updateItem(it._key, { gst_rate: r })}
                style={[styles.gstChip, it.gst_rate === r && styles.gstChipActive]}
              >
                <Text style={[styles.gstChipText, it.gst_rate === r && styles.gstChipTextActive]}>
                  {r}%
                </Text>
              </Pressable>
            ))}
          </View>
        </Card>
      ))}

      <Button title="＋ Add item" variant="secondary" onPress={() => setItems((p) => [...p, newLine()])} />

      <Card style={{ marginTop: 16 }}>
        <Row label="Subtotal" value={formatINR(preview.subtotal)} />
        <Row label="GST" value={formatINR(preview.tax)} />
        <Row label="Grand total (approx)" value={formatINR(preview.grand)} bold />
      </Card>

      <Button
        title={isEdit ? 'Save changes' : 'Save draft'}
        onPress={onSave}
        loading={saveMut.isPending}
        style={{ marginTop: 16, marginBottom: 40 }}
      />

      <PickerModal
        visible={customerModal}
        title="Select customer"
        onClose={() => setCustomerModal(false)}
        loading={customersQ.isLoading}
        items={(customersQ.data ?? []).map((c) => ({ id: c.id, label: c.name, sub: c.state?.name }))}
        onPick={(id) => {
          setCustomerId(id);
          setCustomerModal(false);
        }}
        emptyText="No customers yet. Add one from the Customers tab."
      />

      <PickerModal
        visible={!!productModalFor}
        title="Pick product"
        onClose={() => setProductModalFor(null)}
        loading={productsQ.isLoading}
        items={(productsQ.data ?? []).map((p) => ({
          id: p.id,
          label: p.name,
          sub: `${formatINR(p.rate)} · GST ${p.gst_rate}%`,
        }))}
        onPick={(id) => {
          const p = productsQ.data?.find((x) => x.id === id);
          if (p && productModalFor) {
            updateItem(productModalFor, {
              product_id: p.id,
              description: p.name,
              hsn_sac: p.hsn_sac ?? '',
              rate: p.rate,
              gst_rate: p.gst_rate,
            });
          }
          setProductModalFor(null);
        }}
        emptyText="No products yet. Add some from the Products tab."
      />
    </ScrollView>
  );
}

function PickerModal({
  visible,
  title,
  items,
  onPick,
  onClose,
  loading,
  emptyText,
}: {
  visible: boolean;
  title: string;
  items: { id: number; label: string; sub?: string }[];
  onPick: (id: number) => void;
  onClose: () => void;
  loading?: boolean;
  emptyText?: string;
}) {
  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        <View style={styles.modalSheet}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>{title}</Text>
            <Text style={styles.modalClose} onPress={onClose}>
              Close
            </Text>
          </View>
          {loading ? (
            <ActivityIndicator color={colors.primary} style={{ marginVertical: 24 }} />
          ) : items.length === 0 ? (
            <Text style={styles.modalEmpty}>{emptyText}</Text>
          ) : (
            <ScrollView style={{ maxHeight: 400 }}>
              {items.map((it) => (
                <Pressable key={it.id} style={styles.modalItem} onPress={() => onPick(it.id)}>
                  <Text style={styles.modalItemLabel}>{it.label}</Text>
                  {it.sub ? <Text style={styles.modalItemSub}>{it.sub}</Text> : null}
                </Pressable>
              ))}
            </ScrollView>
          )}
        </View>
      </View>
    </Modal>
  );
}

function Row({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
  return (
    <View style={styles.totalRow}>
      <Text style={[styles.totalLabel, bold && { fontWeight: '800', color: colors.text }]}>{label}</Text>
      <Text style={[styles.totalValue, bold && { fontWeight: '800' }]}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  title: { fontSize: 24, fontWeight: '800', color: colors.text, marginBottom: 16 },
  label: { fontSize: 13, fontWeight: '600', color: colors.text, marginBottom: 6 },
  meta: { fontSize: 13, color: colors.muted, marginTop: 8 },
  picker: { borderWidth: 1, borderColor: colors.border, borderRadius: 10, padding: 14, backgroundColor: colors.bg },
  pickerText: { fontSize: 16, color: colors.text },
  pickerPlaceholder: { fontSize: 16, color: colors.muted },
  sectionTitle: { fontSize: 18, fontWeight: '700', color: colors.text, marginBottom: 12 },
  itemHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 },
  itemNum: { fontSize: 14, fontWeight: '700', color: colors.text },
  remove: { fontSize: 13, color: colors.danger, fontWeight: '600' },
  linkPickText: { color: colors.primary, fontWeight: '600' },
  itemRow: { flexDirection: 'row', gap: 12 },
  gstRow: { flexDirection: 'row', gap: 8, flexWrap: 'wrap' },
  gstChip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.bg,
  },
  gstChipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  gstChipText: { color: colors.muted, fontWeight: '600' },
  gstChipTextActive: { color: '#fff' },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  totalLabel: { fontSize: 14, color: colors.muted },
  totalValue: { fontSize: 14, color: colors.text },
  modalBackdrop: { flex: 1, backgroundColor: '#00000066', justifyContent: 'flex-end' },
  modalSheet: {
    backgroundColor: colors.card,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 20,
    paddingBottom: 36,
  },
  modalHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 16 },
  modalTitle: { fontSize: 18, fontWeight: '700', color: colors.text },
  modalClose: { fontSize: 15, color: colors.primary, fontWeight: '600' },
  modalEmpty: { color: colors.muted, paddingVertical: 24, textAlign: 'center' },
  modalItem: { paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  modalItemLabel: { fontSize: 16, color: colors.text, fontWeight: '500' },
  modalItemSub: { fontSize: 13, color: colors.muted, marginTop: 2 },
});
