import { FlashList } from '@shopify/flash-list';
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, useWindowDimensions, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ApiError } from '@/api/client';
import { Report, ReportCell, ReportColumn, ReportFormat, reportsApi } from '@/api/workspace';
import { ActionSheet } from '@/components/action-sheet';
import { AppText } from '@/components/app-text';
import { Card, Chips, EmptyState, ErrorState, Loading, Segmented } from '@/components/blocks';
import { BarAction, Screen, useScreenScroll } from '@/components/screen';
import { useReport, useReportsHub } from '@/hooks/workspace';
import { isDate } from '@/screens/finance/periods';
import { colors, fonts } from '@/theme';
import { date, money } from '@/utils/format';
import { downloadAndShare } from '@/utils/share';

type Row = Record<string, ReportCell>;
type Mode = 'table' | 'cards';

const MIME: Record<ReportFormat, string> = {
  pdf: 'application/pdf',
  csv: 'text/csv',
  xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
};
const FORMAT_LABEL: Record<ReportFormat, { label: string; hint: string }> = {
  xlsx: { label: 'Excel (.xlsx)', hint: 'For your CA or Tally import' },
  csv: { label: 'CSV', hint: 'Plain rows for any spreadsheet' },
  pdf: { label: 'PDF', hint: 'To print or share' },
};

/** One cell as the web shows it: ₹ amounts, "13 Nov 2026" dates, blanks as a dash. */
function cell(col: ReportColumn, v: ReportCell): string {
  if (v === null || v === undefined || v === '') return '—';
  if (typeof v === 'boolean') return v ? 'Yes' : 'No';
  switch (col.type) {
    case 'money':
      return money(Number(v), { paise: true });
    case 'date':
      return date(String(v));
    case 'percent':
      return `${v}%`;
    default:
      return String(v);
  }
}

const numeric = (c: ReportColumn) => ['money', 'qty', 'number', 'percent'].includes(c.type);
const width = (c: ReportColumn, first: boolean) => (first ? 150 : c.type === 'money' ? 116 : c.type === 'date' ? 100 : numeric(c) ? 84 : 140);

/**
 * Any report or register from config/reports.php (GET /reports/{key}): date
 * presets, the rows as a table or as cards, totals over every row, and the
 * web's Excel / CSV / PDF export.
 */
export default function ReportScreen() {
  const params = useLocalSearchParams<{ report: string; from?: string; to?: string }>();
  const key = String(params.report ?? '');
  const hub = useReportsHub();
  const presets = hub.data?.meta.presets ?? [];
  const summary = hub.data?.data.find((r) => r.key === key);

  const [range, setRange] = useState<{ from?: string; to?: string }>(() =>
    isDate(params.from) && isDate(params.to) ? { from: params.from, to: params.to } : {},
  );
  const [mode, setMode] = useState<Mode>('table');
  const [exportOpen, setExportOpen] = useState(false);
  const q = useReport(key, range.from, range.to);
  const r = q.data;
  const usesDates = r?.uses_dates ?? summary?.uses_dates ?? false;
  const title = r?.title ?? summary?.title ?? 'Report';

  // chip for the current range: a preset, "All", or the custom range from the link
  const matched = presets.find((p) => p.from === range.from && p.to === range.to);
  const chipValue = !range.from ? 'all' : matched ? matched.key : 'custom';
  const chips = [
    { value: 'all', label: 'All' },
    ...presets.map((p) => ({ value: p.key, label: p.label })),
    ...(chipValue === 'custom' ? [{ value: 'custom', label: `${date(range.from, true)} – ${date(range.to, true)}` }] : []),
  ];
  const onChip = (v: string) => {
    if (v === 'all') return setRange({});
    const p = presets.find((x) => x.key === v);
    if (p) setRange({ from: p.from, to: p.to });
  };

  const formats = hub.data?.meta.formats ?? (['xlsx', 'csv', 'pdf'] as ReportFormat[]);
  const exportAs = (fmt: ReportFormat) => {
    const name = `${key}${range.from ? `-${range.from}-to-${range.to}` : ''}.${fmt}`;
    return downloadAndShare(reportsApi.exportPath(key, fmt, range), name, MIME[fmt]);
  };

  const notFound = q.error instanceof ApiError && q.error.status === 404;

  return (
    <Screen
      nav="back"
      title={title}
      subtitle={r ? [r.subtitle, `${r.rows.length}${r.truncated ? '+' : ''} row${r.rows.length === 1 ? '' : 's'}`].filter(Boolean).join(' · ') : summary?.group}
      scroll={false}
      right={r && !notFound ? <BarAction icon="download-outline" label="Export report" onPress={() => setExportOpen(true)} /> : undefined}
      collapsible={
        <View style={{ gap: 10 }}>
          {usesDates ? <Chips value={chipValue} options={chips} onChange={onChip} /> : null}
          <Segmented
            value={mode}
            options={[
              { value: 'table', label: 'Table' },
              { value: 'cards', label: 'Cards' },
            ]}
            onChange={setMode}
          />
        </View>
      }
    >
      <Body q={q} mode={mode} notFound={notFound} />
      <ActionSheet
        visible={exportOpen}
        title="Export"
        subtitle={r?.subtitle ?? undefined}
        actions={formats.map((fmt) => ({ key: fmt, label: FORMAT_LABEL[fmt]?.label ?? fmt.toUpperCase(), hint: FORMAT_LABEL[fmt]?.hint, icon: fmt === 'pdf' ? 'document-outline' : 'grid-outline', onPress: () => exportAs(fmt) }))}
        onClose={() => setExportOpen(false)}
      />
    </Screen>
  );
}

