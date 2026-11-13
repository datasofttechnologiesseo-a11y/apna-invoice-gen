import { Href, router, useLocalSearchParams } from 'expo-router';
import { View } from 'react-native';

import { EXPENSE_METHODS } from '@/api/expenses';
import { AppText } from '@/components/app-text';
import { Card, EmptyState, ErrorState, KeyValue, Loading } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { Pill } from '@/components/status-pill';
import { useExpense } from '@/hooks/expenses';
import { ExpenseForm } from '@/screens/expenses/expense-form';
import { colors } from '@/theme';
import { date, money } from '@/utils/format';

/** Edit / delete an expense. Entries written by a cash memo are read-only here (the API refuses edits). */
export default function ExpenseDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useExpense(Number(id));
  const e = q.data;

  if (q.isLoading || (q.isFetching && !e)) return <Screen nav="close" title="Expense"><Loading /></Screen>;
  if (q.error) return <Screen nav="close" title="Expense"><ErrorState message={(q.error as Error).message} onRetry={() => q.refetch()} /></Screen>;
  if (!e) return <Screen nav="close" title="Expense"><EmptyState icon="wallet-outline" title="Expense not found" body="It may have been deleted on the web." /></Screen>;

  if (!e.cash_memo_id && !e.purchase_bill_id) return <ExpenseForm key={e.id} initial={e} />;

  return (
    <Screen nav="close" title={e.vendor_name || e.description} subtitle={`${e.category_label} · ${date(e.entry_date)}`}>
      <Card>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
          <Pill label={e.cash_memo_id ? 'From a cash memo' : 'From a purchase bill'} tone="due" />
        </View>
        <AppText variant="small" color={colors.textMuted}>
          This entry was written by a {e.cash_memo_id ? 'cash memo' : 'purchase bill'}. Edit or delete that and this expense follows.
        </AppText>
        {e.cash_memo_id ? (
          <Button label="Open cash memo" kind="soft" size="md" icon="receipt-outline" onPress={() => router.push(`/cash-memos/${e.cash_memo_id}` as Href)} />
        ) : (
          <Button label="Open purchase bill" kind="soft" size="md" icon="cart-outline" onPress={() => router.push(`/purchases/${e.purchase_bill_id}` as Href)} />
        )}
      </Card>
      <Card>
        <KeyValue label="Description" value={e.description} />
        <KeyValue label="Amount" value={money(e.amount, { paise: true })} />
        <KeyValue label={e.is_interstate ? 'IGST' : 'CGST + SGST'} value={money(e.gst_amount, { paise: true })} />
        <KeyValue label="Paid by" value={EXPENSE_METHODS.find((m) => m.value === e.payment_method)?.label ?? e.payment_method ?? '—'} />
        {e.reference_number ? <KeyValue label="Reference" value={e.reference_number} /> : null}
        <View style={{ height: 1, backgroundColor: colors.divider }} />
        <KeyValue label="Cash out" value={money(e.total)} strong />
      </Card>
      {e.notes ? (
        <Card>
          <AppText variant="small" color={colors.textMuted}>{e.notes}</AppText>
        </Card>
      ) : null}
    </Screen>
  );
}
