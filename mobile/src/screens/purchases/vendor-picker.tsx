import { useState } from 'react';
import { Modal, Pressable, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-controller';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ApiError } from '@/api/client';
import type { Vendor } from '@/api/purchases';
import { AppText } from '@/components/app-text';
import { Avatar, Card } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { PickerRow, PickerSheet } from '@/components/picker-sheet';
import { useSaveVendor, useVendors } from '@/hooks/purchases';
import { colors, fonts } from '@/theme';
import { money } from '@/utils/format';

import { draftOf, inputOf, VendorDraft, VendorFields } from './vendor-form';

/** Select a vendor, or add one inline. Modelled on components/party-picker. */
export function VendorPicker({ value, onChange, error }: { value: Vendor | null; onChange: (v: Vendor) => void; error?: string }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const [adding, setAdding] = useState(false);
  const list = useVendors(q);
  const items = list.data?.pages.flatMap((p) => p.data) ?? [];

  return (
    <>
      <Pressable accessibilityRole="button" accessibilityLabel={value ? `Vendor ${value.name}. Change` : 'Choose vendor'} onPress={() => setOpen(true)}>
        <Card style={{ flexDirection: 'row', alignItems: 'center', gap: 12, borderWidth: error ? 1.5 : 0, borderColor: colors.red }}>
          {value ? (
            <>
              <Avatar name={value.name} tone="dark" />
              <View style={{ flex: 1, gap: 2 }}>
                <AppText variant="small" color={colors.textMuted}>Bought from</AppText>
                <AppText style={{ fontFamily: fonts.bold, fontSize: 15 }}>{value.name}</AppText>
                <AppText variant="small" color={colors.textMuted}>
                  {[value.gstin ?? 'No GSTIN', value.state_name].filter(Boolean).join(' · ')}
                </AppText>
              </View>
              <AppText variant="label" color={colors.tealDark}>Change</AppText>
            </>
          ) : (
            <>
              <View style={{ width: 44, height: 44, borderRadius: 14, backgroundColor: colors.tealTint, alignItems: 'center', justifyContent: 'center' }}>
                <Icon name="storefront-outline" color={colors.teal} />
              </View>
              <AppText style={{ flex: 1, fontFamily: fonts.semibold, fontSize: 15 }}>Choose vendor</AppText>
              <Icon name="chevron-forward" color={colors.textMuted} />
            </>
          )}
        </Card>
      </Pressable>
      {error ? <AppText variant="small" color={colors.redText}>{error}</AppText> : null}

      <PickerSheet
        visible={open && !adding}
        title="Bought from"
        placeholder="Search vendors"
        items={items}
        loading={list.isFetching}
        onSearch={setQ}
        onClose={() => setOpen(false)}
        keyOf={(v) => String(v.id)}
        header={
          <View style={{ paddingHorizontal: 16, paddingBottom: 4 }}>
            <Button label="New vendor" icon="add" kind="soft" size="md" onPress={() => setAdding(true)} />
          </View>
        }
        renderItem={(v) => (
          <PickerRow
            title={v.name}
            subtitle={[v.state_name, v.phone, v.gstin].filter(Boolean).join(' · ')}
            right={v.payable ? money(v.payable) : undefined}
            onPress={() => {
              onChange(v);
              setOpen(false);
            }}
          />
        )}
        empty={<AppText color={colors.textMuted} align="center">No vendors match.</AppText>}
      />
      <NewVendorSheet
        visible={open && adding}
        initialName={q}
        onClose={() => setAdding(false)}
        onCreated={(v) => {
          onChange(v);
          setAdding(false);
          setOpen(false);
        }}
      />
    </>
  );
}

function NewVendorSheet({ visible, initialName, onClose, onCreated }: { visible: boolean; initialName: string; onClose: () => void; onCreated: (v: Vendor) => void }) {
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.surface }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 16, paddingTop: 20 }}>
          <AppText variant="h2" style={{ flex: 1 }} accessibilityRole="header">
            New vendor
          </AppText>
          <Button kind="outline" size="md" icon="close" accessibilityLabel="Close" onPress={onClose} />
        </View>
        <KeyboardAwareScrollView bottomOffset={24} keyboardShouldPersistTaps="handled" contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}>
          {/* Remounts each time the sheet opens, so the search text seeds the name. */}
          {visible ? <NewVendorBody initialName={initialName} onCreated={onCreated} /> : null}
        </KeyboardAwareScrollView>
      </View>
    </Modal>
  );
}

function NewVendorBody({ initialName, onCreated }: { initialName: string; onCreated: (v: Vendor) => void }) {
  const save = useSaveVendor();
  const [draft, setDraft] = useState<VendorDraft>(() => draftOf(null, initialName));
  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};
  return (
    <View style={{ paddingHorizontal: 16, gap: 12 }}>
      <VendorFields value={draft} onChange={setDraft} errors={errors} full={false} />
      <Button label="Save vendor" loading={save.isPending} disabled={!draft.name.trim()} onPress={() => save.mutate({ input: inputOf(draft) }, { onSuccess: onCreated })} />
      {save.error && !Object.keys(errors).length ? <AppText variant="small" color={colors.redText}>{(save.error as Error).message}</AppText> : null}
    </View>
  );
}
