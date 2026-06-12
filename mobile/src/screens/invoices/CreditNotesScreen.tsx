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
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import {
  createCreditNote,
  deleteCreditNote,
  getCreditNotes,
} from '../../api/endpoints';
import { downloadAndShare } from '../../api/files';
import { apiErrorMessage } from '../../api/client';
import { Button, Card, EmptyState, TextField } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { InvoicesStackParamList } from '../../navigation/types';
import type { CreditNoteReason } from '../../api/types';

type Props = NativeStackScreenProps<InvoicesStackParamList, 'CreditNotes'>;

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

export default function CreditNotesScreen({ route }: Props) {
  const { invoiceId, invoiceNumber } = route.params;
  const qc = useQueryClient();
  const [creating, setCreating] = useState(false);

  const { data, isLoading, isError, error, refetch, isRefetching } = useQuery({
    queryKey: ['credit-notes', invoiceId],
    queryFn: () => getCreditNotes(invoiceId),
  });

  function invalidate() {
    qc.invalidateQueries({ queryKey: ['credit-notes', invoiceId] });
    qc.invalidateQueries({ queryKey: ['invoice', invoiceId] });
    qc.invalidateQueries({ queryKey: ['invoices'] });
    qc.invalidateQueries({ queryKey: ['dashboard'] });
  }

  const deleteMut = useMutation({
    mutationFn: (id: number) => deleteCreditNote(id),
    onSuccess: invalidate,
    onError: (e) => Alert.alert('Cannot reverse', apiErrorMessage(e)),
  });

  async function sharePdf(id: number, number: string | null) {
    try {
      await downloadAndShare(`/credit-notes/${id}/pdf`, `credit-note-${number ?? id}.pdf`);
    } catch (e) {
      Alert.alert('Error', apiErrorMessage(e));
    }
  }

  if (isLoading) {
    return <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 40 }} />;
  }
  if (isError) {
    return <Text style={styles.error}>{apiErrorMessage(error)}</Text>;
  }

  const meta = data!.meta;

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <FlatList
        data={data!.data}
        keyExtractor={(c) => String(c.id)}
        refreshing={isRefetching}
        onRefresh={refetch}
        contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
        ListHeaderComponent={
          <Card style={styles.summary}>
            <Text style={styles.summaryTitle}>Credit notes · {invoiceNumber}</Text>
            <Text style={styles.summarySub}>Creditable balance</Text>
            <Text style={styles.summaryAmt}>{formatINR(meta.creditable)}</Text>
            {meta.window_closed ? (
              <Text style={styles.warn}>
                Section 34(2) window closed{meta.deadline ? ` on ${meta.deadline}` : ''}. New credit
                notes can't reduce GST liability.
              </Text>
            ) : null}
            {meta.can_create ? (
              <Button title="+ New credit note" onPress={() => setCreating(true)} style={{ marginTop: 14 }} />
            ) : null}
          </Card>
        }
        ListEmptyComponent={
          <EmptyState title="No credit notes" subtitle="Issue one to adjust this invoice." />
        }
        renderItem={({ item }) => (
          <Card style={styles.row}>
            <View style={styles.rowTop}>
              <Text style={styles.cnNumber}>{item.credit_note_number ?? '—'}</Text>
              <Text style={styles.cnAmount}>{formatINR(item.amount)}</Text>
            </View>
            <Text style={styles.cnSub}>
              {item.credit_note_date} · {item.reason_label}
            </Text>
            {item.notes ? <Text style={styles.cnNotes}>{item.notes}</Text> : null}
            <View style={styles.rowActions}>
              <Pressable style={styles.linkBtn} onPress={() => sharePdf(item.id, item.credit_note_number)}>
                <Text style={styles.linkBtnText}>Share PDF</Text>
              </Pressable>
              <Pressable
                style={styles.linkBtn}
                onPress={() =>
                  Alert.alert('Reverse credit note?', 'This restores the invoice balance.', [
                    { text: 'Cancel', style: 'cancel' },
                    { text: 'Reverse', style: 'destructive', onPress: () => deleteMut.mutate(item.id) },
                  ])
                }
              >
                <Text style={[styles.linkBtnText, { color: colors.danger }]}>Reverse</Text>
              </Pressable>
            </View>
          </Card>
        )}
      />

      <CreditNoteCreateModal
        visible={creating}
        max={meta.creditable}
        reasons={meta.reasons}
        onClose={() => setCreating(false)}
        onSave={async (input) => {
          await createCreditNote(invoiceId, input);
          setCreating(false);
          invalidate();
        }}
      />
    </View>
  );
}

