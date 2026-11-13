import { DateTimePicker } from '@expo/ui/community/datetime-picker';
import { useState } from 'react';
import { Platform, Pressable, StyleSheet, View } from 'react-native';

import { AppText } from '@/components/app-text';
import { Icon } from '@/components/icon';
import { colors, fonts, radius } from '@/theme';
import { fromIso, toIso } from '@/screens/finance/periods';
import { addDays, date, today } from '@/utils/format';

type Pick = { label: string; value: string };

/**
 * The app's one date input (value YYYY-MM-DD). iOS shows the compact native
 * picker in the field; Android opens the system date dialog on tap. Optional
 * quick picks (Today / Yesterday / extra) sit under it. `optional` allows an
 * empty value with a Clear pick. Server 422 shown below.
 */
export function DateField({
  label,
  value,
  onChange,
  error,
  min,
  max,
  quick = false,
  extra,
  optional,
}: {
  label: string;
  value: string;
  onChange: (iso: string) => void;
  error?: string;
  min?: string;
  max?: string;
  quick?: boolean;
  extra?: Pick[];
  optional?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const empty = !/^\d{4}-\d{2}-\d{2}$/.test(value);
  // An empty optional date shows "Not set"; the picker opens on today.
  const ios = Platform.OS === 'ios' && !empty;
  const seed = empty ? today() : value;
  const picks: Pick[] = [
    ...(quick
      ? [
          { label: 'Today', value: today() },
          { label: 'Yesterday', value: addDays(today(), -1) },
        ]
      : []),
    ...(extra ?? []),
    ...(optional && !empty ? [{ label: 'Clear', value: '' }] : []),
  ];
  return (
    <View style={{ gap: 6 }}>
      <AppText variant="label" color={colors.textBody}>
        {label}
      </AppText>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${label}: ${empty ? 'not set' : date(value)}. Change date`}
        disabled={ios}
        onPress={() => setOpen(true)}
        style={[styles.box, { borderColor: error ? colors.red : colors.borderStrong }]}
      >
        <Icon name="calendar-outline" size={18} color={colors.textMuted} />
        {ios ? (
          <DateTimePicker
            value={fromIso(seed)}
            mode="date"
            display="compact"
            accentColor={colors.teal}
            minimumDate={min ? fromIso(min) : undefined}
            maximumDate={max ? fromIso(max) : undefined}
            onValueChange={(_, d) => onChange(toIso(d))}
          />
        ) : (
          <AppText style={styles.value} color={empty ? colors.textMuted : colors.text}>
            {empty ? (optional ? 'Not set — tap to pick' : 'Pick a date') : date(value)}
          </AppText>
        )}
      </Pressable>
      {!ios && open ? (
        <DateTimePicker
          value={fromIso(seed)}
          mode="date"
          presentation="dialog"
          accentColor={colors.teal}
          minimumDate={min ? fromIso(min) : undefined}
          maximumDate={max ? fromIso(max) : undefined}
          onValueChange={(_, d) => {
            setOpen(false);
            onChange(toIso(d));
          }}
          onDismiss={() => setOpen(false)}
        />
      ) : null}
      {picks.length ? (
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
          {picks.map((p) => {
            const on = p.value === value && p.value !== '';
            return (
              <Pressable key={p.label} accessibilityRole="button" accessibilityState={{ selected: on }} onPress={() => onChange(p.value)} style={[styles.pick, on ? styles.pickOn : null]}>
                <AppText
                  style={{
                    fontFamily: fonts.semibold,
                    fontSize: 13,
                  }}
                  color={on ? colors.onDark : colors.text}
                >
                  {p.label}
                </AppText>
              </Pressable>
            );
          })}
        </View>
      ) : null}
      {error ? (
        <AppText variant="small" color={colors.redText} accessibilityLiveRegion="polite">
          {error}
        </AppText>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  box: {
    minHeight: 52,
    borderWidth: 1.5,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    gap: 10,
  },
  value: { flex: 1, fontFamily: fonts.medium, fontSize: 16 },
  pick: {
    height: 34,
    paddingHorizontal: 12,
    borderRadius: 99,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    justifyContent: 'center',
  },
  pickOn: { backgroundColor: colors.navy, borderColor: colors.navy },
});
