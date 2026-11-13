import { ReactNode, useState } from 'react';
import { Switch, View } from 'react-native';

import { ApiError } from '@/api/client';
import { ItemDetail, ItemInput, UNITS } from '@/api/items';
import { AppText } from '@/components/app-text';
import { Card, Chips, Segmented } from '@/components/blocks';
import { Button } from '@/components/button';
import { GST_SLABS } from '@/components/line-items-editor';
import { PickerRow, PickerSheet } from '@/components/picker-sheet';
import { Screen } from '@/components/screen';
import { SelectField } from '@/components/select-field';
import { TextField } from '@/components/text-field';
import { useAuth } from '@/auth/auth-context';
import { useSaveItem } from '@/hooks/items';
import { colors } from '@/theme';

/**
 * Add or edit an item — same fields as the web product form. HSN format,
 * allowed units, GST slabs and SKU uniqueness are checked by the server.
 */
export function ItemForm({
  initial,
  title,
  subtitle,
  top,
  bottom,
  onSaved,
}: {
  initial?: ItemDetail;
  title: string;
  subtitle?: string;
  /** Shown above the fields (stock card on the edit screen). */
  top?: ReactNode;
  /** Shown below the fields (delete / restore). */
  bottom?: ReactNode;
  onSaved: (item: ItemDetail) => void;
}) {
  const { company } = useAuth();
  const save = useSaveItem();
  const [kind, setKind] = useState<'goods' | 'service'>(initial?.kind ?? 'goods');
  const [f, setF] = useState({
    name: initial?.name ?? '',
    hsn_sac: initial?.hsn_sac ?? '',
    rate: initial ? String(Number(initial.rate)) : '',
    sku: initial?.sku ?? '',
    description: initial?.description ?? '',
    opening_stock: initial?.opening_stock != null ? String(Number(initial.opening_stock)) : '',
    low_stock_level: initial?.low_stock_level != null ? String(Number(initial.low_stock_level)) : '',
    purchase_rate: initial?.purchase_rate != null ? String(Number(initial.purchase_rate)) : '',
  });
  const [track, setTrack] = useState(!!initial?.track_stock);
  const [unit, setUnit] = useState(initial?.unit ?? (initial?.kind === 'service' ? 'OTH' : 'NOS'));
  const [gst, setGst] = useState(String(Number(initial?.gst_rate ?? 18)));
  const [pickUnit, setPickUnit] = useState(false);
  const [unitQ, setUnitQ] = useState('');

  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));
  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {};
  const hsnRequired = !!company?.gstin;

  // Keep a saved rate that is not a quick pick (e.g. 3% gold) selectable.
  const slabs = GST_SLABS.includes(Number(gst)) ? GST_SLABS : [...GST_SLABS, Number(gst)].sort((a, b) => a - b);
  const unitLabel = UNITS.find((u) => u.code === unit);
  const units = UNITS.filter((u) => !unitQ || `${u.code} ${u.label}`.toLowerCase().includes(unitQ.toLowerCase()));

  const ready = !!f.name.trim() && f.rate.trim() !== '' && !!unit;

  const submit = () => {
    const input: ItemInput = {
      name: f.name.trim(),
      kind,
      hsn_sac: f.hsn_sac.trim() || null,
      unit,
      rate: Number(f.rate) || 0,
      gst_rate: Number(gst),
      sku: f.sku.trim() || null,
      description: f.description.trim() || null,
      purchase_rate: f.purchase_rate.trim() === '' ? null : Number(f.purchase_rate),
      // Stock (goods only; the server ignores it for services)
      track_stock: kind === 'goods' && track,
      opening_stock: kind === 'goods' && track ? Number(f.opening_stock) || 0 : null,
      low_stock_level: kind === 'goods' && track && f.low_stock_level.trim() !== '' ? Number(f.low_stock_level) : null,
      // Sent on create so the 201 response says active (the server's default is active too).
      ...(initial ? {} : { is_active: true }),
    };
    save.mutate({ id: initial?.id, input }, { onSuccess: onSaved });
  };

  return (
    <Screen
      nav="close"
      title={title}
      subtitle={subtitle}
      footer={<Button label={initial ? 'Save changes' : 'Save item'} icon="checkmark" loading={save.isPending} disabled={!ready} onPress={submit} />}
    >
      {top}
      <Card>
        <Segmented
          value={kind}
          onChange={setKind}
          options={[
            { value: 'goods', label: 'Goods' },
            { value: 'service', label: 'Service' },
          ]}
        />
        <TextField label="Item name" value={f.name} onChangeText={set('name')} placeholder={kind === 'goods' ? 'Ceiling fan 1200mm' : 'Annual maintenance'} error={errors.name} autoFocus={!initial} />
        <TextField
          label={`${kind === 'goods' ? 'HSN code' : 'SAC code'}${hsnRequired ? '' : ' (optional)'}`}
          value={f.hsn_sac}
          onChangeText={(t) => set('hsn_sac')(t.replace(/\D/g, ''))}
          keyboardType="number-pad"
          maxLength={8}
          placeholder={kind === 'goods' ? '8414' : '9987'}
          hint={kind === 'service' ? '4 to 8 digits. Service codes start with 99.' : '4 to 8 digits, as printed on the invoice.'}
          error={errors.hsn_sac}
        />
        <TextField
          label="Sale price (before GST)"
          value={f.rate}
          onChangeText={(t) => set('rate')(t.replace(/[^\d.]/g, ''))}
          keyboardType="decimal-pad"
          placeholder="0"
          prefix={<AppText color={colors.textMuted}>₹</AppText>}
          error={errors.rate}
        />
        <SelectField label="Unit" value={unitLabel ? `${unitLabel.code} · ${unitLabel.label}` : unit} placeholder="Choose unit" error={errors.unit} onPress={() => setPickUnit(true)} />
        <View style={{ gap: 6 }}>
          <AppText variant="label" color={colors.textBody}>GST rate</AppText>
          <Chips value={gst} onChange={setGst} options={slabs.map((g) => ({ value: String(g), label: `${g}%` }))} />
          {errors.gst_rate ? <AppText variant="small" color={colors.redText}>{errors.gst_rate}</AppText> : null}
        </View>
      </Card>

      {kind === 'goods' ? (
        <Card>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
            <View style={{ flex: 1 }}>
              <AppText variant="h3">Track stock</AppText>
              <AppText variant="small" color={colors.textMuted}>
                Sales reduce it, purchases add to it. Get a low-stock alert.
              </AppText>
            </View>
            <Switch value={track} onValueChange={setTrack} trackColor={{ true: colors.teal, false: colors.border }} thumbColor={colors.surface} accessibilityLabel="Track stock" />
          </View>
          {track ? (
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <View style={{ flex: 1 }}>
                <TextField label="Opening stock" value={f.opening_stock} onChangeText={set('opening_stock')} keyboardType="decimal-pad" placeholder="0" error={errors.opening_stock} />
              </View>
              <View style={{ flex: 1 }}>
                <TextField label="Alert at or below" value={f.low_stock_level} onChangeText={set('low_stock_level')} keyboardType="decimal-pad" placeholder="Optional" error={errors.low_stock_level} />
              </View>
            </View>
          ) : null}
        </Card>
      ) : null}

      <Card>
        <TextField label="Purchase price (optional)" value={f.purchase_rate} onChangeText={set('purchase_rate')} keyboardType="decimal-pad" placeholder="Used for stock value and margin" error={errors.purchase_rate} />
        <TextField label="SKU / item code (optional)" value={f.sku} onChangeText={set('sku')} autoCapitalize="characters" autoCorrect={false} maxLength={60} error={errors.sku} />
        <TextField label="Description (optional)" value={f.description} onChangeText={set('description')} multiline maxLength={1000} error={errors.description} />
      </Card>

      {save.error && !Object.keys(errors).length ? <AppText variant="small" color={colors.redText}>{(save.error as Error).message}</AppText> : null}

      {bottom}

      <PickerSheet
        visible={pickUnit}
        title="Unit"
        placeholder="Search units"
        items={units}
        onSearch={setUnitQ}
        onClose={() => setPickUnit(false)}
        keyOf={(u) => u.code}
        renderItem={(u) => (
          <PickerRow
            title={u.code}
            subtitle={u.label}
            onPress={() => {
              setUnit(u.code);
              setPickUnit(false);
            }}
          />
        )}
      />
    </Screen>
  );
}
