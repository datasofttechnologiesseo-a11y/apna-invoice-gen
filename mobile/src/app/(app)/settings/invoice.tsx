import { useState } from 'react';
import { Alert, View } from 'react-native';

import { Company, companyInput, documentsApi, InvoiceTemplate } from '@/api/workspace';
import { AppText } from '@/components/app-text';
import { Card, Chips, Overline, Row } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { Pill } from '@/components/status-pill';
import { TextField } from '@/components/text-field';
import { useInvoiceTemplates, useSaveCompany } from '@/hooks/workspace';
import { fyStart } from '@/screens/finance/periods';
import { CompanyGate, fieldErrors, FormError, NO_SETTINGS_ROLE, Notice, orNull, useCan } from '@/screens/settings/shared';
import { colors, fonts } from '@/theme';
import { downloadAndShare } from '@/utils/share';

export default function InvoiceSettingsRoute() {
  return <CompanyGate title="Invoice settings">{(c) => <InvoiceSettings key={c.id} company={c} />}</CompanyGate>;
}

const PADDING = ['1', '2', '3', '4', '5', '6', '7', '8'].map((v) => ({ value: v, label: v }));

/** Numbering, default terms, declaration and LUT — web settings page rules (CompanySettings). */
function InvoiceSettings({ company: c }: { company: Company }) {
  const allowed = useCan('company_settings');
  const save = useSaveCompany();
  const [f, setF] = useState({
    invoice_prefix: c.invoice_prefix ?? 'INV',
    invoice_number_format: c.invoice_number_format ?? '',
    next: '',
    default_terms: c.default_terms ?? '',
    declaration: c.declaration ?? '',
    lut_number: c.lut_number ?? '',
  });
  const [padding, setPadding] = useState(String(c.invoice_number_padding ?? 4));
  const [warning, setWarning] = useState<string | null>(null);
  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));
  const errors = fieldErrors(save.error);
  const fy = Number(fyStart().slice(0, 4));
  const fyLabel = `${fy}-${String((fy + 1) % 100).padStart(2, '0')}`;

  const submit = () => {
    setWarning(null);
    save.mutate(
      {
        id: c.id,
        input: {
          ...companyInput(c),
          invoice_prefix: f.invoice_prefix.trim(),
          invoice_number_padding: Number(padding),
          invoice_number_format: orNull(f.invoice_number_format),
          default_terms: orNull(f.default_terms),
          declaration: orNull(f.declaration),
          lut_number: orNull(f.lut_number),
          next_invoice_number: f.next.trim() ? Number(f.next.trim()) : null,
        },
      },
      {
        onSuccess: (res) => {
          const w = res.meta?.warnings?.next_invoice_number;
          if (w) {
            // the rest was saved; only the running number was refused (as on the web)
            setWarning(w);
            return;
          }
          set('next')('');
          Alert.alert('Saved', `Invoice settings are updated. Your next invoice will be ${res.data.next_invoice_number}.`);
        },
      },
    );
  };

  return (
    <Screen
      nav="back"
      title="Invoice settings"
      subtitle={`Next invoice: ${c.next_invoice_number}`}
      footer={allowed ? <Button label="Save settings" icon="checkmark" loading={save.isPending} disabled={!f.invoice_prefix.trim()} onPress={submit} /> : undefined}
    >
      {!allowed ? <Notice text={NO_SETTINGS_ROLE} /> : null}

      <Overline>Numbering</Overline>
      <Card>
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
          <AppText variant="small" color={colors.textMuted}>
            Next invoice number
          </AppText>
          <AppText style={{ fontFamily: fonts.bold, fontSize: 16 }}>{c.next_invoice_number}</AppText>
        </View>
        <TextField label="Invoice prefix" value={f.invoice_prefix} onChangeText={set('invoice_prefix')} autoCapitalize="characters" autoCorrect={false} maxLength={10} error={errors.invoice_prefix} />
        <View style={{ gap: 6 }}>
          <AppText variant="label" color={colors.textBody}>
            Number padding (digits)
          </AppText>
          <Chips value={padding} options={PADDING} onChange={setPadding} />
          {errors.invoice_number_padding ? <AppText variant="small" color={colors.redText}>{errors.invoice_number_padding}</AppText> : null}
        </View>
        <TextField
          label="Invoice number format (optional)"
          value={f.invoice_number_format}
          onChangeText={set('invoice_number_format')}
          autoCapitalize="characters"
          autoCorrect={false}
          maxLength={60}
          placeholder="INV/{FY}/{N}"
          error={errors.invoice_number_format}
          hint={`Tokens: {FY} → ${fyLabel} · {FY_SHORT} → ${fyLabel.slice(2)} · {YYYY} → year · {N} → running number. Empty = prefix-number. GST allows 16 characters: letters, digits, - and /.`}
        />
        <TextField
          label="Next running number (digits only)"
          value={f.next}
          onChangeText={(t) => set('next')(t.replace(/\D/g, ''))}
          keyboardType="number-pad"
          maxLength={7}
          placeholder={runningNumber(c.next_invoice_number)}
          error={errors.next_invoice_number ?? warning ?? undefined}
          hint="Moving from another app mid-year? Enter the number your next invoice should get. It can only go forward."
        />
      </Card>

      <Overline>Printed on invoices</Overline>
      <Card>
        <TextField
          label="Default terms and conditions"
          value={f.default_terms}
          onChangeText={set('default_terms')}
          multiline
          style={{ minHeight: 96, textAlignVertical: 'top' }}
          placeholder={'1. Payment due within 30 days.\n2. Late payment attracts 2% monthly interest.'}
          error={errors.default_terms}
        />
        <TextField
          label="Declaration"
          value={f.declaration}
          onChangeText={set('declaration')}
          multiline
          style={{ minHeight: 72, textAlignVertical: 'top' }}
          placeholder="We declare that this invoice shows the actual price of the goods/services described and that all particulars are true and correct."
          error={errors.declaration}
        />
        <TextField
          label="LUT / bond reference number (optional)"
          value={f.lut_number}
          onChangeText={set('lut_number')}
          autoCapitalize="characters"
          autoCorrect={false}
          maxLength={40}
          error={errors.lut_number}
          hint="For exports and SEZ supplies without IGST (Rule 96A). Printed on those invoices."
        />
      </Card>

      {warning ? <Notice tone="warn" text={`Saved, but the running number was not changed: ${warning}`} /> : null}
      <FormError error={save.error} />

      <Templates />
    </Screen>
  );
}

