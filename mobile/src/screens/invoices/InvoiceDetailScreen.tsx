import React, { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Linking,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import {
  cancelInvoice,
  deleteInvoice,
  finalizeInvoice,
  getInvoice,
  getShareLink,
  recordPayment,
  sendInvoiceReminder,
} from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Button, Card, Centered, StatusBadge, TextField } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { InvoicesStackParamList } from '../../navigation/types';

type Props = NativeStackScreenProps<InvoicesStackParamList, 'InvoiceDetail'>;

export default function InvoiceDetailScreen({ route, navigation }: Props) {
  const { id } = route.params;
  const qc = useQueryClient();
  const [payAmount, setPayAmount] = useState('');

  const { data: invoice, isLoading, isError, error } = useQuery({
    queryKey: ['invoice', id],
    queryFn: () => getInvoice(id),
  });

  function invalidate() {
    qc.invalidateQueries({ queryKey: ['invoice', id] });
    qc.invalidateQueries({ queryKey: ['invoices'] });
    qc.invalidateQueries({ queryKey: ['dashboard'] });
  }

  const finalizeMut = useMutation({
    mutationFn: () => finalizeInvoice(id),
    onSuccess: invalidate,
    onError: (e) => Alert.alert('Cannot issue', apiErrorMessage(e)),
  });

  const paymentMut = useMutation({
    mutationFn: (amount: number) =>
      recordPayment(id, {
        amount,
        method: 'cash',
        received_at: new Date().toISOString().slice(0, 10),
      }),
    onSuccess: () => {
      setPayAmount('');
      invalidate();
    },
    onError: (e) => Alert.alert('Payment failed', apiErrorMessage(e)),
  });

  const deleteMut = useMutation({
    mutationFn: () => deleteInvoice(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['invoices'] });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
      navigation.goBack();
    },
    onError: (e) => Alert.alert('Cannot delete', apiErrorMessage(e)),
  });

  const cancelMut = useMutation({
    mutationFn: (reason: string) => cancelInvoice(id, reason),
    onSuccess: invalidate,
    onError: (e) => Alert.alert('Cannot cancel', apiErrorMessage(e)),
  });

  const reminderMut = useMutation({
    mutationFn: (channel: 'email' | 'whatsapp' | 'sms') => sendInvoiceReminder(id, channel),
    onSuccess: (res) => Alert.alert('Reminder', res.message),
    onError: (e) => Alert.alert('Reminder failed', apiErrorMessage(e)),
  });

  async function onShare() {
    try {
      const { url, whatsapp_link } = await getShareLink(id);
      Alert.alert('Share invoice', url, [
        whatsapp_link
          ? { text: 'WhatsApp', onPress: () => Linking.openURL(whatsapp_link) }
          : { text: 'Open link', onPress: () => Linking.openURL(url) },
        { text: 'Close', style: 'cancel' },
      ]);
    } catch (e) {
      Alert.alert('Error', apiErrorMessage(e));
    }
  }

  // Opens the signed public PDF in the device browser, which renders/downloads
  // it natively (works on Android Chrome + iOS Safari over the LAN host).
  async function onViewPdf() {
    try {
      const { url } = await getShareLink(id);
      await Linking.openURL(url);
    } catch (e) {
      Alert.alert('Error', apiErrorMessage(e));
    }
  }

  function onRemind() {
    Alert.alert('Send payment reminder', 'Choose a channel', [
      { text: 'WhatsApp', onPress: () => reminderMut.mutate('whatsapp') },
      { text: 'Email', onPress: () => reminderMut.mutate('email') },
      { text: 'SMS', onPress: () => reminderMut.mutate('sms') },
      { text: 'Cancel', style: 'cancel' },
    ]);
  }

  function confirmCancel() {
    Alert.prompt?.(
      'Cancel invoice',
      'Reason (min 5 chars):',
      (reason) => reason && cancelMut.mutate(reason),
    ) ?? cancelMut.mutate('Cancelled via mobile app');
  }

  if (isLoading) {
    return (
      <Centered>
        <ActivityIndicator size="large" color={colors.primary} />
      </Centered>
    );
  }
  if (isError || !invoice) {
    return (
      <Centered>
        <Text style={{ color: colors.danger, padding: 24 }}>{apiErrorMessage(error)}</Text>
      </Centered>
    );
  }

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.bg }} contentContainerStyle={{ padding: 16 }}>
      <Card>
        <View style={styles.headerRow}>
          <Text style={styles.number}>{invoice.display_number}</Text>
          <StatusBadge status={invoice.status} />
        </View>
        <Text style={styles.title}>{invoice.document_title}</Text>
        <Text style={styles.customer}>{invoice.customer?.name ?? '—'}</Text>
        <View style={styles.metaRow}>
          <Text style={styles.meta}>Date: {invoice.invoice_date}</Text>
          {invoice.due_date ? <Text style={styles.meta}>Due: {invoice.due_date}</Text> : null}
        </View>
      </Card>

      <Card style={{ marginTop: 12 }}>
        <Text style={styles.sectionTitle}>Items</Text>
        {invoice.items?.map((item) => (
          <View key={item.id} style={styles.item}>
            <View style={{ flex: 1 }}>
              <Text style={styles.itemDesc}>{item.description}</Text>
              <Text style={styles.itemSub}>
                {item.quantity} × {formatINR(item.rate)} · GST {item.gst_rate}%
              </Text>
            </View>
            <Text style={styles.itemTotal}>{formatINR(item.total)}</Text>
          </View>
        ))}

        <View style={styles.divider} />
        <Row label="Subtotal" value={formatINR(invoice.subtotal)} />
        {invoice.total_cgst > 0 ? <Row label="CGST" value={formatINR(invoice.total_cgst)} /> : null}
        {invoice.total_sgst > 0 ? <Row label="SGST" value={formatINR(invoice.total_sgst)} /> : null}
        {invoice.total_igst > 0 ? <Row label="IGST" value={formatINR(invoice.total_igst)} /> : null}
        {invoice.round_off !== 0 ? <Row label="Round off" value={formatINR(invoice.round_off)} /> : null}
        <Row label="Grand total" value={formatINR(invoice.grand_total)} bold />
        {invoice.paid_amount > 0 ? <Row label="Paid" value={formatINR(invoice.paid_amount)} /> : null}
        {invoice.balance > 0 && invoice.status !== 'draft' ? (
          <Row label="Balance due" value={formatINR(invoice.balance)} bold color={colors.warning} />
        ) : null}
      </Card>

      {invoice.payments && invoice.payments.length > 0 ? (
        <Card style={{ marginTop: 12 }}>
          <Text style={styles.sectionTitle}>Payments</Text>
          {invoice.payments.map((p) => (
            <View key={p.id} style={styles.item}>
              <Text style={styles.itemDesc}>
                {p.receipt_number} · {p.method_label}
              </Text>
              <Text style={styles.itemTotal}>{formatINR(p.amount)}</Text>
            </View>
          ))}
        </Card>
      ) : null}

      <View style={styles.actions}>
        {invoice.can.finalize ? (
          <Button
            title="Issue invoice"
            onPress={() => finalizeMut.mutate()}
            loading={finalizeMut.isPending}
          />
        ) : null}

        {!invoice.can.finalize && invoice.status !== 'cancelled' && invoice.balance > 0 ? (
          <Card style={{ marginTop: 4 }}>
            <Text style={styles.sectionTitle}>Record payment</Text>
            <TextField
              label="Amount (cash)"
              keyboardType="decimal-pad"
              value={payAmount}
              onChangeText={setPayAmount}
              placeholder={String(invoice.balance)}
            />
            <Button
              title="Record payment"
              onPress={() => {
                const amt = parseFloat(payAmount || String(invoice.balance));
                if (!amt || amt <= 0) return Alert.alert('Enter a valid amount');
                paymentMut.mutate(amt);
              }}
              loading={paymentMut.isPending}
            />
          </Card>
        ) : null}

        {invoice.status !== 'draft' && invoice.status !== 'cancelled' ? (
          <>
            <Button title="View / Download PDF" variant="secondary" onPress={onViewPdf} style={{ marginTop: 10 }} />
            <Button title="Share / WhatsApp" variant="secondary" onPress={onShare} style={{ marginTop: 10 }} />
          </>
        ) : null}

        {invoice.status !== 'draft' && invoice.status !== 'cancelled' && invoice.balance > 0 ? (
          <Button
            title="Send payment reminder"
            variant="secondary"
            onPress={onRemind}
            loading={reminderMut.isPending}
            style={{ marginTop: 10 }}
          />
        ) : null}

        {invoice.status !== 'draft' && invoice.status !== 'cancelled' ? (
          <Button
            title="Credit notes"
            variant="secondary"
            onPress={() =>
              navigation.navigate('CreditNotes', { invoiceId: id, invoiceNumber: invoice.display_number })
            }
            style={{ marginTop: 10 }}
          />
        ) : null}

        {invoice.can.edit ? (
          <Button
            title="Edit"
            variant="secondary"
            onPress={() => navigation.navigate('InvoiceEdit', { id })}
            style={{ marginTop: 10 }}
          />
        ) : null}

        {invoice.can.cancel ? (
          <Button title="Cancel invoice" variant="danger" onPress={confirmCancel} style={{ marginTop: 10 }} />
        ) : null}

        {invoice.can.delete ? (
          <Button
            title="Delete"
            variant="danger"
            onPress={() =>
              Alert.alert('Delete invoice?', 'This cannot be undone.', [
                { text: 'Cancel', style: 'cancel' },
                { text: 'Delete', style: 'destructive', onPress: () => deleteMut.mutate() },
              ])
            }
            style={{ marginTop: 10 }}
          />
        ) : null}
      </View>
    </ScrollView>
  );
}

