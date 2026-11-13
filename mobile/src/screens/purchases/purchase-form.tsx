import { router, type Href } from 'expo-router';
import { useMemo, useState } from 'react';
import { Alert, Pressable, View } from 'react-native';

import { ApiError } from '@/api/client';
import type { PurchaseBill, PurchaseInput, Vendor } from '@/api/purchases';
import type { LineItem } from '@/api/types';
import { AppText } from '@/components/app-text';
import { Card, Chips, KeyValue } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { LineItemsEditor, lineTaxable } from '@/components/line-items-editor';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { usePurchaseOptions, useSavePurchase } from '@/hooks/purchases';
import { colors, fonts } from '@/theme';
import { addDays, money, today } from '@/utils/format';

import { DateField, Note, ToggleRow } from './parts';
import { VendorPicker } from './vendor-picker';

const num = (s: string) => Number(s.replace(/,/g, '')) || 0;

/** New purchase invoice, or edit one (`bill`). Tax split, stock and ITC rules come from the server. */
export function PurchaseForm({ bill, initialVendor }: { bill?: PurchaseBill; initialVendor?: Vendor | null }) {
  const save = useSavePurchase();
  const options = usePurchaseOptions();
  const editing = !!bill;

  const [vendor, setVendor] = useState<Vendor | null>(bill?.vendor ?? initialVendor ?? null);
  const [billNumber, setBillNumber] = useState(bill?.bill_number ?? '');
  const [billDate, setBillDate] = useState(bill?.bill_date ?? today());
  const [dueDate, setDueDate] = useState(bill?.due_date ?? '');
  // Line discounts are folded into one bill discount, as on the web form.
  const [items, setItems] = useState<LineItem[]>(() =>
    (bill?.items ?? []).map((it) => ({ product_id: it.product_id, description: it.description, hsn_sac: it.hsn_sac, quantity: Number(it.quantity), unit: it.unit, rate: Number(it.rate), discount: 0, gst_rate: Number(it.gst_rate) })),
  );
  const [discount, setDiscount] = useState(() => {
    const d = (bill?.items ?? []).reduce((s, it) => s + Number(it.discount || 0), 0);
    return d ? String(Math.round(d * 100) / 100) : '';
  });
  const [manualTax, setManualTax] = useState(editing);
  const [interstate, setInterstate] = useState(bill?.is_interstate ?? false);
  const [rcm, setRcm] = useState(bill?.reverse_charge ?? false);
  const [itc, setItc] = useState(bill?.itc_eligible ?? true);
  const [category, setCategory] = useState(bill?.expense_category ?? 'purchases');
  const [reference, setReference] = useState(bill?.reference_number ?? '');
  const [notes, setNotes] = useState(bill?.notes ?? '');
  const [paidAlready, setPaidAlready] = useState(false);
  const [paidNow, setPaidNow] = useState<string | null>(null);
  const [paidOn, setPaidOn] = useState(today());
  const [method, setMethod] = useState('upi');
  const [more, setMore] = useState(editing && !!(bill?.notes || bill?.reference_number));

  const preview = useMemo(() => {
    const gross = items.reduce((s, l) => s + lineTaxable(l), 0);
    const disc = Math.min(num(discount), gross);
    const share = gross > 0 ? (gross - disc) / gross : 0;
    const taxable = gross - disc;
    const tax = rcm ? 0 : items.reduce((s, l) => s + (lineTaxable(l) * share * l.gst_rate) / 100, 0);
    return { taxable, tax, total: Math.round(taxable + tax) };
  }, [items, discount, rcm]);

  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};
  const itemsError = errors.items ?? Object.entries(errors).find(([k]) => k.startsWith('items.'))?.[1];
  const ready = !!vendor && !!billNumber.trim() && items.some((l) => l.description.trim() && l.quantity > 0);
  const paidValue = paidNow === null ? preview.total : num(paidNow);

  const submit = () => {
    if (!vendor) return;
    const input: PurchaseInput = {
      vendor_id: vendor.id,
      bill_number: billNumber.trim(),
      bill_date: billDate,
      due_date: dueDate.trim() || null,
      ...(manualTax ? { is_interstate: interstate } : {}),
      reverse_charge: rcm,
      itc_eligible: itc,
      expense_category: category || null,
      reference_number: reference.trim() || null,
      notes: notes.trim() || null,
      bill_discount: num(discount),
      items: items
        .filter((l) => l.description.trim() && l.quantity > 0)
        .map((l) => ({ product_id: l.product_id, description: l.description.trim(), hsn_sac: l.hsn_sac, quantity: l.quantity, unit: l.unit, rate: l.rate, discount: 0, gst_rate: l.gst_rate })),
      ...(!editing && paidAlready && paidValue > 0 ? { paid_now: paidValue, paid_on: paidOn, payment_method: method } : {}),
    };
    save.mutate(
      { id: bill?.id, input },
      {
        onSuccess: (r) => {
          const saved = r.data;
          const go = () => (editing ? router.back() : router.replace(`/purchases/${saved.id}` as Href));
          // Late booking (locked period) or time-barred ITC: the server explains it.
          if (r.message && (saved.booked_on || saved.credit_notes?.time_bar)) {
            Alert.alert('Purchase invoice saved', r.message, [{ text: 'OK', onPress: go }]);
          } else {
            go();
          }
        },
      },
    );
  };

  return (
    <Screen
      nav="close"
      title={editing ? 'Edit purchase invoice' : 'New purchase invoice'}
      subtitle={editing ? `${bill.bill_number} · ${bill.vendor.name}` : 'A bill your supplier gave you'}
      footer={
        <>
          <KeyValue label="Taxable value" value={money(preview.taxable, { paise: true })} />
          <KeyValue label={rcm ? 'GST (reverse charge, paid by you)' : 'GST (split shown after saving)'} value={money(preview.tax, { paise: true })} />
          <View style={{ height: 1, backgroundColor: colors.divider }} />
          <KeyValue label="Payable to vendor" value={money(preview.total)} strong />
          <Button label={editing ? 'Save changes' : 'Save purchase invoice'} loading={save.isPending} disabled={!ready} onPress={submit} />
        </>
      }
    >
      <VendorPicker value={vendor} onChange={setVendor} error={errors.vendor_id} />

      <Card>
        <TextField label="Vendor's invoice number" value={billNumber} onChangeText={setBillNumber} autoCapitalize="characters" placeholder="As printed on their bill" error={errors.bill_number} />
        <DateField label="Invoice date" value={billDate} onChange={setBillDate} error={errors.bill_date} />
        <DateField
          label="Due date"
          optional
          quick={false}
          value={dueDate}
          onChange={setDueDate}
          error={errors.due_date}
          extra={/^\d{4}-\d{2}-\d{2}$/.test(billDate) ? [{ label: 'On receipt', value: billDate }, { label: '15 days', value: addDays(billDate, 15) }, { label: '30 days', value: addDays(billDate, 30) }] : []}
        />
      </Card>

      <LineItemsEditor items={items} onChange={setItems} error={itemsError} />
      <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
        Pick a saved item on each line so its stock and purchase price update.
      </AppText>

      <TextField
        label="Discount on the whole bill (₹)"
        value={discount}
        onChangeText={(t) => setDiscount(t.replace(/[^\d.]/g, ''))}
        keyboardType="decimal-pad"
        placeholder="0"
        error={errors.bill_discount}
      />

      <Card style={{ gap: 4 }}>
        <AppText variant="h3">Tax treatment</AppText>
        <ToggleRow title="Input tax credit claimable" body="Turn off for blocked credit (s.17(5): vehicles, staff food, personal use). The GST is then added to cost." value={itc} onChange={setItc} />
        <ToggleRow title="Reverse charge (GST payable by you)" body="The vendor charges no GST; you pay it in GSTR-3B 3.1(d)." value={rcm} onChange={setRcm} />
        <ToggleRow title="Set tax type manually" body={manualTax ? undefined : "Normally set by the vendor's state."} value={manualTax} onChange={setManualTax} />
        {manualTax ? <ToggleRow title="Inter-state (IGST)" body={interstate ? 'IGST' : 'CGST + SGST'} value={interstate} onChange={setInterstate} /> : null}
      </Card>

      {options.data?.categories.length ? (
        <>
          <AppText variant="label" color={colors.textBody}>Expense category</AppText>
          <Chips value={category} options={options.data.categories} onChange={setCategory} />
          {errors.expense_category ? <AppText variant="small" color={colors.redText}>{errors.expense_category}</AppText> : null}
        </>
      ) : null}

      {!editing ? (
        <Card style={{ gap: 10 }}>
          <ToggleRow title="Paid already?" body="Record a payment made when you got the bill." value={paidAlready} onChange={setPaidAlready} />
          {paidAlready ? (
            <>
              <TextField
                label="Amount paid (₹)"
                value={paidNow ?? String(preview.total)}
                onChangeText={(t) => setPaidNow(t.replace(/[^\d.]/g, ''))}
                keyboardType="decimal-pad"
                error={errors.paid_now}
                hint={paidValue >= preview.total ? 'Paid in full' : `${money(preview.total - paidValue)} left to pay`}
              />
              {options.data?.methods.length ? <Chips value={method} options={options.data.methods} onChange={setMethod} /> : null}
              {errors.payment_method ? <AppText variant="small" color={colors.redText}>{errors.payment_method}</AppText> : null}
              <DateField label="Paid on" value={paidOn} onChange={setPaidOn} error={errors.paid_on} />
            </>
          ) : null}
        </Card>
      ) : (
        <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
          Manage recorded payments on the purchase invoice page.
        </AppText>
      )}

      <Pressable
        accessibilityRole="button"
        accessibilityState={{ expanded: more }}
        onPress={() => setMore((m) => !m)}
        style={{ height: 52, borderRadius: 16, borderWidth: 1.5, borderStyle: 'dashed', borderColor: colors.tealLight, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, gap: 8 }}
      >
        <AppText style={{ flex: 1, fontFamily: fonts.semibold, fontSize: 14 }} color={colors.tealDark}>
          More details: reference, notes
        </AppText>
        <Icon name={more ? 'chevron-up' : 'chevron-down'} color={colors.tealDark} size={18} />
      </Pressable>
      {more ? (
        <Card>
          <TextField label="Reference (PO / challan / UTR)" value={reference} onChangeText={setReference} error={errors.reference_number} />
          <TextField label="Notes" value={notes} onChangeText={setNotes} multiline error={errors.notes} />
        </Card>
      ) : null}

      {/* Long form: the summary also shows here, near the Save button. */}
      {save.error ? <Note tone="danger">{(save.error as Error).message}</Note> : null}
    </Screen>
  );
}
