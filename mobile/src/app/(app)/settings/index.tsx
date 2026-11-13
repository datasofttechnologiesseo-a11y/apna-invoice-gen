import { Href, router } from 'expo-router';
import { useState } from 'react';
import { Alert, View } from 'react-native';

import { GSTIN_PATTERN } from '@/api/parties';
import { Company, companyInput } from '@/api/workspace';
import { AppText } from '@/components/app-text';
import { Card, IconTile, Overline, Row } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon, IconName } from '@/components/icon';
import { PickerRow, PickerSheet } from '@/components/picker-sheet';
import { Screen } from '@/components/screen';
import { SelectField } from '@/components/select-field';
import { Pill } from '@/components/status-pill';
import { TextField } from '@/components/text-field';
import { useStates } from '@/hooks/queries';
import { useCompanies, useSaveCompany, useSwitchCompany } from '@/hooks/workspace';
import { CompanyGate, fieldErrors, FormError, NO_SETTINGS_ROLE, Notice, orNull, ToggleRow, useCan } from '@/screens/settings/shared';
import { colors } from '@/theme';
import { date } from '@/utils/format';

export default function CompanySettingsRoute() {
  return <CompanyGate title="Company">{(c) => <CompanyProfile key={c.id} company={c} />}</CompanyGate>;
}

const SECTIONS: { icon: IconName; label: string; sub: string; href: Href }[] = [
  { icon: 'options-outline', label: 'Invoice settings', sub: 'Numbering, terms, LUT, templates', href: '/settings/invoice' as Href },
  { icon: 'card-outline', label: 'Bank & UPI', sub: 'Printed on invoices, UPI pay QR', href: '/settings/bank' as Href },
  { icon: 'image-outline', label: 'Logo & signature', sub: 'On every PDF', href: '/settings/logo' as Href },
  { icon: 'people-outline', label: 'Team', sub: 'Staff, accountant, roles', href: '/settings/team' as Href },
];

