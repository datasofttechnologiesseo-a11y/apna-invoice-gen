import { Redirect, router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import type { Invoice } from '@/api/types';
import type { InvoiceTransport, IssuedInvoiceEdit } from '@/api/workspace';
import { AppText } from '@/components/app-text';
import { Card, Chips, ErrorState, Loading, Overline } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { useInvoice } from '@/hooks/queries';
import { useEditIssuedInvoice } from '@/hooks/workspace';
import { DateField } from '@/screens/finance/date-field';
import { fieldErrors, FormError, Notice, orNull } from '@/screens/settings/shared';
import { colors } from '@/theme';
import { addDays } from '@/utils/format';

/**
 * Edit an invoice. A draft opens the full invoice form; an issued invoice can
 * only change what the web allows after issue (due date, notes, terms,
 * transport / e-way bill) — the server refuses the rest.
 */
export default function EditInvoice() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useInvoice(Number(id));
  const inv = q.data;
  if (q.isLoading) return <Screen nav="close" title="Edit invoice"><Loading /></Screen>;
  if (q.error || !inv) return <Screen nav="close" title="Edit invoice"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;
  if (inv.status === 'draft') return <Redirect href={`/invoices/new?id=${inv.id}`} />;
  return <IssuedEdit key={inv.id} inv={inv as Invoice & InvoiceTransport} />;
}

const MODES = [
  { value: '', label: 'Not set' },
  { value: 'Road', label: 'Road' },
  { value: 'Rail', label: 'Rail' },
  { value: 'Air', label: 'Air' },
  { value: 'Ship', label: 'Ship' },
];

function IssuedEdit({ inv }: { inv: Invoice & InvoiceTransport }) {
  const save = useEditIssuedInvoice(inv.id);
  const [due, setDue] = useState(inv.due_date ?? inv.invoice_date);
  const [f, setF] = useState({
    notes: inv.notes ?? '',
    terms: inv.terms ?? '',
    transporter_name: inv.transporter_name ?? '',
    transporter_id: inv.transporter_id ?? '',
    vehicle_number: inv.vehicle_number ?? '',
    eway_bill_number: inv.eway_bill_number ?? '',
  });
  const [mode, setMode] = useState<string>(inv.transport_mode ?? '');
  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));
  const errors = fieldErrors(save.error);

  if (!inv.can.soft_edit) {
    return (
      <Screen nav="close" title={`Edit ${inv.display_number}`}>
        <Notice tone="warn" text="This invoice can no longer be changed. To correct an issued invoice, raise a credit note or cancel it." />
      </Screen>
    );
  }

  const submit = () => {
    const input: IssuedInvoiceEdit = {
      due_date: due,
      notes: orNull(f.notes),
      terms: orNull(f.terms),
      transporter_name: orNull(f.transporter_name),
      transporter_id: orNull(f.transporter_id.toUpperCase()),
      vehicle_number: orNull(f.vehicle_number.toUpperCase()),
      transport_mode: (mode || null) as IssuedInvoiceEdit['transport_mode'],
      eway_bill_number: orNull(f.eway_bill_number),
    };
    save.mutate(input, { onSuccess: () => router.back() });
  };

  return (
    <Screen nav="close" title={`Edit ${inv.display_number}`} subtitle={inv.customer.name} footer={<Button label="Save changes" icon="checkmark" loading={save.isPending} onPress={submit} />}>
      <Notice text="Items, amounts and GST cannot change once an invoice is issued. To correct them, raise a credit note or cancel the invoice." />

      <Overline>Payment</Overline>
      <Card>
        <DateField label="Due date" value={due} onChange={setDue} min={inv.invoice_date} error={errors.due_date} />
        <View style={{ flexDirection: 'row', gap: 8, flexWrap: 'wrap' }}>
          {[15, 30, 45].map((d) => (
            <Button key={d} label={`${d} days`} kind="outline" size="sm" onPress={() => setDue(addDays(inv.invoice_date, d))} />
          ))}
        </View>
      </Card>

      <Overline>Printed on the invoice</Overline>
      <Card>
        <TextField label="Notes" value={f.notes} onChangeText={set('notes')} multiline style={{ minHeight: 72, textAlignVertical: 'top' }} error={errors.notes} />
        <TextField label="Terms" value={f.terms} onChangeText={set('terms')} multiline style={{ minHeight: 96, textAlignVertical: 'top' }} error={errors.terms} />
      </Card>

      <Overline>Transport & e-way bill</Overline>
      <Card>
        <View style={{ gap: 6 }}>
          <AppText variant="label" color={colors.textBody}>
            Mode
          </AppText>
          <Chips value={mode} options={MODES} onChange={setMode} />
          {errors.transport_mode ? <AppText variant="small" color={colors.redText}>{errors.transport_mode}</AppText> : null}
        </View>
        <TextField label="Transporter name" value={f.transporter_name} onChangeText={set('transporter_name')} maxLength={120} error={errors.transporter_name} />
        <TextField label="Transporter ID / GSTIN" value={f.transporter_id} onChangeText={set('transporter_id')} autoCapitalize="characters" autoCorrect={false} maxLength={40} error={errors.transporter_id} />
        <TextField label="Vehicle number" value={f.vehicle_number} onChangeText={set('vehicle_number')} autoCapitalize="characters" autoCorrect={false} maxLength={30} placeholder="MH01AB1234" error={errors.vehicle_number} />
        <TextField label="E-way bill number" value={f.eway_bill_number} onChangeText={set('eway_bill_number')} keyboardType="number-pad" maxLength={30} error={errors.eway_bill_number} />
      </Card>

      <FormError error={save.error} />
    </Screen>
  );
}
