import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, TextInput, View } from 'react-native';

import { ApiError } from '@/api/client';
import { AppText } from '@/components/app-text';
import { Card, Chips, ErrorState, Loading } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { usePayPurchase, usePurchase, usePurchaseOptions } from '@/hooks/purchases';
import { DateField, Note } from '@/screens/purchases/parts';
import { colors, fonts } from '@/theme';
import { money, today } from '@/utils/format';

const NO_REF = new Set(['cash', 'other']);

/** Record money paid to the vendor against this purchase invoice. */
export default function PayPurchase() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = usePurchase(Number(id));
  const options = usePurchaseOptions();
  const save = usePayPurchase(Number(id));
  const [typed, setAmount] = useState<string | null>(null);
  const [method, setMethod] = useState('upi');
  const [on, setOn] = useState(today());
  const [ref, setRef] = useState('');
  const [notes, setNotes] = useState('');
  const b = q.data;

  if (q.isLoading) return <Screen nav="close" title="Record payment"><Loading /></Screen>;
  if (q.error || !b) return <Screen nav="close" title="Record payment"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;

  const amount = typed ?? String(b.balance);
  const value = Number(amount.replace(/,/g, '')) || 0;
  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};
  const quick = [b.balance, Math.round(b.balance / 2)].filter((v, i, a) => v > 0 && a.indexOf(v) === i);
  const methods = options.data?.methods ?? [];

  return (
    <Screen
      nav="close"
      title="Record payment"
      subtitle={`${b.bill_number} · ${b.vendor.name}`}
      footer={
        <Button
          label={value >= b.balance ? `Save ${money(value)} · mark paid` : `Save ${money(value)}`}
          loading={save.isPending}
          disabled={value <= 0 || b.balance <= 0}
          onPress={() =>
            save.mutate(
              { amount: value, method, paid_on: on, reference_number: ref.trim() || undefined, notes: notes.trim() || undefined },
              { onSuccess: () => router.back() },
            )
          }
        />
      }
    >
      <Card style={{ alignItems: 'center', backgroundColor: colors.tealTint }}>
        <AppText variant="label" color={colors.tealDark}>
          Amount paid · {money(b.balance)} to pay
        </AppText>
        <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 4 }}>
          <AppText style={{ fontFamily: fonts.displayHeavy, fontSize: 28 }} color={colors.tealDark}>
            ₹
          </AppText>
          <TextInput
            accessibilityLabel="Amount paid"
            value={amount}
            onChangeText={(t) => setAmount(t.replace(/[^\d.]/g, ''))}
            keyboardType="decimal-pad"
            selectTextOnFocus
            maxFontSizeMultiplier={1.2}
            style={{ fontFamily: fonts.displayHeavy, fontSize: 42, color: colors.navy, textAlign: 'center', minWidth: 140, paddingVertical: 4 }}
          />
        </View>
        <View style={{ flexDirection: 'row', gap: 8 }}>
          {quick.map((v, i) => (
            <Button key={v} label={i === 0 ? 'Full balance' : money(v)} kind={value === v ? 'primary' : 'outline'} size="sm" onPress={() => setAmount(String(v))} />
          ))}
        </View>
        {errors.amount ? <AppText variant="small" color={colors.redText}>{errors.amount}</AppText> : null}
      </Card>

      <AppText variant="label" color={colors.textBody}>Paid by</AppText>
      {methods.length ? <Chips value={method} options={methods} onChange={setMethod} /> : <ActivityIndicator color={colors.teal} />}
      {errors.method ? <AppText variant="small" color={colors.redText}>{errors.method}</AppText> : null}

      <DateField label="Paid on" value={on} onChange={setOn} error={errors.paid_on} />

      {!NO_REF.has(method) ? (
        <TextField label="Reference (optional)" value={ref} onChangeText={setRef} placeholder="UTR / cheque number" autoCapitalize="characters" error={errors.reference_number} />
      ) : null}
      <TextField label="Notes (optional)" value={notes} onChangeText={setNotes} error={errors.notes} />
      {save.error && !Object.keys(errors).length ? <Note tone="danger">{(save.error as Error).message}</Note> : null}
    </Screen>
  );
}
