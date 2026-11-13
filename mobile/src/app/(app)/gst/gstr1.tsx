import { useState } from 'react';
import { Alert, Pressable, View } from 'react-native';

import { financeApi, Gstr1Hsn, Gstr1Summary } from '@/api/finance';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, KeyValue, Loading, Segmented, SectionTitle } from '@/components/blocks';
import { BarAction, Screen } from '@/components/screen';
import { HeadsTable } from '@/screens/gst/heads-table';
import { ReturnPeriodPicker, useReturnPeriod } from '@/screens/gst/return-period';
import { StatStrip } from '@/screens/finance/stat-strip';
import { useGstr1 } from '@/hooks/finance';
import { colors, fonts } from '@/theme';
import { money } from '@/utils/format';
import { downloadAndShare } from '@/utils/share';

/** GSTR-1 for a month/quarter: B2B vs B2C, table-wise summary (incl. credit notes), HSN (Table 12), portal JSON / Excel / CSV export. */
export default function Gstr1Screen() {
  const p = useReturnPeriod();
  const q = useGstr1(p.range.from, p.range.to);
  const [allTables, setAllTables] = useState(false);
  const [hsnTab, setHsnTab] = useState<'B2B' | 'B2C'>('B2C');
  const d = q.data;

  const exportAs = () =>
    Alert.alert('Export GSTR-1', p.label, [
      { text: 'JSON for the GST portal', onPress: () => downloadAndShare(financeApi.gstr1FilePath('json', p.qs), `gstr1-${p.month}.json`, 'application/json').catch((e) => Alert.alert('Could not export', (e as Error).message)) },
      { text: 'Excel (offline tool)', onPress: () => downloadAndShare(financeApi.gstr1FilePath('xlsx', p.qs), `gstr1-${p.month}.xlsx`, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') },
      { text: 'CSV', onPress: () => downloadAndShare(financeApi.gstr1CsvPath(p.range.from, p.range.to), `gstr1-${p.month}.csv`, 'text/csv') },
      { text: 'Cancel', style: 'cancel' },
    ]);

  const tables = d ? Object.entries(d.tables).filter(([, t]) => allTables || t.records > 0 || t.taxable !== 0) : [];
  const hsnRows = d ? d.hsn.filter((h) => h.tab === hsnTab) : [];

  return (
    <Screen
      nav="back"
      title="GSTR-1"
      subtitle={`Outward supplies · ${p.label}`}
      right={<BarAction icon="share-outline" label="Export GSTR-1" onPress={exportAs} />}
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
    >
      <ReturnPeriodPicker p={p} />

      {q.isLoading ? (
        <Loading />
      ) : q.error || !d ? (
        <ErrorState message={(q.error as Error)?.message ?? 'Could not load GSTR-1'} onRetry={() => q.refetch()} />
      ) : (
        <>
          <StatStrip
            stats={[
              { label: 'Taxable', value: d.gstr3b_outward.taxable },
              { label: 'IGST', value: d.gstr3b_outward.igst, tone: 'accent' },
              { label: 'CGST + SGST', value: d.gstr3b_outward.cgst + d.gstr3b_outward.sgst, tone: 'accent' },
            ]}
          />
          <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
            {d.gstr3b_outward.invoices} invoices and {d.gstr3b_outward.credit_notes} credit notes, net of credit notes — the figures GSTR-3B 3.1 takes.
          </AppText>

          <SectionTitle title="B2B and B2C" />
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <SummaryCard title="B2B" sub="Customers with GSTIN" s={d.b2b} />
            <SummaryCard title="B2C" sub="Without GSTIN" s={d.b2c} />
          </View>

          <SectionTitle title="Table-wise" action={allTables ? 'Hide empty' : 'Show all'} onAction={() => setAllTables((v) => !v)} />
          <Card>
            {tables.length ? (
              <HeadsTable rows={tables.map(([, t]) => ({ label: `${t.table} · ${t.label}`, hint: `${t.records} record${t.records === 1 ? '' : 's'}`, taxable: t.taxable, igst: t.igst, cgst: t.cgst, sgst: t.sgst }))} />
            ) : (
              <AppText variant="small" color={colors.textMuted}>Nothing to report for this period.</AppText>
            )}
          </Card>

          <SectionTitle title="HSN summary (Table 12)" />
          <Segmented
            value={hsnTab}
            options={[
              { value: 'B2B', label: `B2B (${d.hsn.filter((h) => h.tab === 'B2B').length})` },
              { value: 'B2C', label: `B2C (${d.hsn.filter((h) => h.tab === 'B2C').length})` },
            ]}
            onChange={setHsnTab}
          />
          <Card style={{ gap: 0 }}>
            {hsnRows.length ? hsnRows.map((h, i) => <HsnRow key={h.key} h={h} first={i === 0} />) : <AppText variant="small" color={colors.textMuted}>No {hsnTab} lines in this period.</AppText>}
          </Card>

          <Pressable accessibilityRole="button" onPress={exportAs} style={{ alignSelf: 'center', padding: 12 }}>
            <AppText style={{ fontFamily: fonts.semibold }} color={colors.tealDark}>
              Export for the GST portal or your CA
            </AppText>
          </Pressable>
        </>
      )}
    </Screen>
  );
}

function SummaryCard({ title, sub, s }: { title: string; sub: string; s: Gstr1Summary }) {
  return (
    <Card style={{ flex: 1, gap: 6 }}>
      <AppText variant="h3">{title}</AppText>
      <AppText variant="small" color={colors.textMuted}>{sub}</AppText>
      <AppText variant="moneyLg" numberOfLines={1} adjustsFontSizeToFit>{money(s.taxable)}</AppText>
      <AppText variant="small" color={colors.textMuted}>
        {s.count} invoice{s.count === 1 ? '' : 's'} · tax {money(s.igst + s.cgst + s.sgst)}
      </AppText>
    </Card>
  );
}

function HsnRow({ h, first }: { h: Gstr1Hsn; first: boolean }) {
  const tax = h.igst + h.cgst + h.sgst + h.cess;
  return (
    <View style={{ paddingVertical: 10, gap: 4, borderTopWidth: first ? 0 : 1, borderTopColor: colors.divider }}>
      <View style={{ flexDirection: 'row', gap: 8, alignItems: 'baseline' }}>
        <AppText style={{ fontFamily: fonts.bold, fontSize: 14 }}>{h.hsn || '—'}</AppText>
        <AppText variant="small" color={colors.textMuted} style={{ flex: 1 }} numberOfLines={1}>
          {h.description}
        </AppText>
        <AppText variant="money">{money(h.taxable)}</AppText>
      </View>
      <KeyValue label={`${h.qty} ${h.uqc} · GST ${h.rate}%`} value={`tax ${money(tax, { paise: true })}`} />
    </View>
  );
}
