import { Alert, View } from 'react-native';

import { financeApi } from '@/api/finance';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, KeyValue, Loading, SectionTitle } from '@/components/blocks';
import { BarAction, Screen } from '@/components/screen';
import { Pill } from '@/components/status-pill';
import { useGstr3b } from '@/hooks/finance';
import { HeadsTable } from '@/screens/gst/heads-table';
import { ReturnPeriodPicker, useReturnPeriod } from '@/screens/gst/return-period';
import { colors, fonts } from '@/theme';
import { date, money } from '@/utils/format';
import { downloadAndShare } from '@/utils/share';

/** GSTR-3B summary (tables 3.1, 4 and 6.1) with the tax to pay in cash. CSV/PDF export. Accepts ?month=&frequency=. */
export default function Gstr3bScreen() {
  const p = useReturnPeriod();
  const q = useGstr3b(p.query);
  const d = q.data;

  const exportReport = () =>
    Alert.alert('Export GSTR-3B', p.label, [
      { text: 'PDF', onPress: () => downloadAndShare(financeApi.gstr3bPdfPath(p.query), `gstr3b-${p.month}.pdf`) },
      { text: 'CSV (Excel)', onPress: () => downloadAndShare(financeApi.gstr3bCsvPath(p.query), `gstr3b-${p.month}.csv`, 'text/csv') },
      { text: 'Cancel', style: 'cancel' },
    ]);

  return (
    <Screen
      hero
      nav="back"
      title="GSTR-3B"
      subtitle={d?.period.label ?? p.label}
      right={<BarAction hero icon="share-outline" label="Export GSTR-3B" onPress={exportReport} />}
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      headerExtra={
        d ? (
          <View style={{ gap: 6, paddingBottom: 24 }}>
            <AppText color={colors.onDarkMuted} style={{ fontSize: 14 }}>
              Tax to pay in cash · due {date(d.period.due_date)}
            </AppText>
            <AppText variant="hero" color={colors.onDark} accessibilityLabel={`Tax to pay in cash ${money(d.net_cash.total)}`}>
              {money(d.net_cash.total)}
            </AppText>
            <AppText variant="small" color={colors.onDarkMuted}>
              Input tax credit used {money(d.itc_used.igst + d.itc_used.cgst + d.itc_used.sgst)}
              {d.itc_carried.total > 0 ? ` · ${money(d.itc_carried.total)} carried forward` : ''}
            </AppText>
          </View>
        ) : null
      }
    >
      <ReturnPeriodPicker p={p} />

      {q.isLoading ? (
        <Loading />
      ) : q.error || !d ? (
        <ErrorState message={(q.error as Error)?.message ?? 'Could not load GSTR-3B'} onRetry={() => q.refetch()} />
      ) : (
        <>
          <SectionTitle title="3.1 Outward and reverse-charge supplies" />
          <Card>
            <HeadsTable
              rows={[
                { label: '(a) Taxable outward supplies', taxable: d.outward.taxable, igst: d.outward.igst, cgst: d.outward.cgst, sgst: d.outward.sgst },
                { label: '(b) Zero-rated (export / SEZ)', taxable: d.outward_zero.taxable, igst: d.outward_zero.igst, cgst: 0, sgst: 0 },
                { label: '(c) Nil-rated / exempt', taxable: d.outward_nil, igst: 0, cgst: 0, sgst: 0 },
                { label: '(d) Inward supplies on reverse charge', taxable: d.rcm_inward.taxable, igst: d.rcm_inward.igst, cgst: d.rcm_inward.cgst, sgst: d.rcm_inward.sgst },
              ]}
            />
            <AppText variant="small" color={colors.textMuted}>
              From {d.invoice_count} invoices and {d.credit_note_count} credit notes.
            </AppText>
          </Card>

          <SectionTitle title="4 Eligible input tax credit" />
          <Card>
            <HeadsTable
              taxable={false}
              rows={[
                { label: '(A)(3) Reverse charge', ...d.itc_rcm },
                { label: '(A)(5) All other ITC', ...d.itc_other },
                ...(d.itc_reclaimed.total ? [{ label: 'incl. Rule 37 re-claim', ...d.itc_reclaimed }] : []),
                ...(d.itc_reversed.total ? [{ label: '(B)(2) Reversed (Rule 37)', ...d.itc_reversed }] : []),
                { label: '(C) Net ITC available', ...d.itc, strong: true },
              ]}
            />
            <AppText variant="small" color={colors.textMuted}>
              From {d.purchase_bill_count} purchase bills, {d.expense_count} expenses and {d.cash_memo_count} cash memos.
            </AppText>
            {d.itc_time_barred.total > 0 ? (
              <View style={{ flexDirection: 'row', gap: 8, alignItems: 'center' }}>
                <Pill label="Left out" tone="late" />
                <AppText variant="small" color={colors.textBody} style={{ flex: 1 }}>
                  {money(d.itc_time_barred.total)} on {d.itc_time_barred.count} bill{d.itc_time_barred.count === 1 ? '' : 's'} is past the s.16(4) time limit.
                </AppText>
              </View>
            ) : null}
          </Card>

          <SectionTitle title="6.1 Payment of tax" />
          <Card>
            <HeadsTable
              taxable={false}
              rows={[
                ...(d.negative_liability_in.igst + d.negative_liability_in.cgst + d.negative_liability_in.sgst
                  ? [{ label: 'Negative liability brought in', ...d.negative_liability_in }]
                  : []),
                { label: 'Paid through ITC', ...d.itc_used },
                { label: 'Paid in cash', ...d.net_cash, strong: true },
              ]}
            />
            <View style={{ height: 1, backgroundColor: colors.divider }} />
            <KeyValue label="Total to pay in cash" value={money(d.net_cash.total)} strong />
            {d.itc_carried.total > 0 ? <KeyValue label="ITC carried to next period" value={money(d.itc_carried.total)} /> : null}
            {d.negative_liability_out.igst + d.negative_liability_out.cgst + d.negative_liability_out.sgst ? (
              <KeyValue label="Negative liability carried forward" value={money(d.negative_liability_out.igst + d.negative_liability_out.cgst + d.negative_liability_out.sgst)} />
            ) : null}
          </Card>

          <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4, fontFamily: fonts.body }}>
            {d.period.frequency === 'quarterly' ? 'Quarterly (QRMP) return. ' : ''}Check these figures against the portal before you file.
          </AppText>
        </>
      )}
    </Screen>
  );
}
