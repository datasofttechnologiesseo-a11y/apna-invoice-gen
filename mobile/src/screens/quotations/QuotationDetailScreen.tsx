import React from 'react';
import { ActivityIndicator, Alert, Linking, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import {
  acceptQuotation,
  convertQuotation,
  declineQuotation,
  deleteQuotation,
  getQuotation,
  getQuotationShareLink,
  sendQuotation,
} from '../../api/endpoints';
import { apiErrorMessage } from '../../api/client';
import { Button, Card, Centered, StatusBadge } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { QuotationsStackParamList } from '../../navigation/types';

type Props = NativeStackScreenProps<QuotationsStackParamList, 'QuotationDetail'>;

export default function QuotationDetailScreen({ route, navigation }: Props) {
  const { id } = route.params;
  const qc = useQueryClient();

  const { data: quote, isLoading, isError, error } = useQuery({
    queryKey: ['quotation', id],
    queryFn: () => getQuotation(id),
  });

  function invalidate() {
    qc.invalidateQueries({ queryKey: ['quotation', id] });
    qc.invalidateQueries({ queryKey: ['quotations'] });
    qc.invalidateQueries({ queryKey: ['dashboard'] });
  }

  const sendMut = useMutation({
    mutationFn: () => sendQuotation(id),
    onSuccess: invalidate,
    onError: (e) => Alert.alert('Cannot send', apiErrorMessage(e)),
  });
  const acceptMut = useMutation({
    mutationFn: () => acceptQuotation(id),
    onSuccess: invalidate,
    onError: (e) => Alert.alert('Cannot accept', apiErrorMessage(e)),
  });
  const declineMut = useMutation({
    mutationFn: () => declineQuotation(id),
    onSuccess: invalidate,
    onError: (e) => Alert.alert('Cannot decline', apiErrorMessage(e)),
  });
  const deleteMut = useMutation({
    mutationFn: () => deleteQuotation(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['quotations'] });
      navigation.goBack();
    },
    onError: (e) => Alert.alert('Cannot delete', apiErrorMessage(e)),
  });
  const convertMut = useMutation({
    mutationFn: () => convertQuotation(id),
    onSuccess: (invoice) => {
      invalidate();
      qc.invalidateQueries({ queryKey: ['invoices'] });
      Alert.alert(
        'Converted',
        `Created draft invoice ${invoice.display_number}. Open the Invoices tab to review and issue it.`,
      );
    },
    onError: (e) => Alert.alert('Cannot convert', apiErrorMessage(e)),
  });

  if (isLoading) {
    return (
      <Centered>
        <ActivityIndicator size="large" color={colors.primary} />
      </Centered>
    );
  }
  if (isError || !quote) {
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
          <Text style={styles.number}>{quote.display_number}</Text>
          <StatusBadge status={quote.effective_status} />
        </View>
        {quote.subject ? <Text style={styles.subject}>{quote.subject}</Text> : null}
        <Text style={styles.customer}>{quote.customer?.name ?? '—'}</Text>
        <View style={styles.metaRow}>
          <Text style={styles.meta}>Date: {quote.quote_date}</Text>
          {quote.valid_until ? <Text style={styles.meta}>Valid till: {quote.valid_until}</Text> : null}
        </View>
        {quote.is_expired ? <Text style={styles.expired}>This quotation has expired.</Text> : null}
      </Card>

      <Card style={{ marginTop: 12 }}>
        <Text style={styles.sectionTitle}>Items</Text>
        {quote.items?.map((item) => (
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
        <Row label="Subtotal" value={formatINR(quote.subtotal)} />
        {quote.total_cgst > 0 ? <Row label="CGST" value={formatINR(quote.total_cgst)} /> : null}
        {quote.total_sgst > 0 ? <Row label="SGST" value={formatINR(quote.total_sgst)} /> : null}
        {quote.total_igst > 0 ? <Row label="IGST" value={formatINR(quote.total_igst)} /> : null}
        <Row label="Grand total" value={formatINR(quote.grand_total)} bold />
      </Card>

      <View style={styles.actions}>
        {quote.can.send ? (
          <Button title="Mark as sent" onPress={() => sendMut.mutate()} loading={sendMut.isPending} />
        ) : null}
        {quote.status !== 'draft' ? (
          <Button
            title="View / Download PDF"
            variant="secondary"
            onPress={async () => {
              try {
                const { url } = await getQuotationShareLink(id);
                await Linking.openURL(url);
              } catch (e) {
                Alert.alert('Error', apiErrorMessage(e));
              }
            }}
            style={{ marginTop: 10 }}
          />
        ) : null}
        {quote.whatsapp_link && quote.status !== 'draft' ? (
          <Button
            title="Share on WhatsApp"
            variant="secondary"
            onPress={() => Linking.openURL(quote.whatsapp_link!)}
            style={{ marginTop: 10 }}
          />
        ) : null}
        {quote.can.accept ? (
          <Button
            title="Mark as accepted"
            onPress={() => acceptMut.mutate()}
            loading={acceptMut.isPending}
            style={{ marginTop: 10 }}
          />
        ) : null}
        {quote.can.convert ? (
          <Button
            title="Convert to invoice"
            onPress={() => convertMut.mutate()}
            loading={convertMut.isPending}
            style={{ marginTop: 10 }}
          />
        ) : null}
        {quote.can.edit ? (
          <Button
            title="Edit"
            variant="secondary"
            onPress={() => navigation.navigate('QuotationEdit', { id })}
            style={{ marginTop: 10 }}
          />
        ) : null}
        {quote.can.decline ? (
          <Button
            title="Mark as declined"
            variant="secondary"
            onPress={() =>
              Alert.alert('Mark declined?', 'The customer rejected this quote.', [
                { text: 'Cancel', style: 'cancel' },
                { text: 'Decline', onPress: () => declineMut.mutate() },
              ])
            }
            style={{ marginTop: 10 }}
          />
        ) : null}
        {quote.can.delete ? (
          <Button
            title="Delete draft"
            variant="danger"
            onPress={() =>
              Alert.alert('Delete draft?', 'This cannot be undone.', [
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

function Row({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
  return (
    <View style={styles.totalRow}>
      <Text style={[styles.totalLabel, bold && { fontWeight: '800', color: colors.text }]}>{label}</Text>
      <Text style={[styles.totalValue, bold && { fontWeight: '800' }]}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  headerRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  number: { fontSize: 20, fontWeight: '800', color: colors.text },
  subject: { fontSize: 15, color: colors.text, marginTop: 8, fontWeight: '600' },
  customer: { fontSize: 16, color: colors.text, marginTop: 6 },
  metaRow: { flexDirection: 'row', gap: 16, marginTop: 6 },
  meta: { fontSize: 13, color: colors.muted },
  expired: { fontSize: 13, color: colors.danger, marginTop: 8 },
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
