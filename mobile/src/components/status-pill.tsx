import { View } from 'react-native';

import { colors, fonts } from '@/theme';
import { daysFromToday } from '@/utils/format';

import { AppText } from './app-text';

type Tone = 'draft' | 'due' | 'part' | 'paid' | 'late' | 'off' | 'info';

const tones: Record<Tone, { bg: string; fg: string }> = {
  draft: { bg: colors.slateTint, fg: colors.slateText },
  due: { bg: colors.amberTint, fg: colors.amberText },
  part: { bg: colors.tealSoft, fg: colors.tealDark },
  paid: { bg: colors.greenTint, fg: colors.greenText },
  late: { bg: colors.redTint, fg: colors.redText },
  off: { bg: colors.textMuted, fg: colors.onDark },
  info: { bg: colors.tealSoft, fg: colors.tealDark },
};

export function Pill({ label, tone }: { label: string; tone: Tone }) {
  const t = tones[tone];
  return (
    <View style={{ backgroundColor: t.bg, borderRadius: 999, paddingHorizontal: 9, paddingVertical: 3, alignSelf: 'flex-start' }}>
      <AppText style={{ fontFamily: fonts.bold, fontSize: 11 }} color={t.fg}>
        {label}
      </AppText>
    </View>
  );
}

/** Invoice status exactly as the web shows it; "Overdue" is derived from the due date. */
export function invoiceStatus(status: string, dueDate: string | null, balance: number): { label: string; tone: Tone } {
  if (status === 'draft') return { label: 'Draft', tone: 'draft' };
  if (status === 'cancelled') return { label: 'Cancelled', tone: 'off' };
  if (status === 'paid' || balance <= 0) return { label: 'Paid', tone: 'paid' };
  const days = daysFromToday(dueDate);
  if (days !== null && days < 0) return { label: 'Overdue', tone: 'late' };
  if (status === 'partially_paid') return { label: 'Part paid', tone: 'part' };
  return { label: 'Unpaid', tone: 'due' };
}

export function InvoicePill(p: { status: string; dueDate: string | null; balance: number }) {
  const s = invoiceStatus(p.status, p.dueDate, p.balance);
  return <Pill label={s.label} tone={s.tone} />;
}

const quoteTones: Record<string, { label: string; tone: Tone }> = {
  draft: { label: 'Draft', tone: 'draft' },
  sent: { label: 'Sent', tone: 'info' },
  accepted: { label: 'Accepted', tone: 'paid' },
  declined: { label: 'Declined', tone: 'late' },
  converted: { label: 'Converted', tone: 'part' },
  expired: { label: 'Expired', tone: 'off' },
};

export function QuotePill({ status }: { status: string }) {
  const s = quoteTones[status] ?? { label: status, tone: 'draft' as Tone };
  return <Pill label={s.label} tone={s.tone} />;
}
