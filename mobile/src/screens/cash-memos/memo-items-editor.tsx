import { Pressable, StyleSheet, TextInput, View } from 'react-native';

import { AppText } from '@/components/app-text';
import { Card } from '@/components/blocks';
import { Button } from '@/components/button';
import { Icon } from '@/components/icon';
import { colors, fonts, radius } from '@/theme';
import { money } from '@/utils/format';

/** A cash memo line as edited (text fields so partial input like "2." survives). */
export type MemoLine = { key: string; description: string; hsn_sac: string; quantity: string; unit: string; rate: string };

let seq = 0;
export const newMemoLine = (): MemoLine => ({ key: `l${++seq}`, description: '', hsn_sac: '', quantity: '1', unit: '', rate: '' });
export const memoLineAmount = (l: MemoLine) => (Number(l.quantity) || 0) * (Number(l.rate) || 0);

const num = (t: string) => t.replace(/[^\d.]/g, '');

/**
 * Lines for a cash memo: what was bought, quantity × rate. Unlike invoices,
 * the memo carries one GST rate for the whole memo, so lines have no GST
 * (that is why this is not the shared LineItemsEditor).
 */
export function MemoItemsEditor({ lines, onChange, errors }: { lines: MemoLine[]; onChange: (l: MemoLine[]) => void; errors: Record<string, string> }) {
  const update = (i: number, patch: Partial<MemoLine>) => onChange(lines.map((l, j) => (j === i ? { ...l, ...patch } : l)));
  const remove = (i: number) => onChange(lines.filter((_, j) => j !== i));
  const listError = errors.items;
  return (
    <View style={{ gap: 10 }}>
      <View style={styles.head}>
        <AppText variant="h3">Items bought</AppText>
        <Button label="Add line" icon="add" size="sm" onPress={() => onChange([...lines, newMemoLine()])} />
      </View>
      {lines.map((l, i) => {
        const err = errors[`items.${i}.description`] ?? errors[`items.${i}.quantity`] ?? errors[`items.${i}.rate`] ?? errors[`items.${i}.hsn_sac`];
        return (
          <Card key={l.key} style={{ gap: 10 }}>
            <View style={{ flexDirection: 'row', gap: 8, alignItems: 'flex-start' }}>
              <TextInput
                value={l.description}
                onChangeText={(t) => update(i, { description: t })}
                placeholder="What was bought"
                placeholderTextColor={colors.textMuted}
                style={styles.name}
                accessibilityLabel={`Line ${i + 1} description`}
                maxLength={255}
                multiline
              />
              <AppText variant="money" style={{ paddingTop: 2 }}>{money(memoLineAmount(l))}</AppText>
              {lines.length > 1 ? (
                <Pressable accessibilityRole="button" accessibilityLabel={`Remove line ${i + 1}`} onPress={() => remove(i)} hitSlop={10} style={{ paddingTop: 2 }}>
                  <Icon name="trash-outline" size={18} color={colors.redText} />
                </Pressable>
              ) : null}
            </View>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              <Mini label="Qty" value={l.quantity} onChange={(t) => update(i, { quantity: num(t) })} numeric flex={1} />
              <Mini label="Unit" value={l.unit} onChange={(t) => update(i, { unit: t })} placeholder="kg, pcs" flex={1} />
              <Mini label="Rate ₹" value={l.rate} onChange={(t) => update(i, { rate: num(t) })} numeric flex={1.4} />
              <Mini label="HSN/SAC" value={l.hsn_sac} onChange={(t) => update(i, { hsn_sac: t.replace(/\D/g, '') })} numeric flex={1.3} />
            </View>
            {err ? <AppText variant="small" color={colors.redText}>{err}</AppText> : null}
          </Card>
        );
      })}
      {listError ? <AppText variant="small" color={colors.redText}>{listError}</AppText> : null}
    </View>
  );
}

function Mini({ label, value, onChange, numeric, placeholder, flex }: { label: string; value: string; onChange: (t: string) => void; numeric?: boolean; placeholder?: string; flex: number }) {
  return (
    <View style={{ flex, gap: 4 }}>
      <AppText variant="small" color={colors.textMuted}>{label}</AppText>
      <TextInput
        value={value}
        onChangeText={onChange}
        keyboardType={numeric ? 'decimal-pad' : 'default'}
        placeholder={placeholder}
        placeholderTextColor={colors.textMuted}
        accessibilityLabel={label}
        selectTextOnFocus
        style={styles.mini}
        maxLength={numeric ? 12 : 20}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 4 },
  name: { flex: 1, fontFamily: fonts.bold, fontSize: 15, color: colors.text, padding: 0 },
  mini: { height: 42, borderRadius: radius.sm + 2, borderWidth: 1.5, borderColor: colors.borderStrong, paddingHorizontal: 8, fontFamily: fonts.semibold, fontSize: 15, color: colors.text },
});