function Body({ q, mode, notFound }: { q: ReturnType<typeof useReport>; mode: Mode; notFound: boolean }) {
  const { topInset } = useScreenScroll();
  const r = q.data;
  if (q.isLoading) return <View style={{ flex: 1, paddingTop: topInset }}><Loading /></View>;
  if (q.error || !r) {
    return (
      <View style={{ flex: 1, paddingTop: topInset }}>
        {notFound ? <EmptyState icon="bar-chart-outline" title="Report not found" body="This report is not available. Open Reports to pick another." /> : <ErrorState message={(q.error as Error)?.message ?? 'Could not load this report.'} onRetry={() => q.refetch()} />}
      </View>
    );
  }
  return mode === 'table' ? <Table r={r} refreshing={q.isRefetching} onRefresh={() => q.refetch()} /> : <Cards r={r} refreshing={q.isRefetching} onRefresh={() => q.refetch()} />;
}

function Note({ r }: { r: Report }) {
  if (!r.truncated) return null;
  return (
    <AppText variant="small" color={colors.textMuted} style={{ padding: 12 }}>
      Showing the first {r.limit} rows. Totals cover every row; export for the full report.
    </AppText>
  );
}

function Table({ r, refreshing, onRefresh }: { r: Report; refreshing: boolean; onRefresh: () => void }) {
  const { topInset, onScroll, onScrollEnd } = useScreenScroll();
  const insets = useSafeAreaInsets();
  const { width: screenW } = useWindowDimensions();
  const cols = r.columns;
  const total = cols.reduce((s, c, i) => s + width(c, i === 0), 0) + 24;
  const tableW = Math.max(total, screenW);
  const hasTotals = cols.some((c) => c.total);

  const line = (cells: string[], style: object | null, textStyle?: object) => (
    <View style={[styles.tr, style]}>
      {cols.map((c, i) => (
        <AppText key={c.key} numberOfLines={2} style={[styles.td, { width: width(c, i === 0), textAlign: numeric(c) ? 'right' : 'left' }, textStyle]}>
          {cells[i]}
        </AppText>
      ))}
    </View>
  );

  return (
    <ScrollView horizontal showsHorizontalScrollIndicator contentContainerStyle={{ width: tableW }}>
      <View style={{ width: tableW, flex: 1 }}>
        <FlashList
          data={r.rows}
          keyExtractor={(_, i) => String(i)}
          renderItem={({ item, index }) =>
            line(
              cols.map((c) => cell(c, (item as Row)[c.key])),
              index % 2 ? styles.zebra : null,
            )
          }
          ListHeaderComponent={line(cols.map((c) => c.label), styles.thead, styles.th)}
          ListFooterComponent={
            <View>
              {hasTotals && r.rows.length ? line(cols.map((c, i) => (c.total ? cell(c, r.totals[c.key] ?? 0) : i === 0 ? 'Total' : '')), styles.tfoot, styles.th) : null}
              {/* messages stay within the screen, not the full table width */}
              <View style={{ width: screenW }}>
                {!r.rows.length ? <EmptyState icon="file-tray-outline" title="No rows" body="Nothing in this period. Try another range." /> : null}
                <Note r={r} />
              </View>
            </View>
          }
          contentContainerStyle={{ paddingTop: topInset + 6, paddingBottom: insets.bottom + 32 }}
          onScroll={onScroll}
          onScrollEndDrag={onScrollEnd}
          onMomentumScrollEnd={onScrollEnd}
          scrollEventThrottle={16}
          scrollIndicatorInsets={{ top: topInset }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.teal} progressViewOffset={topInset} />}
          showsVerticalScrollIndicator
        />
      </View>
    </ScrollView>
  );
}

