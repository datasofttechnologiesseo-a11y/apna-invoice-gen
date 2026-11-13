import { router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { Company, companyInput } from '@/api/workspace';
import { AppText } from '@/components/app-text';
import { Card, Overline, Segmented } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { useSaveCompany } from '@/hooks/workspace';
import { CompanyGate, fieldErrors, FormError, NO_SETTINGS_ROLE, Notice, orNull, useCan } from '@/screens/settings/shared';
import { colors } from '@/theme';

export default function BankSettingsRoute() {
  return <CompanyGate title="Bank & UPI">{(c) => <BankSettings key={c.id} company={c} />}</CompanyGate>;
}

type AccountType = 'none' | 'savings' | 'current';

const TYPES: { value: AccountType; label: string }[] = [
  { value: 'current', label: 'Current' },
  { value: 'savings', label: 'Savings' },
  { value: 'none', label: 'Not set' },
];

/** Bank details and UPI ID printed on invoices (the UPI ID drives the pay QR). IFSC / UPI shape is checked by the server. */
function BankSettings({ company: c }: { company: Company }) {
  const allowed = useCan('company_settings');
  const save = useSaveCompany();
  const [f, setF] = useState({
    bank_account_name: c.bank_account_name ?? '',
    bank_name: c.bank_name ?? '',
    bank_branch: c.bank_branch ?? '',
    bank_account_number: c.bank_account_number ?? '',
    bank_ifsc: c.bank_ifsc ?? '',
    upi_id: c.upi_id ?? '',
  });
  const [type, setType] = useState<AccountType>(c.bank_account_type ?? 'none');
  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));
  const errors = fieldErrors(save.error);

  const submit = () =>
    save.mutate(
      {
        id: c.id,
        input: {
          ...companyInput(c),
          bank_account_name: orNull(f.bank_account_name),
          bank_name: orNull(f.bank_name),
          bank_branch: orNull(f.bank_branch),
          bank_account_number: orNull(f.bank_account_number.replace(/\s+/g, '')),
          bank_ifsc: orNull(f.bank_ifsc.toUpperCase()),
          bank_account_type: type === 'none' ? null : type,
          upi_id: orNull(f.upi_id),
        },
      },
      { onSuccess: () => router.back() },
    );

  return (
    <Screen nav="back" title="Bank & UPI" subtitle="Printed on every invoice" footer={allowed ? <Button label="Save bank details" icon="checkmark" loading={save.isPending} onPress={submit} /> : undefined}>
      {!allowed ? <Notice text={NO_SETTINGS_ROLE} /> : null}

      <Overline>Bank account</Overline>
      <Card>
        <TextField label="Payee name (as in bank)" value={f.bank_account_name} onChangeText={set('bank_account_name')} maxLength={120} placeholder={c.name} error={errors.bank_account_name} hint="Leave empty to print your business name." />
        <TextField label="Bank name" value={f.bank_name} onChangeText={set('bank_name')} maxLength={120} placeholder="HDFC Bank" error={errors.bank_name} />
        <TextField label="Branch" value={f.bank_branch} onChangeText={set('bank_branch')} maxLength={120} placeholder="Powai" error={errors.bank_branch} />
        <View style={{ gap: 6 }}>
          <AppText variant="label" color={colors.textBody}>
            Account type
          </AppText>
          <Segmented value={type} options={TYPES} onChange={setType} />
          {errors.bank_account_type ? <AppText variant="small" color={colors.redText}>{errors.bank_account_type}</AppText> : null}
        </View>
        <TextField label="Account number" value={f.bank_account_number} onChangeText={set('bank_account_number')} keyboardType="number-pad" maxLength={30} error={errors.bank_account_number} />
        <TextField label="IFSC" value={f.bank_ifsc} onChangeText={(t) => set('bank_ifsc')(t.toUpperCase())} autoCapitalize="characters" autoCorrect={false} maxLength={11} placeholder="HDFC0001234" error={errors.bank_ifsc} />
      </Card>

      <Overline>UPI</Overline>
      <Card>
        <TextField
          label="UPI ID (optional)"
          value={f.upi_id}
          onChangeText={set('upi_id')}
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          maxLength={60}
          placeholder="yourname@okhdfcbank"
          error={errors.upi_id}
          hint="Invoices show a UPI QR code so customers can pay in one scan."
        />
      </Card>

      <FormError error={save.error} />
    </Screen>
  );
}
