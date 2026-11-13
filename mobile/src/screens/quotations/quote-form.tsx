import { useMemo, useState } from 'react';
import { Pressable, View } from 'react-native';

import { ApiError } from '@/api/client';
import type { Customer, LineItem, Quotation } from '@/api/types';
import { AppText } from '@/components/app-text';
import { Card, Chips, KeyValue } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { LineItemsEditor, lineTaxable } from '@/components/line-items-editor';
import { PartyPicker } from '@/components/party-picker';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { useAuth } from '@/auth/auth-context';
import { useSaveQuotation } from '@/hooks/quotes';
import { colors, fonts } from '@/theme';
import { addDays, date, money, today } from '@/utils/format';

const VALIDITY = ['7', '15', '30', '60'];

/** Whole days from one ISO date to another. */
const daysBetween = (from: string, to: string) => {
  const u = (iso: string) => {
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    return Date.UTC(y, m - 1, d);
  };
  return Math.round((u(to) - u(from)) / 86400000);
};

/**
 * New quotation, or edit a draft. Same pieces as the invoice form (PartyPicker +
 * LineItemsEditor); totals and the GST split come from the server on save.
 */
export function QuoteForm({ initial, onSaved }: { initial?: Quotation; onSaved: (q: Quotation) => void }) {
  const { company } = useAuth();
  const save = useSaveQuotation();
  const [customer, setCustomer] = useState<Customer | null>(initial?.customer ?? null);
  const [items, setItems] = useState<LineItem[]>(() =>
    (initial?.items ?? []).map((it) => ({ product_id: it.product_id, description: it.description, hsn_sac: it.hsn_sac, quantity: Number(it.quantity), unit: it.unit, rate: Number(it.rate), discount: Number(it.discount), gst_rate: Number(it.gst_rate) })),
  );
  const [quoteDate] = useState(initial?.quote_date ?? today());
  const [valid, setValid] = useState(() => {
    if (!initial) return '15';
    return initial.valid_until ? String(daysBetween(initial.quote_date, initial.valid_until)) : 'none';
  });
  const [more, setMore] = useState(!!(initial?.subject || initial?.reference || initial?.delivery_period || initial?.terms || initial?.notes));
  const [extra, setExtra] = useState({
    subject: initial?.subject ?? '',
    reference: initial?.reference ?? '',
    delivery_period: initial?.delivery_period ?? '',
    terms: initial?.terms ?? '',
    notes: initial?.notes ?? '',
  });

  const validOptions = useMemo(() => {
    const days = VALIDITY.includes(valid) || valid === 'none' ? VALIDITY : [...VALIDITY, valid].sort((a, b) => Number(a) - Number(b));
    return [
      ...days.map((d) => ({ value: d, label: VALIDITY.includes(d) ? `${d} days` : `Till ${date(addDays(quoteDate, Number(d)), true)}` })),
      { value: 'none', label: 'No expiry' },
    ];
  }, [valid, quoteDate]);

  const preview = useMemo(() => {
    const taxable = items.reduce((s, l) => s + lineTaxable(l), 0);
    // Composition dealers quote without GST (the server applies the same rule).
    const tax = company?.composition_dealer ? 0 : items.reduce((s, l) => s + (lineTaxable(l) * l.gst_rate) / 100, 0);
    return { taxable, tax, total: Math.round(taxable + tax) };
  }, [items, company?.composition_dealer]);

  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};
  const itemsError = errors.items ?? Object.entries(errors).find(([k]) => k.startsWith('items.'))?.[1];
  const set = (k: keyof typeof extra) => (v: string) => setExtra((s) => ({ ...s, [k]: v }));
  const validUntil = valid === 'none' ? null : addDays(quoteDate, Number(valid));

  const submit = (send: boolean) => {
    if (!customer) return;
    save.mutate(
      {
        id: initial?.id,
        send,
        input: {
          customer_id: customer.id,
          quote_date: quoteDate,
          valid_until: validUntil,
          items: items.filter((l) => l.description.trim() && l.quantity > 0),
          subject: extra.subject.trim() || null,
          reference: extra.reference.trim() || null,
          delivery_period: extra.delivery_period.trim() || null,
          terms: extra.terms.trim() || null,
          notes: extra.notes.trim() || null,
        },
      },
      { onSuccess: onSaved },
    );
  };

  const ready = !!customer && items.some((l) => l.description.trim() && l.quantity > 0);

  return (
    <Screen
      nav="close"
      title={initial ? `Edit ${initial.display_number}` : 'New quotation'}
      subtitle={`Quote date ${date(quoteDate)} · number given when sent`}
      footer={
        <>
          <KeyValue label="Taxable value" value={money(preview.taxable, { paise: true })} />
          <KeyValue label="GST (split shown after saving)" value={money(preview.tax, { paise: true })} />
          <View style={{ height: 1, backgroundColor: colors.divider }} />
          <KeyValue label="Total" value={money(preview.total)} strong />
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <Button label="Save draft" kind="soft" style={{ flex: 1 }} disabled={!ready || save.isPending} onPress={() => submit(false)} />
            <Button label="Save & mark sent" icon="paper-plane-outline" style={{ flex: 2 }} loading={save.isPending} disabled={!ready} onPress={() => submit(true)} />
          </View>
        </>
      }
    >
      <PartyPicker value={customer} onChange={setCustomer} error={errors.customer_id} />

      <AppText variant="label" color={colors.textBody} style={{ marginTop: 4 }}>
        Valid {validUntil ? `till ${date(validUntil)}` : 'with no expiry'}
      </AppText>
      <Chips value={valid} options={validOptions} onChange={setValid} />
      {errors.valid_until ? <AppText variant="small" color={colors.redText}>{errors.valid_until}</AppText> : null}

      <LineItemsEditor items={items} onChange={setItems} error={itemsError} />

      <Pressable
        accessibilityRole="button"
        accessibilityState={{ expanded: more }}
        onPress={() => setMore((m) => !m)}
        style={{ height: 52, borderRadius: 16, borderWidth: 1.5, borderStyle: 'dashed', borderColor: colors.tealLight, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, gap: 8 }}
      >
        <AppText style={{ flex: 1, fontFamily: fonts.semibold, fontSize: 14 }} color={colors.tealDark}>
          More details: subject, reference, delivery, terms, notes
        </AppText>
        <Icon name={more ? 'chevron-up' : 'chevron-down'} color={colors.tealDark} size={18} />
      </Pressable>
      {more ? (
        <Card>
          <TextField label="Subject" value={extra.subject} onChangeText={set('subject')} maxLength={200} placeholder="Supply of ceiling fans" error={errors.subject} />
          <TextField label="Reference (their enquiry no.)" value={extra.reference} onChangeText={set('reference')} maxLength={100} error={errors.reference} />
          <TextField label="Delivery period" value={extra.delivery_period} onChangeText={set('delivery_period')} maxLength={100} placeholder="Within 7 days of order" error={errors.delivery_period} />
          <TextField label="Terms & conditions" value={extra.terms} onChangeText={set('terms')} multiline maxLength={2000} placeholder="50% advance, balance on delivery." error={errors.terms} />
          <TextField label="Notes" value={extra.notes} onChangeText={set('notes')} multiline maxLength={1000} error={errors.notes} />
        </Card>
      ) : null}

      {save.error && !Object.keys(errors).length ? (
        <AppText variant="small" color={colors.redText}>{(save.error as Error).message}</AppText>
      ) : null}
    </Screen>
  );
}
