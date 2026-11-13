import * as DocumentPicker from 'expo-document-picker';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { useState } from 'react';
import { Alert, View } from 'react-native';

import { ApiError } from '@/api/client';
import { currentMonth, Gstr2bBucket, Gstr2bRow, gstr2bApi, monthLabel, shiftMonth } from '@/api/purchases';
import { AppText } from '@/components/app-text';
import { Card, Chips, ErrorState, Loading, Row } from '@/components/blocks';
import { Button } from '@/components/button';
import { BarAction, Screen } from '@/components/screen';
import { Pill } from '@/components/status-pill';
import { useGstr2b, useUploadGstr2b } from '@/hooks/purchases';
import { MonthSwitcher, Note, StatTile } from '@/screens/purchases/parts';
import { colors } from '@/theme';
import { date, money, moneyShort } from '@/utils/format';
import { downloadAndShare } from '@/utils/share';

const ORDER: Gstr2bBucket[] = ['matched', 'mismatch', 'missing', 'not_in_2b', 'no_gstin', 'notes', 'no_itc'];
const TONE: Record<Gstr2bBucket, 'paid' | 'late' | 'due' | 'info' | 'draft'> = {
  matched: 'paid',
  mismatch: 'late',
  missing: 'late',
  not_in_2b: 'due',
  no_gstin: 'draft',
  notes: 'info',
  no_itc: 'draft',
};
const isMonth = (m?: string) => !!m && /^\d{4}-(0[1-9]|1[0-2])$/.test(m);

/** GSTR-2B match: upload the 2B file from the GST portal and compare it with your purchase invoices. */
export default function Gstr2b() {
  const params = useLocalSearchParams<{ month?: string }>();
  // The 2B for a month is generated on the 14th of the next, so default to last month (as the web does).
  const [month, setMonth] = useState(isMonth(params.month) ? params.month! : currentMonth(-1));
  const [bucket, setBucket] = useState<Gstr2bBucket>('mismatch');
  const q = useGstr2b(month);
  const upload = useUploadGstr2b();
  const [exporting, setExporting] = useState(false);
  const d = q.data;
  const latest = currentMonth();

  const pick = async () => {
    const res = await DocumentPicker.getDocumentAsync({ type: '*/*', copyToCacheDirectory: true, multiple: false });
    if (res.canceled || !res.assets?.[0]) return;
    const a = res.assets[0];
    upload.mutate(
      { file: { uri: a.uri, name: a.name, type: a.mimeType ?? 'application/octet-stream' }, month },
      {
        onSuccess: (r) => {
          if (r.period !== month) setMonth(r.period);
          Alert.alert('GSTR-2B uploaded', r.message);
        },
        onError: (e) => Alert.alert('Could not read the file', (e as ApiError).fieldErrors.file ?? (e as ApiError).message),
      },
    );
  };

  const counts = (k: Gstr2bBucket) => (k === 'no_itc' ? (d?.summary.no_itc.count ?? 0) : (d?.summary.counts[k] ?? d?.buckets[k]?.length ?? 0));
  const shown = d ? ORDER.filter((k) => counts(k) > 0 || k === 'matched' || k === 'mismatch') : [];
  const active = shown.includes(bucket) ? bucket : (shown.find((k) => counts(k) > 0) ?? 'matched');
  const rows = d?.buckets[active] ?? [];

  return (
    <Screen
      nav="back"
      title="GSTR-2B match"
      subtitle="Input credit as your suppliers filed it"
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      right={
        d?.summary.uploaded ? (
          <BarAction
            icon="share-outline"
            label="Export match as CSV"
            onPress={async () => {
              setExporting(true);
              await downloadAndShare(gstr2bApi.exportPath(month), `gstr2b-match-${month}.csv`, 'text/csv');
              setExporting(false);
            }}
          />
        ) : undefined
      }
      headerExtra={<MonthSwitcher label={monthLabel(month)} onPrev={() => setMonth(shiftMonth(month, -1))} onNext={() => setMonth(shiftMonth(month, 1))} canNext={month < latest} />}
      footer={<Button label={d?.summary.uploaded ? 'Upload again' : `Upload 2B for ${monthLabel(month)}`} icon="cloud-upload-outline" loading={upload.isPending} onPress={pick} />}
    >
      {q.isLoading ? (
        <Loading />
      ) : q.error || !d ? (
        <ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} />
      ) : (
        <>
          {exporting ? <AppText variant="small" color={colors.textMuted}>Preparing the CSV…</AppText> : null}
          {!d.summary.uploaded ? (
            <Card>
              <AppText variant="h3">No 2B uploaded for {d.period_label}</AppText>
              <AppText variant="small" color={colors.textBody} style={{ lineHeight: 19 }}>
                On the GST portal, open Returns → Returns Dashboard, pick the month and download GSTR-2B as JSON or Excel. Upload the file here as downloaded. It is matched with your purchase invoices and not sent anywhere.
              </AppText>
              {d.uploaded_periods.length ? (
                <AppText variant="small" color={colors.textMuted}>
                  Uploaded: {d.uploaded_periods.map(monthLabel).join(', ')}
                </AppText>
              ) : null}
            </Card>
          ) : (
            <>
              <View style={{ flexDirection: 'row', gap: 8 }}>
                <StatTile label="ITC in 2B" value={moneyShort(d.summary.itc_in_2b)} full={money(d.summary.itc_in_2b)} />
                <StatTile label="Matched GST" value={moneyShort(d.summary.tax.matched ?? 0)} full={money(d.summary.tax.matched ?? 0)} />
                <StatTile label="Documents" value={String(d.summary.documents)} full={String(d.summary.documents)} />
              </View>
              {d.summary.uploaded_at ? (
                <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
                  Uploaded {date(d.summary.uploaded_at)}
                </AppText>
              ) : null}
            </>
          )}

          {(d.summary.counts.mismatch ?? 0) + (d.summary.counts.missing ?? 0) > 0 ? (
            <Note tone="warn">
              {d.summary.counts.mismatch} bill{d.summary.counts.mismatch === 1 ? '' : 's'} with different amounts and {d.summary.counts.missing} 2B document
              {d.summary.counts.missing === 1 ? '' : 's'} missing in your books. Fix these before claiming credit in GSTR-3B.
            </Note>
          ) : null}

          <Chips value={active} options={shown.map((k) => ({ value: k, label: `${d.labels[k]} · ${counts(k)}` }))} onChange={setBucket} />
          {typeof d.summary.tax[active] === 'number' ? (
            <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
              GST in this group: {money(d.summary.tax[active])}
            </AppText>
          ) : active === 'no_itc' ? (
            <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
              GST in this group: {money(d.summary.no_itc.tax)}
            </AppText>
          ) : null}

          <Card style={{ paddingVertical: 2, gap: 0 }}>
            {rows.length ? (
              rows.map((r, i) => <MatchRow key={`${r.entry?.id ?? 'b'}-${r.bill?.id ?? 'e'}-${i}`} row={r} bucket={active} divider={i < rows.length - 1} />)
            ) : (
              <AppText color={colors.textMuted} style={{ paddingVertical: 16 }}>
                Nothing in this group.
              </AppText>
            )}
          </Card>
        </>
      )}
    </Screen>
  );
}

