import { Href, router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Alert, View } from 'react-native';

import { ApiError } from '@/api/client';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, KeyValue, Loading } from '@/components/blocks';
import { Button } from '@/components/button';
import { documentsApi } from '@/api/workspace';
import { BarAction, Screen } from '@/components/screen';
import { downloadAndShare , openWhatsApp } from '@/utils/share';
import { QuotePill } from '@/components/status-pill';
import { TextField } from '@/components/text-field';
import { useQuotation, useQuotationAction } from '@/hooks/queries';
import { colors, fonts } from '@/theme';
import { date, money } from '@/utils/format';

export default function QuotationDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const q = useQuotation(Number(id));
  const act = useQuotationAction(Number(id));
  const [declining, setDeclining] = useState(false);
  const [reason, setReason] = useState('');
  const qt = q.data;

  if (q.isLoading) return <Screen hero nav="back" title="Quotation"><Loading /></Screen>;
  if (q.error || !qt) return <Screen hero nav="back" title="Quotation"><ErrorState message={(q.error as Error)?.message ?? 'Not found'} onRetry={() => q.refetch()} /></Screen>;

  const fail = (e: unknown) => Alert.alert('Could not finish', (e as ApiError).message);

  return (
    <Screen
      hero
      nav="back"
      title={qt.display_number}
      subtitle={qt.subject ?? 'Quotation'}
      right={
        <>
          {qt.can.edit ? <BarAction hero icon="create-outline" label="Edit quotation" onPress={() => router.push(`/quotations/${qt.id}/edit` as Href)} /> : null}
          <BarAction
            hero
            icon="document-outline"
            label="Share quotation PDF"
            onPress={() => downloadAndShare(documentsApi.quotationPdfPath(qt.id), `${qt.display_number.replace(/[^A-Za-z0-9._-]+/g, '-')}.pdf`).catch((e) => Alert.alert('Could not share', (e as Error).message))}
          />
        </>
      }
      onRefresh={() => q.refetch()}
      refreshing={q.isRefetching}
      headerExtra={
        <View style={{ gap: 6 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <AppText color={colors.onDarkMuted} style={{ fontSize: 14 }}>{qt.customer.name}</AppText>
            <QuotePill status={qt.effective_status} />
          </View>
          <AppText variant="hero" color={colors.onDark}>{money(qt.grand_total)}</AppText>
          <AppText variant="small" color={colors.onDarkMuted}>
            {date(qt.quote_date)}{qt.valid_until ? ` · valid till ${date(qt.valid_until)}` : ''}{qt.reference ? ` · ref ${qt.reference}` : ''}
          </AppText>
        </View>
      }
    >
      <View style={{ gap: 10 }}>
        {qt.can.send ? <Button label="Mark as sent" icon="paper-plane-outline" loading={act.isPending} onPress={() => act.mutate({ type: 'send' }, { onError: fail })} /> : null}
        {qt.can.convert ? (
          <Button
            label="Convert to invoice"
            icon="swap-horizontal"
            kind="accent"
            loading={act.isPending}
            onPress={() => act.mutate({ type: 'convert' }, { onSuccess: (r) => { const invId = r?.data?.converted_to_invoice_id ?? r?.data?.id; if (invId) router.replace(`/invoices/${invId}`); }, onError: fail })}
          />
        ) : null}
        <View style={{ flexDirection: 'row', gap: 10 }}>
          {qt.can.accept ? <Button label="Accepted" icon="checkmark" kind="soft" size="md" style={{ flex: 1 }} onPress={() => act.mutate({ type: 'accept' }, { onError: fail })} /> : null}
          {qt.can.decline ? <Button label="Declined" icon="close" kind="danger" size="md" style={{ flex: 1 }} onPress={() => setDeclining(true)} /> : null}
        </View>
        {qt.whatsapp_link ? <Button label="Share on WhatsApp" icon="logo-whatsapp" kind="outline" size="md" onPress={() => openWhatsApp(qt.whatsapp_link, `${qt.display_number} for ${money(qt.grand_total)}`)} /> : null}
        {qt.converted_to_invoice_id ? <Button label="Open the invoice" icon="document-text-outline" kind="soft" size="md" onPress={() => router.push(`/invoices/${qt.converted_to_invoice_id}`)} /> : null}
      </View>

      {declining ? (
        <Card>
          <TextField label="Why did they decline? (optional)" value={reason} onChangeText={setReason} multiline />
          <Button label="Mark declined" kind="danger" loading={act.isPending} onPress={() => act.mutate({ type: 'decline', reason }, { onSuccess: () => setDeclining(false), onError: fail })} />
        </Card>
      ) : null}

      <Card>
        {qt.items.map((it) => (
          <View key={it.id} style={{ flexDirection: 'row', gap: 10 }}>
            <View style={{ flex: 1 }}>
              <AppText style={{ fontFamily: fonts.medium, fontSize: 14 }}>{it.description}</AppText>
              <AppText variant="small" color={colors.textMuted}>{it.quantity} {it.unit ?? ''} × {money(it.rate)} · GST {it.gst_rate}%</AppText>
            </View>
            <AppText style={{ fontFamily: fonts.medium, fontSize: 14 }}>{money(it.amount)}</AppText>
          </View>
        ))}
        <View style={{ height: 1, backgroundColor: colors.divider }} />
        <KeyValue label="Taxable value" value={money(qt.subtotal, { paise: true })} />
        <KeyValue label="GST" value={money(qt.total_tax, { paise: true })} />
        <KeyValue label="Total" value={money(qt.grand_total)} strong />
      </Card>
      {qt.terms ? <Card><AppText variant="small" color={colors.textBody}>Terms: {qt.terms}</AppText>{qt.delivery_period ? <AppText variant="small" color={colors.textBody}>Delivery: {qt.delivery_period}</AppText> : null}</Card> : null}
      {qt.decline_reason ? <Card style={{ backgroundColor: colors.redTint }}><AppText variant="small" color={colors.redText}>Declined: {qt.decline_reason}</AppText></Card> : null}
    </Screen>
  );
}
