/** Small pieces shared by the vendor, purchase, GSTR-2B and stock screens. */
import { ComponentProps, ReactNode } from 'react';
import { StyleSheet, Switch, View } from 'react-native';

import type { PurchaseListItem } from '@/api/purchases';
import { AppText } from '@/components/app-text';
import { Pill } from '@/components/status-pill';
import { Button } from '@/components/button';
import { DateField as SharedDateField } from '@/components/date-field';
import { colors, fonts } from '@/theme';

type Tone = Parameters<typeof Pill>[0]['tone'];

/** The server's status label, coloured like invoice statuses. */
export function purchaseTone(b: Pick<PurchaseListItem, 'status' | 'is_overdue' | 'balance'>): Tone {
  if (b.status === 'paid' || b.balance <= 0) return 'paid';
  if (b.is_overdue) return 'late';
  if (b.status === 'partial') return 'part';
  return 'due';
}

export function PurchasePill({ bill }: { bill: Pick<PurchaseListItem, 'status' | 'status_label' | 'is_overdue' | 'balance'> }) {
  return <Pill label={bill.is_overdue && bill.balance > 0 ? 'Overdue' : bill.status_label} tone={purchaseTone(bill)} />;
}

/** On/off line with a title and an explanation (ITC, reverse charge, inter-state). */
export function ToggleRow({ title, body, value, onChange, disabled }: { title: string; body?: string; value: boolean; onChange: (v: boolean) => void; disabled?: boolean }) {
  return (
    <View style={styles.toggle}>
      <View style={{ flex: 1, gap: 2 }}>
        <AppText style={{ fontFamily: fonts.semibold, fontSize: 15 }}>{title}</AppText>
        {body ? (
          <AppText variant="small" color={colors.textMuted}>
            {body}
          </AppText>
        ) : null}
      </View>
      <Switch value={value} onValueChange={onChange} disabled={disabled} accessibilityLabel={title} trackColor={{ true: colors.teal, false: colors.border }} thumbColor={colors.surface} />
    </View>
  );
}

/** Purchases use the shared native date field (quick picks on). */
export function DateField(
  props: Omit<ComponentProps<typeof SharedDateField>, 'quick'> & {
    quick?: boolean;
  },
) {
  return <SharedDateField quick {...props} />;
}

/** Coloured note card (server messages: late booking, time-barred ITC, Rule 37). */
export function Note({ tone = 'info', children }: { tone?: 'info' | 'warn' | 'danger'; children: ReactNode }) {
  const bg = tone === 'danger' ? colors.redTint : tone === 'warn' ? colors.amberTint : colors.tealTint;
  const fg = tone === 'danger' ? colors.redText : tone === 'warn' ? colors.amberText : colors.tealDark;
  return (
    <View style={{ backgroundColor: bg, borderRadius: 16, padding: 14 }}>
      <AppText variant="small" color={fg} style={{ fontSize: 13, lineHeight: 19 }}>
        {children}
      </AppText>
    </View>
  );
}

/** Label + value tile for summary strips (moneyShort value, full value for screen readers). */
export function StatTile({ label, value, full, tone }: { label: string; value: string; full: string; tone?: 'danger' | 'warn' }) {
  return (
    <View style={styles.tile} accessible accessibilityLabel={`${label} ${full}`}>
      <AppText variant="small" color={colors.textMuted} numberOfLines={1}>
        {label}
      </AppText>
      <AppText variant="moneyLg" style={{ fontSize: 18 }} color={tone === 'danger' ? colors.redText : tone === 'warn' ? colors.amberText : colors.text} numberOfLines={1}>
        {value}
      </AppText>
    </View>
  );
}

/** ‹ Month › switcher for monthly returns. `canNext` false stops it at the latest month. */
export function MonthSwitcher({ label, onPrev, onNext, canNext = true, dark }: { label: string; onPrev: () => void; onNext: () => void; canNext?: boolean; dark?: boolean }) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
      <Button kind={dark ? 'glass' : 'outline'} size="md" icon="chevron-back" accessibilityLabel="Previous month" onPress={onPrev} />
      <AppText variant="h2" align="center" color={dark ? colors.onDark : colors.text} style={{ flex: 1 }} accessibilityRole="header" accessibilityLiveRegion="polite">
        {label}
      </AppText>
      <Button kind={dark ? 'glass' : 'outline'} size="md" icon="chevron-forward" accessibilityLabel="Next month" onPress={onNext} disabled={!canNext} />
    </View>
  );
}

const styles = StyleSheet.create({
  toggle: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    minHeight: 52,
  },
  pick: {
    paddingHorizontal: 14,
    height: 38,
    borderRadius: 12,
    justifyContent: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
  },
  pickOn: { backgroundColor: colors.navy, borderColor: colors.navy },
  tile: {
    flex: 1,
    backgroundColor: colors.surface,
    borderRadius: 16,
    paddingHorizontal: 12,
    paddingVertical: 10,
    gap: 2,
  },
});