function CreditNoteCreateModal({
  visible,
  max,
  reasons,
  onClose,
  onSave,
}: {
  visible: boolean;
  max: number;
  reasons: CreditNoteReason[];
  onClose: () => void;
  onSave: (input: { credit_note_date: string; amount: number; reason: string; notes?: string }) => Promise<void>;
}) {
  const [amount, setAmount] = useState('');
  const [date, setDate] = useState(today());
  const [reason, setReason] = useState<string | null>(null);
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);

  React.useEffect(() => {
    if (visible) {
      setAmount(String(max));
      setDate(today());
      setReason(null);
      setNotes('');
    }
  }, [visible, max]);

  async function save() {
    const amt = parseFloat(amount);
    if (!amt || amt <= 0) return Alert.alert('Enter a valid amount');
    if (amt > max) return Alert.alert('Amount exceeds creditable balance', `Max ${formatINR(max)}`);
    if (!reason) return Alert.alert('Pick a reason');
    setSaving(true);
    try {
      await onSave({ credit_note_date: date, amount: amt, reason, notes: notes || undefined });
    } catch (e) {
      Alert.alert('Could not issue', apiErrorMessage(e));
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.bg }}>
        <View style={styles.modalHeader}>
          <Text style={styles.modalTitle}>New credit note</Text>
          <Text style={styles.modalClose} onPress={onClose}>
            Close
          </Text>
        </View>
        <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
          <TextField
            label={`Amount * (max ${formatINR(max)})`}
            keyboardType="decimal-pad"
            value={amount}
            onChangeText={setAmount}
          />
          <TextField label="Date *" value={date} onChangeText={setDate} placeholder="YYYY-MM-DD" autoCapitalize="none" />

          <Text style={styles.label}>Reason *</Text>
          <View style={styles.reasonWrap}>
            {reasons.map((r) => {
              const active = reason === r.value;
              return (
                <Pressable
                  key={r.value}
                  style={[styles.reasonChip, active && styles.reasonChipActive]}
                  onPress={() => setReason(r.value)}
                >
                  <Text style={[styles.reasonChipText, active && styles.reasonChipTextActive]}>{r.label}</Text>
                </Pressable>
              );
            })}
          </View>

          <View style={{ height: 14 }} />
          <TextField
            label="Notes"
            value={notes}
            onChangeText={setNotes}
            placeholder="Optional"
            multiline
            style={{ height: 90, textAlignVertical: 'top' }}
          />
          <Button title="Issue credit note" onPress={save} loading={saving} style={{ marginTop: 8 }} />
        </ScrollView>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  error: { color: colors.danger, textAlign: 'center', marginTop: 40 },
  summary: { marginBottom: 14 },
  summaryTitle: { fontSize: 16, fontWeight: '800', color: colors.text },
  summarySub: { fontSize: 13, color: colors.muted, marginTop: 12 },
  summaryAmt: { fontSize: 24, fontWeight: '800', color: colors.text, marginTop: 2 },
  warn: { fontSize: 13, color: colors.warning, marginTop: 12, lineHeight: 18 },
  row: { marginBottom: 10 },
  rowTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  cnNumber: { fontSize: 15, fontWeight: '700', color: colors.text },
  cnAmount: { fontSize: 15, fontWeight: '800', color: colors.converted },
  cnSub: { fontSize: 13, color: colors.muted, marginTop: 4 },
  cnNotes: { fontSize: 13, color: colors.text, marginTop: 6 },
  rowActions: { flexDirection: 'row', marginTop: 12, gap: 10 },
  linkBtn: { borderWidth: 1, borderColor: colors.border, borderRadius: 8, paddingHorizontal: 12, paddingVertical: 8 },
  linkBtnText: { fontSize: 13, fontWeight: '700', color: colors.primary },
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
  reasonWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  reasonChip: { borderWidth: 1, borderColor: colors.border, borderRadius: 999, paddingHorizontal: 14, paddingVertical: 8, backgroundColor: colors.card },
  reasonChipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  reasonChipText: { fontSize: 13, fontWeight: '600', color: colors.text },
  reasonChipTextActive: { color: '#fff' },
});
