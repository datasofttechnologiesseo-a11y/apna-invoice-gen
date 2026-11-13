import { useState } from 'react';
import { Pressable, StyleSheet, TextInput, View } from 'react-native';

import type { LineItem } from '@/api/types';
import { useProductSearch } from '@/hooks/queries';
import { colors, fonts, radius } from '@/theme';
import { money } from '@/utils/format';

import { AppText } from './app-text';
import { Card } from './blocks';
import { Button } from './button';
import { Icon } from './icon';
import { PickerRow, PickerSheet } from './picker-sheet';

/** GST slabs offered as quick picks (config/gst.php allows these). */
export const GST_SLABS = [0, 5, 12, 18, 28, 40];

/** Taxable value of one line, as the server computes it (qty × rate − discount). */
export const lineTaxable = (l: LineItem) => Math.max(0, l.quantity * l.rate - (l.discount || 0));

/**
 * Item lines for invoices (and later quotations, purchases, cash memos).
 * Pick from saved items or type a custom line; quantity stepper, rate and GST.
 */
export function LineItemsEditor({ items, onChange, error }: { items: LineItem[]; onChange: (items: LineItem[]) => void; error?: string }) {
  const [picking, setPicking] = useState(false);
  const [q, setQ] = useState('');
  const search = useProductSearch(q);

  const update = (i: number, patch: Partial<LineItem>) => onChange(items.map((l, j) => (j === i ? { ...l, ...patch } : l)));
  const remove = (i: number) => onChange(items.filter((_, j) => j !== i));

  return (
    <View style={{ gap: 10 }}>
      <View style={styles.head}>
        <AppText variant="h3">Items</AppText>
        <Button label="Add item" icon="add" size="sm" onPress={() => setPicking(true)} />
      </View>

      {items.map((l, i) => (
        <Card key={i} style={{ gap: 10 }}>
          <View style={{ flexDirection: 'row', gap: 8, alignItems: 'flex-start' }}>
            <TextInput
              value={l.description}
              onChangeText={(t) => update(i, { description: t })}
              placeholder="Item name"
              style={styles.name}
              accessibilityLabel={`Item ${i + 1} name`}
              multiline
            />
            <AppText variant="money" style={{ paddingTop: 4 }}>{money(lineTaxable(l))}</AppText>
            <Pressable accessibilityRole="button" accessibilityLabel={`Remove item ${i + 1}`} onPress={() => remove(i)} hitSlop={10} style={{ paddingTop: 4 }}>
              <Icon name="trash-outline" size={18} color={colors.redText} />
            </Pressable>
          </View>
          {l.hsn_sac ? (
            <AppText variant="small" color={colors.textMuted}>
              HSN/SAC {l.hsn_sac} · {l.unit ?? 'NOS'}
            </AppText>
          ) : null}
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
            <View style={styles.stepper} accessibilityRole="adjustable" accessibilityLabel={`Quantity ${l.quantity}`}>
              <Pressable accessibilityLabel="Decrease quantity" onPress={() => update(i, { quantity: Math.max(1, l.quantity - 1) })} style={styles.stepBtn} hitSlop={4}>
                <Icon name="remove" size={18} color={colors.tealDark} />
              </Pressable>
              <TextInput
                value={String(l.quantity)}
                onChangeText={(t) => update(i, { quantity: Number(t.replace(/[^\d.]/g, '')) || 0 })}
                keyboardType="decimal-pad"
                style={styles.qty}
                accessibilityLabel="Quantity"
                selectTextOnFocus
              />
              <Pressable accessibilityLabel="Increase quantity" onPress={() => update(i, { quantity: l.quantity + 1 })} style={styles.stepBtn} hitSlop={4}>
                <Icon name="add" size={18} color={colors.tealDark} />
              </Pressable>
            </View>
            <AppText color={colors.textMuted}>×</AppText>
            <View style={styles.rateBox}>
              <AppText style={{ fontFamily: fonts.bold }} color={colors.textMuted}>₹</AppText>
              <TextInput
                value={l.rate ? String(l.rate) : ''}
                onChangeText={(t) => update(i, { rate: Number(t.replace(/[^\d.]/g, '')) || 0 })}
                keyboardType="decimal-pad"
                placeholder="Rate"
                style={styles.rate}
                accessibilityLabel="Rate before tax"
                selectTextOnFocus
              />
            </View>
          </View>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
            {GST_SLABS.map((g) => {
              const on = l.gst_rate === g;
              return (
                <Pressable key={g} accessibilityRole="button" accessibilityState={{ selected: on }} accessibilityLabel={`GST ${g} percent`} onPress={() => update(i, { gst_rate: g })} style={[styles.gst, on ? styles.gstOn : null]}>
                  <AppText style={{ fontFamily: on ? fonts.bold : fonts.semibold, fontSize: 12 }} color={on ? colors.amberText : colors.textMuted}>
                    GST {g}%
                  </AppText>
                </Pressable>
              );
            })}
          </View>
        </Card>
      ))}

      {!items.length ? (
        <Pressable accessibilityRole="button" onPress={() => setPicking(true)} style={styles.emptyAdd}>
          <Icon name="add-circle-outline" size={22} color={colors.tealDark} />
          <AppText style={{ fontFamily: fonts.semibold }} color={colors.tealDark}>
            Add the first item
          </AppText>
        </Pressable>
      ) : null}
      {error ? <AppText variant="small" color={colors.redText}>{error}</AppText> : null}

      <PickerSheet
        visible={picking}
        title="Add item"
        placeholder="Search your items"
        items={search.data ?? []}
        loading={search.isFetching}
        onSearch={setQ}
        onClose={() => setPicking(false)}
        keyOf={(p) => String(p.id)}
        header={
          <View style={{ paddingHorizontal: 16, paddingBottom: 4 }}>
            <Button
              label={q ? `Add "${q}" as a new line` : 'Add a custom line'}
              kind="soft"
              size="md"
              icon="create-outline"
              onPress={() => {
                onChange([...items, { product_id: null, description: q, hsn_sac: null, quantity: 1, unit: 'NOS', rate: 0, discount: 0, gst_rate: 18 }]);
                setPicking(false);
              }}
            />
          </View>
        }
        renderItem={(p) => (
          <PickerRow
            title={p.name}
            subtitle={`${p.hsn_sac ? `HSN ${p.hsn_sac} · ` : ''}GST ${Number(p.gst_rate)}%${p.stock !== null ? ` · ${p.stock} in stock` : ''}`}
            right={money(Number(p.rate))}
            onPress={() => {
              onChange([...items, { product_id: p.id, description: p.name, hsn_sac: p.hsn_sac, quantity: 1, unit: p.unit, rate: Number(p.rate), discount: 0, gst_rate: Number(p.gst_rate) }]);
              setPicking(false);
            }}
          />
        )}
        empty={<AppText color={colors.textMuted} align="center">No saved items match. Add it as a custom line above.</AppText>}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 4 },
  name: { flex: 1, fontFamily: fonts.bold, fontSize: 15, color: colors.text, padding: 0 },
  stepper: { flexDirection: 'row', alignItems: 'center', height: 42, borderRadius: 12, backgroundColor: colors.ground },
  stepBtn: { width: 38, height: 42, alignItems: 'center', justifyContent: 'center' },
  qty: { minWidth: 34, textAlign: 'center', fontFamily: fonts.bold, fontSize: 15, color: colors.text, padding: 0 },
  rateBox: { flex: 1, flexDirection: 'row', alignItems: 'center', height: 42, borderRadius: 12, borderWidth: 1.5, borderColor: colors.borderStrong, paddingHorizontal: 10, gap: 4 },
  rate: { flex: 1, fontFamily: fonts.semibold, fontSize: 15, color: colors.text, padding: 0 },
  gst: { height: 30, paddingHorizontal: 10, borderRadius: radius.pill, backgroundColor: colors.ground, justifyContent: 'center' },
  gstOn: { backgroundColor: colors.amberTint },
  emptyAdd: { height: 64, borderRadius: 18, borderWidth: 1.5, borderStyle: 'dashed', borderColor: colors.tealLight, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8 },
});