/** Business profile — the same fields and checks as the web settings page (CompanySettings). */
function CompanyProfile({ company: c }: { company: Company }) {
  const allowed = useCan('company_settings');
  const save = useSaveCompany();
  const states = useStates();
  const [f, setF] = useState({
    name: c.name ?? '',
    gstin: c.gstin ?? '',
    pan: c.pan ?? '',
    email: c.email ?? '',
    phone: c.phone ?? '',
    website: c.website ?? '',
    address_line1: c.address_line1 ?? '',
    address_line2: c.address_line2 ?? '',
    city: c.city ?? '',
    postal_code: c.postal_code ?? '',
    country: c.country ?? 'India',
  });
  const [composition, setComposition] = useState(c.composition_dealer);
  const [stateId, setStateId] = useState<number | null>(c.state_id);
  const [pickState, setPickState] = useState(false);
  const [stateQ, setStateQ] = useState('');

  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));
  const errors = fieldErrors(save.error);
  const all = states.data ?? [];
  const state = all.find((s) => s.id === stateId) ?? null;
  const filtered = all.filter((s) => !stateQ || s.name.toLowerCase().includes(stateQ.toLowerCase()) || s.gst_code === stateQ);

  const gstin = f.gstin.trim().toUpperCase();
  const gstinHint = gstin.length === 15 && !GSTIN_PATTERN.test(gstin) ? 'This does not look like a GSTIN (15 characters, e.g. 27ABCDE1234F1Z5).' : undefined;

  const onGstin = (t: string) => {
    const v = t.replace(/\s/g, '').toUpperCase();
    set('gstin')(v);
    if (v.length >= 2) {
      const match = all.find((s) => s.gst_code === v.slice(0, 2));
      if (match) setStateId(match.id);
    }
  };

  const submit = () =>
    save.mutate(
      {
        id: c.id,
        input: {
          ...companyInput(c),
          name: f.name.trim(),
          gstin: gstin || null,
          pan: orNull(f.pan.toUpperCase()),
          composition_dealer: composition,
          email: orNull(f.email),
          phone: orNull(f.phone),
          website: orNull(f.website),
          address_line1: orNull(f.address_line1),
          address_line2: orNull(f.address_line2),
          city: orNull(f.city),
          state_id: stateId,
          postal_code: orNull(f.postal_code),
          country: f.country.trim() || 'India',
        },
      },
      { onSuccess: () => Alert.alert('Saved', 'Your business details are updated.') },
    );

  return (
    <Screen
      nav="back"
      title="Company"
      subtitle={c.name}
      footer={allowed ? <Button label="Save details" icon="checkmark" loading={save.isPending} disabled={!f.name.trim() || !stateId} onPress={submit} /> : undefined}
    >
      <BusinessSwitcher activeId={c.id} />

      {!allowed ? <Notice text={NO_SETTINGS_ROLE} /> : null}

      <Card style={{ paddingVertical: 2 }}>
        {SECTIONS.map((s, i) => (
          <Row
            key={s.label}
            divider={i < SECTIONS.length - 1}
            onPress={() => router.push(s.href)}
            leading={<IconTile icon={s.icon} size={34} />}
            title={s.label}
            subtitle={s.sub}
            trailing={<Icon name="chevron-forward" size={18} color={colors.textMuted} />}
          />
        ))}
      </Card>

      <Overline>Business</Overline>
      <Card>
        <TextField label="Business name" value={f.name} onChangeText={set('name')} error={errors.name} />
        <TextField
          label="GSTIN"
          value={f.gstin}
          onChangeText={onGstin}
          autoCapitalize="characters"
          autoCorrect={false}
          maxLength={15}
          placeholder="27AAACT2727Q1ZW"
          error={errors.gstin ?? gstinHint}
          hint="Leave empty if you are not GST-registered."
        />
        <TextField label="PAN" value={f.pan} onChangeText={(t) => set('pan')(t.toUpperCase())} autoCapitalize="characters" autoCorrect={false} maxLength={10} placeholder="AABCU9603R" error={errors.pan} />
        <ToggleRow label="Composition dealer" hint="Issues a Bill of Supply without GST (Section 10)." value={composition} onChange={setComposition} />
        {errors.composition_dealer ? <AppText variant="small" color={colors.redText}>{errors.composition_dealer}</AppText> : null}
      </Card>

      <Overline>Contact</Overline>
      <Card>
        <TextField label="Email" value={f.email} onChangeText={set('email')} keyboardType="email-address" autoCapitalize="none" autoCorrect={false} error={errors.email} />
        <TextField label="Phone" value={f.phone} onChangeText={set('phone')} keyboardType="phone-pad" maxLength={30} placeholder="+91 98200 00000" error={errors.phone} />
        <TextField label="Website" value={f.website} onChangeText={set('website')} keyboardType="url" autoCapitalize="none" autoCorrect={false} placeholder="https://..." error={errors.website} />
      </Card>

      <Overline>Address</Overline>
      <Card>
        <TextField label="Address line 1" value={f.address_line1} onChangeText={set('address_line1')} error={errors.address_line1} />
        <TextField label="Address line 2" value={f.address_line2} onChangeText={set('address_line2')} error={errors.address_line2} />
        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={{ flex: 1.4 }}>
            <TextField label="City" value={f.city} onChangeText={set('city')} error={errors.city} />
          </View>
          <View style={{ flex: 1 }}>
            <TextField label="PIN code" value={f.postal_code} onChangeText={set('postal_code')} keyboardType="number-pad" maxLength={6} placeholder="400076" error={errors.postal_code} />
          </View>
        </View>
        <SelectField
          label="State (decides CGST + SGST or IGST)"
          value={state ? `${state.name} (${state.gst_code})` : null}
          placeholder={states.isLoading ? 'Loading states…' : 'Choose state'}
          error={errors.state_id}
          onPress={() => setPickState(true)}
        />
        <TextField label="Country" value={f.country} onChangeText={set('country')} error={errors.country} />
      </Card>

      {c.books_locked_until ? <Notice text={`Books are locked up to ${date(c.books_locked_until)}. Invoices on or before that date cannot be changed. Change the lock on the website.`} /> : null}

      <FormError error={save.error} />

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

/** Shown only when the user has more than one business (own ones + businesses they joined as a team member). */
function BusinessSwitcher({ activeId }: { activeId: number }) {
  const list = useCompanies();
  const sw = useSwitchCompany();
  const own = list.data?.data ?? [];
  const joined = (list.data?.meta.memberships ?? []).filter((m) => !own.some((o) => o.id === m.company_id));
  const active = list.data?.meta.active_company_id ?? activeId;
  const all = [
    ...own.map((o) => ({ id: o.id, name: o.name, sub: o.gstin ?? o.city ?? 'Your business', role: null as string | null })),
    ...joined.map((m) => ({ id: m.company_id, name: m.name, sub: m.gstin ?? 'Team member', role: m.role_label })),
  ];
  if (all.length < 2) return null;

  const open = (id: number, name: string) =>
    sw.mutate(id, {
      onSuccess: () => router.replace('/'),
      onError: (e) => Alert.alert(`Could not open ${name}`, (e as Error).message),
    });

  return (
    <>
      <Overline>Your businesses</Overline>
      <Card style={{ paddingVertical: 2 }}>
        {all.map((b, i) => {
          const on = b.id === active;
          return (
            <Row
              key={`${b.role ? 'm' : 'o'}${b.id}`}
              divider={i < all.length - 1}
              onPress={on || sw.isPending ? undefined : () => open(b.id, b.name)}
              accessibilityLabel={on ? `${b.name}, open now` : `Switch to ${b.name}`}
              leading={<IconTile icon={b.role ? 'people-outline' : 'business-outline'} size={34} bg={on ? colors.teal : colors.tealTint} fg={on ? colors.onDark : colors.teal} />}
              title={b.name}
              subtitle={b.sub}
              trailing={on ? <Pill label="Open" tone="paid" /> : b.role ? <Pill label={b.role} tone="info" /> : <Icon name="swap-horizontal" size={18} color={colors.textMuted} />}
            />
          );
        })}
      </Card>
    </>
  );
}