function Cards({ r, refreshing, onRefresh }: { r: Report; refreshing: boolean; onRefresh: () => void }) {
  const { topInset, onScroll, onScrollEnd } = useScreenScroll();
  const insets = useSafeAreaInsets();
  const [first, ...rest] = r.columns;
  // the headline amount: the last totalled column (balance, total, outstanding…)
  const lead = [...r.columns].reverse().find((c) => c.total && c.type === 'money') ?? null;
  const details = rest.filter((c) => c.key !== lead?.key);
  const totalled = r.columns.filter((c) => c.total);

  return (
    <FlashList
      data={r.rows}
      keyExtractor={(_, i) => String(i)}
      renderItem={({ item }) => {
        const row = item as Row;
        return (
          <View style={{ paddingHorizontal: 16, paddingBottom: 8 }}>
            <Card style={{ gap: 6 }}>
              <View style={{ flexDirection: 'row', gap: 10, alignItems: 'baseline' }}>
                <AppText variant="h3" style={{ flex: 1 }} numberOfLines={2}>
                  {first ? cell(first, row[first.key]) : ''}
                </AppText>
                {lead ? <AppText variant="money">{cell(lead, row[lead.key])}</AppText> : null}
              </View>
              {details
                .filter((c) => row[c.key] !== null && row[c.key] !== '' && !(c.type === 'money' && Number(row[c.key]) === 0))
                .map((c) => (
                  <View key={c.key} style={{ flexDirection: 'row', gap: 10 }}>
                    <AppText variant="small" color={colors.textMuted} style={{ width: 120 }}>
                      {c.label}
                    </AppText>
                    <AppText variant="small" color={colors.textBody} style={{ flex: 1, textAlign: numeric(c) ? 'right' : 'left' }}>
                      {cell(c, row[c.key])}
                    </AppText>
                  </View>
                ))}
            </Card>
          </View>
        );
      }}
      ListFooterComponent={
        <View style={{ paddingHorizontal: 16 }}>
          {totalled.length && r.rows.length ? (
            <Card style={{ backgroundColor: colors.tealTint, gap: 6 }}>
              <AppText variant="h3">Total</AppText>
              {totalled.map((c) => (
                <View key={c.key} style={{ flexDirection: 'row', gap: 10 }}>
                  <AppText variant="small" color={colors.textBody} style={{ flex: 1 }}>
                    {c.label}
                  </AppText>
                  <AppText style={{ fontFamily: fonts.bold, fontSize: 13 }}>{cell(c, r.totals[c.key] ?? 0)}</AppText>
                </View>
              ))}
            </Card>
          ) : null}
          <Note r={r} />
        </View>
      }
      ListEmptyComponent={<EmptyState icon="file-tray-outline" title="No rows" body="Nothing in this period. Try another range." />}
      contentContainerStyle={{ paddingTop: topInset + 6, paddingBottom: insets.bottom + 32 }}
      onScroll={onScroll}
      onScrollEndDrag={onScrollEnd}
      onMomentumScrollEnd={onScrollEnd}
      scrollEventThrottle={16}
      scrollIndicatorInsets={{ top: topInset }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.teal} progressViewOffset={topInset} />}
      showsVerticalScrollIndicator
    />
  );
}

const styles = StyleSheet.create({
  tr: { flexDirection: 'row', paddingHorizontal: 12, minHeight: 44, alignItems: 'center', backgroundColor: colors.surface, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.border },
  zebra: { backgroundColor: colors.ground },
  thead: { backgroundColor: colors.slateTint },
  tfoot: { backgroundColor: colors.tealTint, borderTopWidth: 1, borderTopColor: colors.tealLight },
  td: { fontFamily: fonts.medium, fontSize: 13, color: colors.text, paddingHorizontal: 6, paddingVertical: 8, fontVariant: ['tabular-nums'] },
  th: { fontFamily: fonts.bold, fontSize: 12, color: colors.navy },
});