/** Trailing digits of the formatted next number, as a placeholder ("INV-0010" → "10"). */
function runningNumber(formatted: string): string {
  const m = /(\d+)\D*$/.exec(formatted ?? '');
  return m ? String(Number(m[1])) : '';
}

/** The web's "Browse templates" starting points, each with a sample PDF. */
function Templates() {
  const q = useInvoiceTemplates();
  const [busy, setBusy] = useState<string | null>(null);
  const preview = async (t: InvoiceTemplate) => {
    setBusy(t.key);
    await downloadAndShare(documentsApi.templatePreviewPath(t.key), `preview-${t.key}.pdf`);
    setBusy(null);
  };
  if (q.isLoading || q.error || !q.data?.data.length) return null;
  return (
    <>
      <Overline>Invoice templates</Overline>
      <Card style={{ paddingVertical: 2 }}>
        {q.data.data.map((t, i) => (
          <Row
            key={t.key}
            divider={i < q.data.data.length - 1}
            onPress={busy ? undefined : () => preview(t)}
            accessibilityLabel={`Preview ${t.label} template`}
            title={t.label}
            subtitle={t.tagline ?? t.audience ?? undefined}
            trailing={
              <View style={{ alignItems: 'flex-end', gap: 4 }}>
                {t.tag ? <Pill label={t.tag} tone="info" /> : null}
                <AppText variant="small" color={colors.tealDark} style={{ fontFamily: fonts.semibold }}>
                  {busy === t.key ? 'Opening…' : 'Preview'}
                </AppText>
              </View>
            }
          />
        ))}
      </Card>
      <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
        A sample PDF in each template’s style. Starting a new invoice from a template is on the web for now.
      </AppText>
    </>
  );
}
