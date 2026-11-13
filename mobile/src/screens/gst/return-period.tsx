import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import type { ReturnPeriodParams } from '@/api/finance';
import { Segmented } from '@/components/blocks';
import { isMonth, lastMonth, monthLabel, monthRange, quarterRange, shiftMonth } from '@/screens/finance/periods';
import { MonthStepper } from '@/screens/finance/month-stepper';

const QUARTER_NAMES = ['Apr–Jun', 'Jul–Sep', 'Oct–Dec', 'Jan–Mar'];

/** Label for a quarterly period: "Oct–Dec 2026". */
function quarterLabel(ym: string) {
  const [y, m] = ym.split('-').map(Number);
  const q = Math.floor(((m + 8) % 12) / 3);
  return `${QUARTER_NAMES[q]} ${y}`;
}

/**
 * The GST return period every GST screen shares: ?month=YYYY-MM (&frequency=quarterly
 * for QRMP filers). Defaults to last month — the return being filed now. The
 * chosen period is written back to the URL so the hub's links carry it.
 */
export function useReturnPeriod() {
  const params = useLocalSearchParams<{ month?: string; frequency?: string }>();
  const [month, setMonthState] = useState(isMonth(params.month) ? params.month : lastMonth());
  const [frequency, setFrequencyState] = useState<'monthly' | 'quarterly'>(params.frequency === 'quarterly' ? 'quarterly' : 'monthly');

  const setMonth = (m: string) => {
    setMonthState(m);
    router.setParams({ month: m });
  };
  const setFrequency = (f: 'monthly' | 'quarterly') => {
    setFrequencyState(f);
    router.setParams({ frequency: f === 'quarterly' ? 'quarterly' : undefined });
  };

  const range = frequency === 'quarterly' ? quarterRange(month) : monthRange(month);
  const label = frequency === 'quarterly' ? quarterLabel(month) : monthLabel(month);
  const query: ReturnPeriodParams = { month, frequency };
  const qs = `month=${month}${frequency === 'quarterly' ? '&frequency=quarterly' : ''}`;
  return { month, frequency, setMonth, setFrequency, range, label, query, qs };
}

/** Month (or quarter) stepper + Monthly/Quarterly switch. */
export function ReturnPeriodPicker({ p }: { p: ReturnType<typeof useReturnPeriod> }) {
  const quarterly = p.frequency === 'quarterly';
  return (
    <View style={{ gap: 10 }}>
      <MonthStepper
        value={quarterly ? shiftMonth(p.range.from.slice(0, 7), 2) : p.month}
        label={p.label}
        step={quarterly ? 3 : 1}
        onChange={(m) => p.setMonth(m)}
      />
      <Segmented
        value={p.frequency}
        options={[
          { value: 'monthly', label: 'Monthly' },
          { value: 'quarterly', label: 'Quarterly (QRMP)' },
        ]}
        onChange={p.setFrequency}
      />
    </View>
  );
}
