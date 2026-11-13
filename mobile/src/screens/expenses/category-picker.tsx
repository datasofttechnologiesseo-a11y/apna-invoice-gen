import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import type { ExpenseCategory } from '@/api/expenses';
import { AppText } from '@/components/app-text';
import { Icon } from '@/components/icon';
import { PickerRow, PickerSheet } from '@/components/picker-sheet';
import { colors, fonts, radius } from '@/theme';

/** Expense category select: a field that opens the searchable picker sheet. */
export function CategoryPicker({
  label = 'Category',
  value,
  categories,
  onChange,
  error,
}: {
  label?: string;
  value: string;
  categories: ExpenseCategory[];
  onChange: (v: string) => void;
  error?: string;
}) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const current = categories.find((c) => c.value === value);
  const shown = q ? categories.filter((c) => c.label.toLowerCase().includes(q.toLowerCase())) : categories;
  return (
    <View style={{ gap: 6 }}>
      <AppText variant="label" color={colors.textBody}>
        {label}
      </AppText>
      <Pressable accessibilityRole="button" accessibilityLabel={`${label}: ${current?.label ?? 'not chosen'}. Change`} onPress={() => setOpen(true)} style={[styles.box, { borderColor: error ? colors.red : colors.borderStrong }]}>
        <View style={[styles.dot, { backgroundColor: current?.color ?? colors.border }]} />
        <AppText style={styles.value} color={current ? colors.text : colors.textMuted} numberOfLines={1}>
          {current?.label ?? 'Choose a category'}
        </AppText>
        <Icon name="chevron-down" size={18} color={colors.textMuted} />
      </Pressable>
      {error ? (
        <AppText variant="small" color={colors.redText}>
          {error}
        </AppText>
      ) : null}
      <PickerSheet
        visible={open}
        title={label}
        placeholder="Search categories"
        items={shown}
        onSearch={setQ}
        onClose={() => setOpen(false)}
        keyOf={(c) => c.value}
        renderItem={(c) => (
          <PickerRow
            title={c.label}
            right={c.value === value ? '✓' : undefined}
            onPress={() => {
              onChange(c.value);
              setOpen(false);
            }}
          />
        )}
        empty={<AppText color={colors.textMuted} align="center">No category matches.</AppText>}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  box: { minHeight: 52, borderWidth: 1.5, borderRadius: radius.md, backgroundColor: colors.surface, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 10 },
  dot: { width: 12, height: 12, borderRadius: 6 },
  value: { flex: 1, fontFamily: fonts.medium, fontSize: 16 },
});
