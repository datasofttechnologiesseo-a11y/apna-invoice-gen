import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, TextInput, View } from 'react-native';

import { ApiError } from '@/api/client';
import { documentsApi } from '@/api/workspace';
import { AppText } from '@/components/app-text';
import { Card, Chips, Loading } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { useInvoice, useRecordPayment } from '@/hooks/queries';
import { colors, fonts } from '@/theme';
import { addDays, date, money, today } from '@/utils/format';
import { downloadAndShare } from '@/utils/share';

/** Same methods as config/payment_methods.php on the web. */
const METHODS = [
  { value: 'upi', label: 'UPI' },
  { value: 'cash', label: 'Cash' },
  { value: 'neft', label: 'NEFT' },
  { value: 'imps', label: 'IMPS' },
  { value: 'rtgs', label: 'RTGS' },
  { value: 'cheque', label: 'Cheque' },
  { value: 'card', label: 'Card' },
  { value: 'other', label: 'Other' },
];
const NEEDS_REF = new Set(['upi', 'neft', 'imps', 'rtgs', 'cheque', 'card']);

export default function RecordPayment() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useInvoice(Number(id));
  const save = useRecordPayment(Number(id));
  const [typed, setAmount] = useState<string | null>(null);
  const [method, setMethod] = useState('upi');
  const [on, setOn] = useState(today());
  const [ref, setRef] = useState('');
  const inv = q.data;

  if (!inv) return <Screen nav="close" title="Record payment"><Loading /></Screen>;

  // Defaults to the full balance until the user types something.
  const amount = typed ?? String(inv.effective_balance);

  const value = Number(amount.replace(/,/g, '')) || 0;
  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};
  const quick = [inv.effective_balance, Math.round(inv.effective_balance / 2)].filter((v, i, a) => v > 0 && a.indexOf(v) === i);

  return (
    <Screen
      nav="close"
      title="Record payment"
      subtitle={`${inv.display_number} · ${inv.customer.name}`}
      footer={
        <Button
          label={value >= inv.effective_balance ? `Save ${money(value)} · mark paid` : `Save ${money(value)}`}
          loading={save.isPending}
          disabled={value <= 0}
          onPress={() =>
            save.mutate(
              { amount: value, method, received_at: on, reference_number: ref.trim() || undefined },
              {
                onSuccess: (res) => {
                  router.back();
                  const p = res?.data;
                  if (!p?.id) return;
                  // offer the receipt right away, as the web does after saving a payment
                  Alert.alert('Payment saved', `${money(p.amount)} recorded · receipt ${p.receipt_number}.`, [
                    { text: 'Done', style: 'cancel' },
                    { text: 'Share receipt', onPress: () => downloadAndShare(documentsApi.receiptPath(p.id), `receipt-${p.receipt_number.replace(/\W+/g, '-')}.pdf`) },
                  ]);
                },
              },
            )
          }
        />
      }
    >
      <Card style={{ alignItems: 'center', backgroundColor: colors.tealTint }}>
        <AppText variant="label" color={colors.tealDark}>
          Amount received · {money(inv.effective_balance)} due
        </AppText>
        <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 4 }}>
          <AppText style={{ fontFamily: fonts.displayHeavy, fontSize: 28 }} color={colors.tealDark}>
            ₹
          </AppText>
          <TextAmount value={amount} onChange={setAmount} />
        </View>
        <View style={{ flexDirection: 'row', gap: 8 }}>
          {quick.map((v, i) => (
            <Button key={v} label={i === 0 ? 'Full balance' : money(v)} kind={value === v ? 'primary' : 'outline'} size="sm" onPress={() => setAmount(String(v))} />
          ))}
        </View>
        {errors.amount ? <AppText variant="small" color={colors.redText}>{errors.amount}</AppText> : null}
      </Card>

      <AppText variant="label" color={colors.textBody}>Paid by</AppText>
      <Chips value={method} options={METHODS} onChange={setMethod} />

      <AppText variant="label" color={colors.textBody}>Date received</AppText>
      <View style={{ flexDirection: 'row', gap: 8 }}>
        {[today(), addDays(today(), -1)].map((d, i) => (
          <Pressable key={d} accessibilityRole="button" accessibilityState={{ selected: on === d }} onPress={() => setOn(d)} style={{ paddingHorizontal: 14, height: 40, borderRadius: 12, justifyContent: 'center', backgroundColor: on === d ? colors.navy : colors.surface }}>
            <AppText style={{ fontFamily: fonts.semibold, fontSize: 13 }} color={on === d ? colors.onDark : colors.text}>
              {i === 0 ? 'Today' : 'Yesterday'} · {date(d, true)}
            </AppText>
          </Pressable>
        ))}
      </View>
      {errors.received_at ? <AppText variant="small" color={colors.redText}>{errors.received_at}</AppText> : null}

      {NEEDS_REF.has(method) ? (
        <TextField label={`${METHODS.find((m) => m.value === method)?.label} reference (optional)`} value={ref} onChangeText={setRef} placeholder="Transaction / cheque number" autoCapitalize="characters" error={errors.reference_number} />
      ) : null}
      {save.error && !Object.keys(errors).length ? <AppText variant="small" color={colors.redText}>{(save.error as Error).message}</AppText> : null}
    </Screen>
  );
}

function TextAmount({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  return (
    <TextInput
      accessibilityLabel="Amount received"
      value={value}
      onChangeText={(t) => onChange(t.replace(/[^\d.]/g, ''))}
      keyboardType="decimal-pad"
      selectTextOnFocus
      maxFontSizeMultiplier={1.2}
      style={{ fontFamily: fonts.displayHeavy, fontSize: 42, color: colors.navy, textAlign: 'center', minWidth: 140, paddingVertical: 4 }}
    />
  );
}
