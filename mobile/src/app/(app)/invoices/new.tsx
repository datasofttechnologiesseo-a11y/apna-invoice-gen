import { router, useLocalSearchParams } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, View } from 'react-native';

import { ApiError } from '@/api/client';
import type { Customer, Invoice, LineItem } from '@/api/types';
import { AppText } from '@/components/app-text';
import { Card, Chips, KeyValue, Loading } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { LineItemsEditor, lineTaxable } from '@/components/line-items-editor';
import { PartyPicker } from '@/components/party-picker';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { useInvoice, useSaveInvoice } from '@/hooks/queries';
import { colors, fonts } from '@/theme';
import { addDays, date, money, today } from '@/utils/format';

const DUE = [
  { value: '0', label: 'On receipt' },
  { value: '7', label: '7 days' },
  { value: '15', label: '15 days' },
  { value: '30', label: '30 days' },
];

/** New invoice, or edit a draft (?id=). GST split and numbering come from the server. */
export default function InvoiceForm() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const editing = id ? Number(id) : undefined;
  const existing = useInvoice(editing ?? 0);
  if (editing && !existing.data) return <Screen nav="close" title="Edit draft"><Loading /></Screen>;
  return <InvoiceFormBody key={editing ?? 'new'} editing={editing} initial={existing.data} />;
}

function InvoiceFormBody({ editing, initial }: { editing?: number; initial?: Invoice }) {
  const save = useSaveInvoice();
  const [customer, setCustomer] = useState<Customer | null>(initial?.customer ?? null);
  const [items, setItems] = useState<LineItem[]>(() =>
    (initial?.items ?? []).map((it) => ({ product_id: it.product_id, description: it.description, hsn_sac: it.hsn_sac, quantity: Number(it.quantity), unit: it.unit, rate: Number(it.rate), discount: Number(it.discount), gst_rate: Number(it.gst_rate) })),
  );
  const [invoiceDate] = useState(initial?.invoice_date ?? today());
  const [due, setDue] = useState('15');
  const [more, setMore] = useState(false);
  const [extra, setExtra] = useState({ terms: initial?.terms ?? '', notes: initial?.notes ?? '', transporter_name: '', vehicle_number: '', eway_bill_number: '' });

  const preview = useMemo(() => {
    const taxable = items.reduce((s, l) => s + lineTaxable(l), 0);
    const tax = items.reduce((s, l) => s + (lineTaxable(l) * l.gst_rate) / 100, 0);
    return { taxable, tax, total: Math.round(taxable + tax) };
  }, [items]);

  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};
  const itemsError = errors.items ?? Object.entries(errors).find(([k]) => k.startsWith('items.'))?.[1];
  const set = (k: keyof typeof extra) => (v: string) => setExtra((s) => ({ ...s, [k]: v }));

  const submit = (issue: boolean) => {
    if (!customer) return;
    save.mutate(
      {
        id: editing,
        issue,
        input: {
          customer_id: customer.id,
          invoice_date: invoiceDate,
          due_date: addDays(invoiceDate, Number(due)),
          items: items.filter((l) => l.description.trim() && l.quantity > 0),
          terms: extra.terms || null,
          notes: extra.notes || null,
          transporter_name: extra.transporter_name || null,
          vehicle_number: extra.vehicle_number || null,
          eway_bill_number: extra.eway_bill_number || null,
        },
      },
      { onSuccess: (inv) => (issue ? router.replace(`/invoices/${inv.id}/issued`) : router.replace(`/invoices/${inv.id}`)) },
    );
  };

  const ready = !!customer && items.some((l) => l.description.trim() && l.quantity > 0);

  return (
    <Screen
      nav="close"
      title={editing ? 'Edit draft' : 'New invoice'}
      subtitle={`Invoice date ${date(invoiceDate)} · number given on issue`}
      footer={
        <>
          <KeyValue label="Taxable value" value={money(preview.taxable, { paise: true })} />
          <KeyValue label="GST (split shown after saving)" value={money(preview.tax, { paise: true })} />
          <View style={{ height: 1, backgroundColor: colors.divider }} />
          <KeyValue label="Total" value={money(preview.total)} strong />
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <Button label="Save draft" kind="soft" style={{ flex: 1 }} disabled={!ready || save.isPending} onPress={() => submit(false)} />
            <Button label="Save & issue" icon="send" style={{ flex: 2 }} loading={save.isPending} disabled={!ready} onPress={() => submit(true)} />
          </View>
        </>
      }
    >
      <PartyPicker value={customer} onChange={setCustomer} error={errors.customer_id} />

      <AppText variant="label" color={colors.textBody} style={{ marginTop: 4 }}>Payment due</AppText>
      <Chips value={due} options={DUE} onChange={setDue} />

      <LineItemsEditor items={items} onChange={setItems} error={itemsError} />

      <Pressable accessibilityRole="button" accessibilityState={{ expanded: more }} onPress={() => setMore((m) => !m)} style={{ height: 52, borderRadius: 16, borderWidth: 1.5, borderStyle: 'dashed', borderColor: colors.tealLight, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, gap: 8 }}>
        <AppText style={{ flex: 1, fontFamily: fonts.semibold, fontSize: 14 }} color={colors.tealDark}>
          More details: terms, notes, transport, e-way bill
        </AppText>
        <Icon name={more ? 'chevron-up' : 'chevron-down'} color={colors.tealDark} size={18} />
      </Pressable>
      {more ? (
        <Card>
          <TextField label="Terms & conditions" value={extra.terms} onChangeText={set('terms')} multiline placeholder="Payment within 15 days." error={errors.terms} />
          <TextField label="Notes (printed below the terms)" value={extra.notes} onChangeText={set('notes')} multiline error={errors.notes} />
          <TextField label="Transporter name" value={extra.transporter_name} onChangeText={set('transporter_name')} error={errors.transporter_name} />
          <TextField label="Vehicle number" value={extra.vehicle_number} onChangeText={set('vehicle_number')} autoCapitalize="characters" error={errors.vehicle_number} />
          <TextField label="E-way bill number" value={extra.eway_bill_number} onChangeText={set('eway_bill_number')} keyboardType="number-pad" error={errors.eway_bill_number} />
          <AppText variant="small" color={colors.textMuted}>
            Ship-to address, export/SEZ and PDF style are on the web for now.
          </AppText>
        </Card>
      ) : null}

      {save.error && !Object.keys(errors).length ? (
        <AppText variant="small" color={colors.redText}>{(save.error as Error).message}</AppText>
      ) : null}
    </Screen>
  );
}
