import { useState } from 'react';
import { Pressable, View } from 'react-native';

import { ApiError } from '@/api/client';
import { Vendor, VendorInput } from '@/api/purchases';
import type { State } from '@/api/types';
import { AppText } from '@/components/app-text';
import { Card } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { PickerRow, PickerSheet } from '@/components/picker-sheet';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { useSaveVendor } from '@/hooks/purchases';
import { useStates } from '@/hooks/queries';
import { colors, fonts } from '@/theme';

export type VendorDraft = Record<Exclude<keyof VendorInput, 'state_id'>, string> & { state_id: number | null };

export function draftOf(v?: Partial<Vendor> | null, name = ''): VendorDraft {
  return {
    name: v?.name ?? name,
    gstin: v?.gstin ?? '',
    address_line1: v?.address_line1 ?? '',
    address_line2: v?.address_line2 ?? '',
    city: v?.city ?? '',
    state_id: v?.state_id ?? null,
    postal_code: v?.postal_code ?? '',
    country: v?.country ?? 'India',
    phone: v?.phone ?? '',
    email: v?.email ?? '',
    notes: v?.notes ?? '',
  };
}

export function inputOf(d: VendorDraft): VendorInput {
  const s = (v: string) => v.trim() || null;
  return {
    name: d.name.trim(),
    gstin: d.gstin.trim() ? d.gstin.trim().toUpperCase() : null,
    address_line1: s(d.address_line1),
    address_line2: s(d.address_line2),
    city: s(d.city),
    state_id: d.state_id,
    postal_code: s(d.postal_code),
    country: s(d.country) ?? 'India',
    phone: s(d.phone),
    email: s(d.email),
    notes: s(d.notes),
  };
}

/** The vendor fields (same as the web vendor form). `full` adds address and notes. */
export function VendorFields({ value, onChange, errors, full = true }: { value: VendorDraft; onChange: (d: VendorDraft) => void; errors: Record<string, string>; full?: boolean }) {
  const states = useStates();
  const [pick, setPick] = useState(false);
  const [q, setQ] = useState('');
  const set = (k: keyof VendorDraft) => (v: string) => onChange({ ...value, [k]: v });
  const state = states.data?.find((s) => s.id === value.state_id) ?? null;
  const filtered = (states.data ?? []).filter((s: State) => s.name.toLowerCase().includes(q.toLowerCase()) || s.gst_code === q);

  return (
    <>
      <TextField label="Vendor name" value={value.name} onChangeText={set('name')} error={errors.name} autoFocus={!value.name} />
      <TextField
        label="GSTIN (optional)"
        value={value.gstin}
        onChangeText={(t) => set('gstin')(t.toUpperCase())}
        autoCapitalize="characters"
        maxLength={15}
        error={errors.gstin}
        hint="Fills the state when left blank. Needed to match GSTR-2B."
      />
      <View style={{ gap: 6 }}>
        <AppText variant="label" color={colors.textBody}>
          State (decides CGST+SGST or IGST)
        </AppText>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={state ? `State ${state.name}. Change` : 'Choose state'}
          onPress={() => setPick(true)}
          style={{ minHeight: 52, borderRadius: 14, borderWidth: 1.5, borderColor: errors.state_id ? colors.red : colors.borderStrong, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, backgroundColor: colors.surface }}
        >
          <AppText style={{ flex: 1, fontFamily: fonts.medium, fontSize: 16 }} color={state ? colors.text : '#8A94A6'}>
            {state ? state.name : 'Choose state'}
          </AppText>
          <Icon name="chevron-down" color={colors.textMuted} />
        </Pressable>
        {errors.state_id ? <AppText variant="small" color={colors.redText}>{errors.state_id}</AppText> : null}
      </View>
      <TextField label="Phone" value={value.phone} onChangeText={set('phone')} keyboardType="phone-pad" error={errors.phone} />
      <TextField label="Email" value={value.email} onChangeText={set('email')} keyboardType="email-address" autoCapitalize="none" error={errors.email} />
      {full ? (
        <>
          <TextField label="Address line 1" value={value.address_line1} onChangeText={set('address_line1')} error={errors.address_line1} />
          <TextField label="Address line 2" value={value.address_line2} onChangeText={set('address_line2')} error={errors.address_line2} />
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <View style={{ flex: 1 }}>
              <TextField label="City" value={value.city} onChangeText={set('city')} error={errors.city} />
            </View>
            <View style={{ flex: 1 }}>
              <TextField label="PIN code" value={value.postal_code} onChangeText={set('postal_code')} keyboardType="number-pad" maxLength={10} error={errors.postal_code} />
            </View>
          </View>
          <TextField label="Notes" value={value.notes} onChangeText={set('notes')} multiline error={errors.notes} />
        </>
      ) : null}
      <PickerSheet
        visible={pick}
        title="State"
        placeholder="Search states"
        items={filtered}
        loading={states.isLoading}
        onSearch={setQ}
        onClose={() => setPick(false)}
        keyOf={(s) => String(s.id)}
        renderItem={(s) => (
          <PickerRow
            title={s.name}
            subtitle={`GST code ${s.gst_code}`}
            onPress={() => {
              onChange({ ...value, state_id: s.id });
              setPick(false);
            }}
          />
        )}
      />
    </>
  );
}

/** Full-screen vendor form for vendors/new and vendors/[id]/edit. */
export function VendorFormScreen({ vendor, onSaved }: { vendor?: Vendor; onSaved: (v: Vendor) => void }) {
  const save = useSaveVendor();
  const [draft, setDraft] = useState<VendorDraft>(() => draftOf(vendor));
  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};

  return (
    <Screen
      nav="close"
      title={vendor ? 'Edit vendor' : 'New vendor'}
      subtitle={vendor?.name ?? 'Supplier you buy from'}
      footer={
        <Button
          label={vendor ? 'Save changes' : 'Save vendor'}
          loading={save.isPending}
          disabled={!draft.name.trim()}
          onPress={() => save.mutate({ id: vendor?.id, input: inputOf(draft) }, { onSuccess: onSaved })}
        />
      }
    >
      <Card>
        <VendorFields value={draft} onChange={setDraft} errors={errors} />
      </Card>
      {save.error && !Object.keys(errors).length ? (
        <AppText variant="small" color={colors.redText}>
          {(save.error as Error).message}
        </AppText>
      ) : null}
    </Screen>
  );
}