function Row({
  label,
  value,
  bold,
  color,
}: {
  label: string;
  value: string;
  bold?: boolean;
  color?: string;
}) {
  return (
    <View style={styles.totalRow}>
      <Text style={[styles.totalLabel, bold && { fontWeight: '800', color: colors.text }]}>{label}</Text>
      <Text style={[styles.totalValue, bold && { fontWeight: '800' }, color ? { color } : null]}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  headerRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  number: { fontSize: 20, fontWeight: '800', color: colors.text },
  title: { fontSize: 13, color: colors.muted, marginTop: 4 },
  customer: { fontSize: 17, fontWeight: '600', color: colors.text, marginTop: 8 },
  metaRow: { flexDirection: 'row', gap: 16, marginTop: 6 },
  meta: { fontSize: 13, color: colors.muted },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: colors.text, marginBottom: 10 },
  item: { flexDirection: 'row', alignItems: 'center', paddingVertical: 6 },
  itemDesc: { fontSize: 14, color: colors.text, fontWeight: '500' },
  itemSub: { fontSize: 12, color: colors.muted, marginTop: 2 },
  itemTotal: { fontSize: 14, fontWeight: '700', color: colors.text },
  divider: { height: 1, backgroundColor: colors.border, marginVertical: 10 },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  totalLabel: { fontSize: 14, color: colors.muted },
  totalValue: { fontSize: 14, color: colors.text },
  actions: { marginTop: 16, marginBottom: 40 },
});
