import React from 'react';
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { deleteCashMemo, getCashMemo } from '../../api/endpoints';
import { downloadAndShare } from '../../api/files';
import { apiErrorMessage } from '../../api/client';
import { Button, Card, Centered } from '../../components/ui';
import { colors, formatINR } from '../../theme';
import type { FinanceStackParamList } from '../../navigation/types';

type Props = NativeStackScreenProps<FinanceStackParamList, 'CashMemoDetail'>;

export default function CashMemoDetailScreen({ route, navigation }: Props) {
  const { id } = route.params;
  const qc = useQueryClient();

  const { data: memo, isLoading, isError, error } = useQuery({
    queryKey: ['cash-memo', id],
    queryFn: () => getCashMemo(id),
  });

  const deleteMut = useMutation({
    mutationFn: () => deleteCashMemo(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cash-memos'] });
      navigation.goBack();
    },
    onError: (e) => Alert.alert('Cannot delete', apiErrorMessage(e)),
  });

  async function sharePdf() {
    try {
      await downloadAndShare(`/cash-memos/${id}/pdf`, `cash-memo-${memo?.memo_number ?? id}.pdf`);
    } catch (e) {
      Alert.alert('Error', apiErrorMessage(e));
    }
  }

  if (isLoading) {
    return (
      <Centered>
        <ActivityIndicator size="large" color={colors.primary} />
      </Centered>
    );
  }
  if (isError || !memo) {
    return (
      <Centered>
        <Text style={{ color: colors.danger, padding: 24 }}>{apiErrorMessage(error)}</Text>
      </Centered>
    );
  }

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.bg }} contentContainerStyle={{ padding: 16 }}>
      <Card>
        <Text style={styles.number}>{memo.memo_number}</Text>
        <Text style={styles.seller}>{memo.seller_name}</Text>
        {memo.seller_gstin ? <Text style={styles.meta}>GSTIN: {memo.seller_gstin}</Text> : null}
        {memo.seller_state ? <Text style={styles.meta}>State: {memo.seller_state}</Text> : null}
        <View style={styles.metaRow}>
          <Text style={styles.meta}>Date: {memo.memo_date}</Text>
          <Text style={styles.meta}>Paid via: {memo.payment_mode?.toUpperCase()}</Text>
        </View>
      </Card>

      <Card style={{ marginTop: 12 }}>
        <Text style={styles.sectionTitle}>Items</Text>
        {memo.items.map((item) => (
          <View key={item.id} style={styles.item}>
            <View style={{ flex: 1 }}>
              <Text style={styles.itemDesc}>{item.description}</Text>
              <Text style={styles.itemSub}>
                {item.quantity} × {formatINR(item.rate)}
                {item.hsn_sac ? ` · HSN ${item.hsn_sac}` : ''}
              </Text>
            </View>
            <Text style={styles.itemTotal}>{formatINR(item.amount)}</Text>
          </View>
        ))}

        <View style={styles.divider} />
        <Row label="Subtotal" value={formatINR(memo.subtotal)} />
        {memo.discount > 0 ? <Row label="Discount" value={`− ${formatINR(memo.discount)}`} /> : null}
        <Row label="Taxable value" value={formatINR(memo.taxable_value)} />
        {memo.total_cgst > 0 ? <Row label="CGST" value={formatINR(memo.total_cgst)} /> : null}
        {memo.total_sgst > 0 ? <Row label="SGST" value={formatINR(memo.total_sgst)} /> : null}
        {memo.total_igst > 0 ? <Row label="IGST" value={formatINR(memo.total_igst)} /> : null}
        {memo.round_off !== 0 ? <Row label="Round off" value={formatINR(memo.round_off)} /> : null}
        <Row label="Grand total" value={formatINR(memo.grand_total)} bold />
      </Card>

      {memo.notes ? (
        <Card style={{ marginTop: 12 }}>
          <Text style={styles.sectionTitle}>Notes</Text>
          <Text style={styles.notes}>{memo.notes}</Text>
        </Card>
      ) : null}

      <View style={{ marginTop: 16, marginBottom: 40 }}>
        <Button title="Share / Download PDF" variant="secondary" onPress={sharePdf} />
        <Button
          title="Delete cash memo"
          variant="danger"
          style={{ marginTop: 10 }}
          loading={deleteMut.isPending}
          onPress={() =>
            Alert.alert('Delete cash memo?', 'The linked expense entry is removed too.', [
              { text: 'Cancel', style: 'cancel' },
              { text: 'Delete', style: 'destructive', onPress: () => deleteMut.mutate() },
            ])
          }
        />
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
  number: { fontSize: 20, fontWeight: '800', color: colors.text },
  seller: { fontSize: 16, fontWeight: '600', color: colors.text, marginTop: 6 },
  metaRow: { flexDirection: 'row', gap: 16, marginTop: 6 },
  meta: { fontSize: 13, color: colors.muted, marginTop: 2 },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: colors.text, marginBottom: 10 },
  item: { flexDirection: 'row', alignItems: 'center', paddingVertical: 6 },
  itemDesc: { fontSize: 14, color: colors.text, fontWeight: '500' },
  itemSub: { fontSize: 12, color: colors.muted, marginTop: 2 },
  itemTotal: { fontSize: 14, fontWeight: '700', color: colors.text },
  divider: { height: 1, backgroundColor: colors.border, marginVertical: 10 },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  totalLabel: { fontSize: 14, color: colors.muted },
  totalValue: { fontSize: 14, color: colors.text },
  notes: { fontSize: 14, color: colors.text, lineHeight: 20 },
});
