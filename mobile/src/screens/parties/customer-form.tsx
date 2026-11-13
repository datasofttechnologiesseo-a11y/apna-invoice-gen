import { ReactNode, useState } from 'react';
import { View } from 'react-native';

import { ApiError } from '@/api/client';
import { CustomerInput, GSTIN_PATTERN } from '@/api/parties';
import type { Customer, State } from '@/api/types';
import { AppText } from '@/components/app-text';
import { Card, Overline } from '@/components/blocks';
import { Button } from '@/components/button';
import { PickerRow, PickerSheet } from '@/components/picker-sheet';
import { Screen } from '@/components/screen';
import { SelectField } from '@/components/select-field';
import { TextField } from '@/components/text-field';
import { useStates } from '@/hooks/queries';
import { useSaveCustomer } from '@/hooks/parties';
import { colors } from '@/theme';

/**
 * Add or edit a customer — same fields as the web form. The GSTIN shape is
 * hinted here; checksum and state-code match come back from the server.
 */
export function CustomerForm({ initial, title, bottom, onSaved }: { initial?: Customer; title: string; bottom?: ReactNode; onSaved: (c: Customer) => void }) {
  const save = useSaveCustomer();
  const states = useStates();
  const [f, setF] = useState({
    name: initial?.name ?? '',
    gstin: initial?.gstin ?? '',
    phone: initial?.phone ?? '',
    email: initial?.email ?? '',
    address_line1: initial?.address_line1 ?? '',
    address_line2: initial?.address_line2 ?? '',
    city: initial?.city ?? '',
    postal_code: initial?.postal_code ?? '',
  });
  const [stateId, setStateId] = useState<number | null>(initial?.state_id ?? null);
  const [pickState, setPickState] = useState(false);
  const [stateQ, setStateQ] = useState('');

  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));
  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};
  const all = states.data ?? [];
  const state = all.find((s) => s.id === stateId) ?? null;
  const filtered = all.filter((s) => !stateQ || s.name.toLowerCase().includes(stateQ.toLowerCase()) || s.gst_code === stateQ);

  const gstin = f.gstin.trim().toUpperCase();
  const gstinHint = gstin.length === 15 && !GSTIN_PATTERN.test(gstin) ? 'This does not look like a GSTIN (15 characters, e.g. 27ABCDE1234F1Z5).' : undefined;

  /** The first two GSTIN digits are the state code — fill the state from them. */
  const onGstin = (t: string) => {
    const v = t.replace(/\s/g, '').toUpperCase();
    set('gstin')(v);
    if (v.length >= 2) {
      const match = all.find((s: State) => s.gst_code === v.slice(0, 2));
      if (match) setStateId(match.id);
    }
  };

  const ready = !!f.name.trim() && !!stateId;

  const submit = () => {
    if (!stateId) return;
    const input: CustomerInput = {
      name: f.name.trim(),
      gstin: gstin || null,
      phone: f.phone.trim() || null,
      email: f.email.trim() || null,
      address_line1: f.address_line1.trim() || null,
      address_line2: f.address_line2.trim() || null,
      city: f.city.trim() || null,
      state_id: stateId,
      postal_code: f.postal_code.trim() || null,
      country: initial?.country ?? 'India',
    };
    save.mutate({ id: initial?.id, input }, { onSuccess: onSaved });
  };

  return (
    <Screen
      nav="close"
      title={title}
      subtitle={initial ? undefined : 'Billed on invoices and quotations'}
      footer={<Button label={initial ? 'Save changes' : 'Save customer'} icon="checkmark" loading={save.isPending} disabled={!ready} onPress={submit} />}
    >
      <Card>
        <TextField label="Customer name" value={f.name} onChangeText={set('name')} placeholder="Business or person name" error={errors.name} autoFocus={!initial} />
        <TextField
          label="GSTIN (optional, for B2B)"
          value={f.gstin}
          onChangeText={onGstin}
          autoCapitalize="characters"
          autoCorrect={false}
          maxLength={15}
          placeholder="27ABCDE1234F1Z5"
          error={errors.gstin ?? gstinHint}
          hint="With a GSTIN the invoice is B2B and the state is filled for you."
        />
        <SelectField
          label="State (decides CGST + SGST or IGST)"
          value={state ? `${state.name} (${state.gst_code})` : null}
          placeholder={states.isLoading ? 'Loading states…' : 'Choose state'}
          error={errors.state_id}
          onPress={() => setPickState(true)}
        />
      </Card>

      <Overline>Contact</Overline>
      <Card>
        <TextField label="Phone (for WhatsApp)" value={f.phone} onChangeText={set('phone')} keyboardType="phone-pad" maxLength={30} error={errors.phone} />
        <TextField label="Email (for invoice copies)" value={f.email} onChangeText={set('email')} keyboardType="email-address" autoCapitalize="none" autoCorrect={false} error={errors.email} />
      </Card>

      <Overline>Billing address</Overline>
      <Card>
        <TextField label="Address line 1" value={f.address_line1} onChangeText={set('address_line1')} placeholder="Shop / building, street" error={errors.address_line1} />
        <TextField label="Address line 2" value={f.address_line2} onChangeText={set('address_line2')} placeholder="Area, landmark" error={errors.address_line2} />
        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={{ flex: 1.4 }}>
            <TextField label="City" value={f.city} onChangeText={set('city')} error={errors.city} />
          </View>
          <View style={{ flex: 1 }}>
            <TextField label="PIN code" value={f.postal_code} onChangeText={set('postal_code')} keyboardType="number-pad" maxLength={10} error={errors.postal_code} />
          </View>
        </View>
      </Card>

      {save.error && !Object.keys(errors).length ? <AppText variant="small" color={colors.redText}>{(save.error as Error).message}</AppText> : null}

      {bottom}

      <PickerSheet
        visible={pickState}
        title="State"
        placeholder="Search states"
        items={filtered}
        loading={states.isLoading}
        onSearch={setStateQ}
        onClose={() => setPickState(false)}
        keyOf={(s) => String(s.id)}
        renderItem={(s) => (
          <PickerRow
            title={s.name}
            subtitle={`GST code ${s.gst_code}`}
            onPress={() => {
              setStateId(s.id);
              setPickState(false);
            }}
          />
        )}
      />
    </Screen>
  );
}
