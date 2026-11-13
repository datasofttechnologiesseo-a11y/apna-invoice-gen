import { Href, router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Alert, View } from 'react-native';

import { cashMemosApi, MEMO_MODES } from '@/api/cash-memos';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, KeyValue, Loading, SectionTitle } from '@/components/blocks';
import { Button } from '@/components/button';
import { BarAction, Screen } from '@/components/screen';
import { useCashMemo, useDeleteCashMemo } from '@/hooks/cash-memos';
import { useExpenseCategories } from '@/hooks/expenses';
import { fileSafe } from '@/screens/finance/periods';
import { colors, fonts } from '@/theme';
import { date, money } from '@/utils/format';
import { downloadAndShare } from '@/utils/share';

/** Cash memo detail: lines, GST, PDF share and delete (deletes its expense entry too). Editing comes later. */
export default function CashMemoDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useCashMemo(Number(id));
  const remove = useDeleteCashMemo();
  const cats = useExpenseCategories();
  const [sharing, setSharing] = useState(false);
  const m = q.data;

  if (q.isLoading) return <Screen hero nav="back" title="Cash memo"><Loading /></Screen>;
  if (q.error || !m) return <Screen hero nav="back" title="Cash memo"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;

  const sharePdf = async () => {
    setSharing(true);
    await downloadAndShare(cashMemosApi.pdfPath(m.id), `cash-memo-${fileSafe(m.memo_number)}.pdf`);
    setSharing(false);
  };

  const confirmDelete = () =>
    Alert.alert('Delete this cash memo?', `${m.memo_number} and its expense entry (${money(m.grand_total)}) will be removed. This cannot be undone.`, [
      { text: 'Keep', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () => remove.mutate(m.id, { onSuccess: () => router.back(), onError: (e) => Alert.alert('Could not delete', (e as Error).message) }),
      },
    ]);

  const gst = m.total_cgst + m.total_sgst + m.total_igst;
  const category = cats.data?.find((c) => c.value === m.expense_category)?.label ?? m.expense_category;

  return (
    <Screen
      hero
      nav="back"
      title={m.memo_number}
      subtitle="Cash memo"
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      right={
        <>
          <BarAction hero icon="create-outline" label="Edit memo" onPress={() => router.push(`/cash-memos/${m.id}/edit` as Href)} />
          <BarAction hero icon="share-outline" label="Share PDF" onPress={sharePdf} />
        </>
      }
      headerExtra={
        <View style={{ gap: 6, paddingBottom: 24 }}>
          <AppText color={colors.onDarkMuted} style={{ fontSize: 14 }} numberOfLines={1}>
            {m.seller_name} · {date(m.memo_date)}
          </AppText>
          <AppText variant="hero" color={colors.onDark}>
            {money(m.grand_total)}
            <AppText style={{ fontFamily: fonts.semibold, fontSize: 15 }} color={colors.onDarkMuted}>
              {`  paid by ${MEMO_MODES.find((x) => x.value === m.payment_mode)?.label ?? m.payment_mode}`}
            </AppText>
          </AppText>
        </View>
      }
    >
      <View style={{ flexDirection: 'row', gap: 10 }}>
        <Button label="Share PDF" icon="document-outline" kind="soft" size="md" style={{ flex: 1 }} loading={sharing} onPress={sharePdf} />
        <Button label="Delete" icon="trash-outline" kind="danger" size="md" style={{ flex: 1 }} loading={remove.isPending} onPress={confirmDelete} />
      </View>

      <SectionTitle title={`${m.items.length} item${m.items.length === 1 ? '' : 's'}`} />
      <Card>
        {m.items.map((it) => (
          <View key={it.id} style={{ flexDirection: 'row', gap: 10 }}>
            <View style={{ flex: 1 }}>
              <AppText style={{ fontFamily: fonts.medium, fontSize: 14 }}>{it.description}</AppText>
              <AppText variant="small" color={colors.textMuted}>
                {it.quantity} {it.unit ?? ''} × {money(it.rate, { paise: true })}
                {it.hsn_sac ? ` · HSN ${it.hsn_sac}` : ''}
              </AppText>
            </View>
            <AppText variant="money">{money(it.amount, { paise: true })}</AppText>
          </View>
        ))}
      </Card>

      <Card>
        <KeyValue label="Subtotal" value={money(m.subtotal, { paise: true })} />
        {m.discount ? <KeyValue label="Discount" value={`− ${money(m.discount, { paise: true })}`} /> : null}
        <KeyValue label="Taxable value" value={money(m.taxable_value, { paise: true })} />
        {m.total_igst ? <KeyValue label="IGST" value={money(m.total_igst, { paise: true })} /> : null}
        {m.total_cgst ? <KeyValue label="CGST" value={money(m.total_cgst, { paise: true })} /> : null}
        {m.total_sgst ? <KeyValue label="SGST" value={money(m.total_sgst, { paise: true })} /> : null}
        {!gst ? <KeyValue label="GST" value="None" /> : null}
        {m.round_off ? <KeyValue label="Round off" value={money(m.round_off, { paise: true })} /> : null}
        <View style={{ height: 1, backgroundColor: colors.divider }} />
        <KeyValue label="Total" value={money(m.grand_total)} strong />
      </Card>

      <Card>
        <AppText variant="h3">Seller</AppText>
        <AppText style={{ fontFamily: fonts.medium }}>{m.seller_name}</AppText>
        {[m.seller_address, m.seller_state, m.seller_phone, m.seller_gstin ? `GSTIN ${m.seller_gstin}` : null].filter(Boolean).map((line) => (
          <AppText key={line} variant="small" color={colors.textMuted}>
            {line}
          </AppText>
        ))}
        <View style={{ height: 1, backgroundColor: colors.divider }} />
        <AppText variant="small" color={colors.textMuted}>
          Booked as expense: {category ?? 'Miscellaneous'}
          {m.reference_number ? ` · Ref ${m.reference_number}` : ''}
        </AppText>
        {m.notes ? <AppText variant="small" color={colors.textMuted}>{m.notes}</AppText> : null}
      </Card>
    </Screen>
  );
}
