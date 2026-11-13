import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { currentMonth, monthLabel, monthRange, shiftMonth } from '@/api/purchases';
import { AppText } from '@/components/app-text';
import { Card, EmptyState, ErrorState, KeyValue, Loading, Segmented } from '@/components/blocks';
import { Screen } from '@/components/screen';
import { useHsn } from '@/hooks/purchases';
import { MonthSwitcher } from '@/screens/purchases/parts';
import { colors, fonts } from '@/theme';
import { money } from '@/utils/format';

const isMonth = (m?: string) => !!m && /^\d{4}-(0[1-9]|1[0-2])$/.test(m);

/** HSN-wise summary of outward supplies (GSTR-1 Table 12), split B2B / B2C as the portal asks. */
export default function HsnSummary() {
  const params = useLocalSearchParams<{ month?: string }>();
  const [month, setMonth] = useState(isMonth(params.month) ? params.month! : currentMonth());
  const [tab, setTab] = useState<'B2B' | 'B2C'>('B2B');
  const { from, to } = monthRange(month);
  const q = useHsn(from, to);
  const rows = q.data ?? [];
  const hasB2b = rows.some((r) => r.tab === 'B2B');
  const hasB2c = rows.some((r) => r.tab === 'B2C');
  const active = tab === 'B2B' && !hasB2b && hasB2c ? 'B2C' : tab === 'B2C' && !hasB2c && hasB2b ? 'B2B' : tab;
  const shown = rows.filter((r) => r.tab === active);
  const sum = (k: 'taxable' | 'igst' | 'cgst' | 'sgst' | 'cess' | 'value') => shown.reduce((s, r) => s + Number(r[k] || 0), 0);

  return (
    <Screen
      nav="back"
      title="HSN summary"
      subtitle="GSTR-1 Table 12"
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      headerExtra={<MonthSwitcher label={monthLabel(month)} onPrev={() => setMonth(shiftMonth(month, -1))} onNext={() => setMonth(shiftMonth(month, 1))} canNext={month < currentMonth()} />}
    >
      {q.isLoading ? (
        <Loading />
      ) : q.error ? (
        <ErrorState message={(q.error as Error).message} onRetry={() => q.refetch()} />
      ) : !rows.length ? (
        <EmptyState icon="barcode-outline" title={`No sales in ${monthLabel(month)}`} body="Issued invoices for the month are summarised here by HSN/SAC code, unit and GST rate." />
      ) : (
        <>
          {hasB2b && hasB2c ? (
            <Segmented
              value={active}
              onChange={setTab}
              options={[
                { value: 'B2B', label: 'B2B (to GSTINs)' },
                { value: 'B2C', label: 'B2C' },
              ]}
            />
          ) : null}
          <Card>
            <KeyValue label="Taxable value" value={money(sum('taxable'), { paise: true })} />
            <KeyValue label="IGST" value={money(sum('igst'), { paise: true })} />
            <KeyValue label="CGST" value={money(sum('cgst'), { paise: true })} />
            <KeyValue label="SGST / UTGST" value={money(sum('sgst'), { paise: true })} />
            {sum('cess') ? <KeyValue label="Cess" value={money(sum('cess'), { paise: true })} /> : null}
            <KeyValue label="Total value" value={money(sum('value'), { paise: true })} strong />
          </Card>
          <Card style={{ paddingVertical: 4, gap: 0 }}>
            {shown.map((r, i) => {
              const tax = Number(r.igst) + Number(r.cgst) + Number(r.sgst) + Number(r.cess);
              return (
                <View
                  key={r.key}
                  accessible
                  accessibilityLabel={`HSN ${r.hsn}, ${r.description ?? ''}, ${r.qty} ${r.uqc}, GST ${r.rate} percent, taxable ${money(r.taxable)}, tax ${money(tax)}`}
                  style={{ flexDirection: 'row', gap: 10, paddingVertical: 12, borderBottomWidth: i < shown.length - 1 ? 1 : 0, borderBottomColor: colors.divider }}
                >
                  <View style={{ flex: 1, gap: 2 }}>
                    <AppText style={{ fontFamily: fonts.semibold, fontSize: 15 }}>{r.hsn}</AppText>
                    {r.description ? (
                      <AppText variant="small" color={colors.textBody} numberOfLines={1}>
                        {r.description}
                      </AppText>
                    ) : null}
                    <AppText variant="small" color={colors.textMuted}>
                      {r.qty} {r.uqc} · GST {r.rate}%
                    </AppText>
                  </View>
                  <View style={{ alignItems: 'flex-end', gap: 2 }}>
                    <AppText variant="money">{money(r.taxable, { paise: true })}</AppText>
                    <AppText variant="small" color={colors.textMuted}>
                      tax {money(tax, { paise: true })}
                    </AppText>
                  </View>
                </View>
              );
            })}
          </Card>
          <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
            The full GSTR-1 file (JSON / Excel) with this table is on the GSTR-1 screen.
          </AppText>
        </>
      )}
    </Screen>
  );
}
