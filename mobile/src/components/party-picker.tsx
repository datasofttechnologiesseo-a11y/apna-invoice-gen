import { useState } from 'react';
import { Modal, Pressable, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-controller';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ApiError } from '@/api/client';
import type { Customer, State } from '@/api/types';
import { useCreateCustomer, useCustomers, useStates } from '@/hooks/queries';
import { colors, fonts } from '@/theme';

import { AppText } from './app-text';
import { Avatar, Card } from './blocks';
import { Button } from './button';
import { Icon } from './icon';
import { PickerRow, PickerSheet } from './picker-sheet';
import { TextField } from './text-field';

/** Select a customer, or add one inline (same fields as the web "New customer"). */
export function PartyPicker({ value, onChange, error }: { value: Customer | null; onChange: (c: Customer) => void; error?: string }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const [adding, setAdding] = useState(false);
  const list = useCustomers(q);
  const items = list.data?.pages.flatMap((p) => p.data) ?? [];

  return (
    <>
      <Pressable accessibilityRole="button" accessibilityLabel={value ? `Customer ${value.name}. Change` : 'Choose customer'} onPress={() => setOpen(true)}>
        <Card style={{ flexDirection: 'row', alignItems: 'center', gap: 12, borderWidth: error ? 1.5 : 0, borderColor: colors.red }}>
          {value ? (
            <>
              <Avatar name={value.name} tone="dark" />
              <View style={{ flex: 1, gap: 2 }}>
                <AppText variant="small" color={colors.textMuted}>Bill to</AppText>
                <AppText style={{ fontFamily: fonts.bold, fontSize: 15 }}>{value.name}</AppText>
                <AppText variant="small" color={colors.textMuted}>
                  {[value.gstin, value.state?.name].filter(Boolean).join(' · ')}
                </AppText>
              </View>
              <AppText variant="label" color={colors.tealDark}>Change</AppText>
            </>
          ) : (
            <>
              <View style={{ width: 44, height: 44, borderRadius: 14, backgroundColor: colors.tealTint, alignItems: 'center', justifyContent: 'center' }}>
                <Icon name="person-add-outline" color={colors.teal} />
              </View>
              <AppText style={{ flex: 1, fontFamily: fonts.semibold, fontSize: 15 }}>Choose customer</AppText>
              <Icon name="chevron-forward" color={colors.textMuted} />
            </>
          )}
        </Card>
      </Pressable>
      {error ? <AppText variant="small" color={colors.redText}>{error}</AppText> : null}

      <PickerSheet
        visible={open && !adding}
        title="Bill to"
        placeholder="Search customers"
        items={items}
        loading={list.isFetching}
        onSearch={setQ}
        onClose={() => setOpen(false)}
        keyOf={(c) => String(c.id)}
        header={
          <View style={{ paddingHorizontal: 16, paddingBottom: 4 }}>
            <Button label="New customer" icon="person-add-outline" kind="soft" size="md" onPress={() => setAdding(true)} />
          </View>
        }
        renderItem={(c) => (
          <PickerRow
            title={c.name}
            subtitle={[c.state?.name, c.phone, c.gstin].filter(Boolean).join(' · ')}
            onPress={() => {
              onChange(c);
              setOpen(false);
            }}
          />
        )}
        empty={<AppText color={colors.textMuted} align="center">No customers match.</AppText>}
      />
      <NewCustomerSheet
        visible={open && adding}
        initialName={q}
        onClose={() => setAdding(false)}
        onCreated={(c) => {
          onChange(c);
          setAdding(false);
          setOpen(false);
        }}
      />
    </>
  );
}

function NewCustomerSheet({ visible, initialName, onClose, onCreated }: { visible: boolean; initialName: string; onClose: () => void; onCreated: (c: Customer) => void }) {
  const create = useCreateCustomer();
  const states = useStates();
  const insets = useSafeAreaInsets();
  const [f, setF] = useState({ name: '', gstin: '', phone: '', email: '' });
  const [state, setState] = useState<State | null>(null);
  const [stateQ, setStateQ] = useState('');
  const [pickState, setPickState] = useState(false);
  const errors = create.error instanceof ApiError ? create.error.fieldErrors : {};
  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));
  const name = f.name || initialName;

  const filteredStates = (states.data ?? []).filter((s) => s.name.toLowerCase().includes(stateQ.toLowerCase()) || s.gst_code === stateQ);

  return (
    <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.surface }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 16, paddingTop: 20 }}>
          <AppText variant="h2" style={{ flex: 1 }} accessibilityRole="header">
            New customer
          </AppText>
          <Button kind="outline" size="md" icon="close" accessibilityLabel="Close" onPress={onClose} />
        </View>
        <KeyboardAwareScrollView bottomOffset={24} keyboardShouldPersistTaps="handled" contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}>
          <View style={{ paddingHorizontal: 16, gap: 12 }}>
            <TextField label="Customer name" value={name} onChangeText={set('name')} error={errors.name} autoFocus />
            <AppText variant="label" color={colors.textBody}>State (decides CGST+SGST or IGST)</AppText>
            <Pressable accessibilityRole="button" onPress={() => setPickState(true)} style={{ height: 52, borderRadius: 14, borderWidth: 1.5, borderColor: errors.state_id ? colors.red : colors.borderStrong, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14 }}>
              <AppText style={{ flex: 1, fontFamily: fonts.medium, fontSize: 16 }} color={state ? colors.text : '#8A94A6'}>
                {state ? state.name : 'Choose state'}
              </AppText>
              <Icon name="chevron-down" color={colors.textMuted} />
            </Pressable>
            {errors.state_id ? <AppText variant="small" color={colors.redText}>{errors.state_id}</AppText> : null}
            <TextField label="GSTIN (optional, B2B)" value={f.gstin} onChangeText={set('gstin')} autoCapitalize="characters" maxLength={15} error={errors.gstin} />
            <TextField label="Phone (for WhatsApp)" value={f.phone} onChangeText={set('phone')} keyboardType="phone-pad" error={errors.phone} />
            <TextField label="Email (for invoice copies)" value={f.email} onChangeText={set('email')} keyboardType="email-address" autoCapitalize="none" error={errors.email} />
            <Button
              label="Save customer"
              loading={create.isPending}
              disabled={!name.trim() || !state}
              onPress={() => {
                const stateId = state?.id;
                if (!stateId) return;
                create.mutate(
                  { name: name.trim(), state_id: stateId, gstin: f.gstin.trim() || null, phone: f.phone.trim() || null, email: f.email.trim() || null, country: 'India' },
                  { onSuccess: (r) => onCreated(r.data) },
                );
              }}
            />
            {create.error && !Object.keys(errors).length ? <AppText variant="small" color={colors.redText}>{(create.error as Error).message}</AppText> : null}
          </View>
        </KeyboardAwareScrollView>
        <PickerSheet
          visible={pickState}
          title="State"
          placeholder="Search states"
          items={filteredStates}
          loading={states.isLoading}
          onSearch={setStateQ}
          onClose={() => setPickState(false)}
          keyOf={(s) => String(s.id)}
          renderItem={(s) => (
            <PickerRow
              title={s.name}
              subtitle={`GST code ${s.gst_code}`}
              onPress={() => {
                setState(s);
                setPickState(false);
              }}
            />
          )}
        />
      </View>
    </Modal>
  );
}
