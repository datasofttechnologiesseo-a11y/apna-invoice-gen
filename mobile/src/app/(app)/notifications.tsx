import { Href, router } from 'expo-router';
import { useEffect, useRef } from 'react';

import { AppText } from '@/components/app-text';
import { Card, EmptyState, ErrorState, IconTile, Loading, SectionTitle, Row } from '@/components/blocks';
import { Screen } from '@/components/screen';
import { useMarkNotificationsSeen, useNotifications } from '@/hooks/workspace';
import { colors } from '@/theme';
import { date, money } from '@/utils/format';

const METHOD: Record<string, string> = { upi: 'UPI', cash: 'Cash', neft: 'NEFT', imps: 'IMPS', rtgs: 'RTGS', cheque: 'Cheque', card: 'Card', other: 'Other' };

/** The web's notification bell (NotificationFeed): overdue invoices, payments in the last 7 days, invoices issued in the last 24 hours. */
export default function Notifications() {
  const q = useNotifications();
  const seen = useMarkNotificationsSeen();
  const marked = useRef(false);
  const f = q.data;

  // Opening the list clears the "new activity" dot (overdue stays until paid).
  useEffect(() => {
    if (f?.has_unseen && !marked.current) {
      marked.current = true;
      seen.mutate();
    }
  }, [f?.has_unseen, seen]);

  const open = (id: number) => router.push(`/invoices/${id}` as Href);
  const empty = f && !f.overdue.length && !f.recent_payments.length && !f.recent_invoices.length;

  return (
    <Screen nav="back" title="Notifications" subtitle={f?.overdue_count ? `${f.overdue_count} overdue` : undefined} onRefresh={() => q.refetch()} refreshing={q.isRefetching}>
      {q.isLoading ? (
        <Loading />
      ) : q.error || !f ? (
        <ErrorState message={(q.error as Error)?.message ?? 'Could not load notifications.'} onRetry={() => q.refetch()} />
      ) : empty ? (
        <EmptyState icon="notifications-outline" title="All caught up" body="Overdue invoices, payments received and newly issued invoices show up here." />
      ) : (
        <>
          {f.overdue.length ? (
            <>
              <SectionTitle title={`Overdue · ${f.overdue_count}`} />
              <Card style={{ paddingVertical: 2 }}>
                {f.overdue.map((o, i) => (
                  <Row
                    key={`o${o.invoice_id}`}
                    divider={i < f.overdue.length - 1}
                    onPress={() => open(o.invoice_id)}
                    leading={<IconTile icon="alarm-outline" bg={colors.redTint} fg={colors.redText} size={36} />}
                    title={`${o.invoice_number ?? 'Invoice'} · ${o.customer_name ?? ''}`}
                    subtitle={o.days_overdue != null ? `${o.days_overdue} day${o.days_overdue === 1 ? '' : 's'} overdue · due ${date(o.due_date)}` : `Due ${date(o.due_date)}`}
                    trailing={
                      <AppText variant="money" color={colors.redText}>
                        {money(o.balance)}
                      </AppText>
                    }
                  />
                ))}
              </Card>
              {f.overdue_count > f.overdue.length ? (
                <AppText variant="small" color={colors.textMuted} style={{ paddingHorizontal: 4 }}>
                  Showing {f.overdue.length} of {f.overdue_count}. The Invoices tab lists them all.
                </AppText>
              ) : null}
            </>
          ) : null}

          {f.recent_payments.length ? (
            <>
              <SectionTitle title="Payments received" />
              <Card style={{ paddingVertical: 2 }}>
                {f.recent_payments.map((p, i) => (
                  <Row
                    key={`p${p.payment_id}`}
                    divider={i < f.recent_payments.length - 1}
                    onPress={() => open(p.invoice_id)}
                    leading={<IconTile icon="checkmark" bg={colors.greenTint} fg={colors.greenText} size={36} />}
                    title={`${money(p.amount)} received`}
                    subtitle={`${p.invoice_number ?? 'Invoice'} · ${METHOD[p.method] ?? p.method} · ${date(p.received_at)}`}
                  />
                ))}
              </Card>
            </>
          ) : null}

          {f.recent_invoices.length ? (
            <>
              <SectionTitle title="Recently issued" />
              <Card style={{ paddingVertical: 2 }}>
                {f.recent_invoices.map((r, i) => (
                  <Row
                    key={`i${r.invoice_id}`}
                    divider={i < f.recent_invoices.length - 1}
                    onPress={() => open(r.invoice_id)}
                    leading={<IconTile icon="document-text-outline" size={36} />}
                    title={`${r.invoice_number ?? 'Invoice'} · ${r.customer_name ?? ''}`}
                    subtitle={r.finalized_at ? `Issued ${date(r.finalized_at)}` : 'Issued'}
                    trailing={<AppText variant="money">{money(r.grand_total)}</AppText>}
                  />
                ))}
              </Card>
            </>
          ) : null}
        </>
      )}
    </Screen>
  );
}