function MatchRow({ row, bucket, divider }: { row: Gstr2bRow; bucket: Gstr2bBucket; divider: boolean }) {
  const e = row.entry;
  const b = row.bill;
  const name = e?.supplier_name || b?.vendor_name || e?.supplier_gstin || b?.vendor_gstin || 'Supplier';
  const doc = e ? `${e.document_number}${e.document_date ? ` · ${date(e.document_date, true)}` : ''}` : b ? `${b.bill_number}${b.bill_date ? ` · ${date(b.bill_date, true)}` : ''}` : '';
  const books = b && e ? ` · books ${b.bill_number}` : '';
  const tax = e ? e.tax : (b?.total_tax ?? 0);
  const diff = row.tax_diff ?? 0;

  return (
    <Row
      divider={divider}
      onPress={b ? () => router.push(`/purchases/${b.id}` as Href) : bucket === 'missing' ? () => router.push('/purchases/new' as Href) : undefined}
      accessibilityLabel={`${name}, ${doc}, GST ${money(tax)}${diff ? `, 2B minus books ${money(diff)}` : ''}${!b && bucket === 'missing' ? '. Add purchase invoice' : ''}`}
      title={name}
      subtitle={`${doc}${books}${e?.kind && e.kind !== 'invoice' ? ` · ${e.kind}` : ''}`}
      trailing={
        <View style={{ alignItems: 'flex-end', gap: 4 }}>
          <AppText variant="money">{money(tax, { paise: true })}</AppText>
          {bucket === 'mismatch' && diff ? <Pill label={`2B ${diff > 0 ? '+' : ''}${money(diff, { paise: true })}`} tone={TONE[bucket]} /> : null}
          {bucket === 'missing' && !b ? <Pill label="Add bill" tone="due" /> : null}
        </View>
      }
    />
  );
}
