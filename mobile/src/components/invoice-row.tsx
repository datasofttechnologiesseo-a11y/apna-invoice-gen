import { router } from 'expo-router';
import { View } from 'react-native';

import type { InvoiceListItem } from '@/api/types';
import { date, money } from '@/utils/format';

import { AppText } from './app-text';
import { Avatar, Row } from './blocks';
import { InvoicePill } from './status-pill';

/** One invoice line — used by Home, the invoice list and search. */
export function InvoiceRow({ inv, divider = true }: { inv: InvoiceListItem; divider?: boolean }) {
  const due = inv.status !== 'draft' && inv.status !== 'paid' && inv.due_date ? ` · due ${date(inv.due_date, true)}` : '';
  return (
    <Row
      divider={divider}
      onPress={() => router.push(`/invoices/${inv.id}`)}
      accessibilityLabel={`${inv.customer_name}, ${inv.display_number}, ${money(inv.grand_total)}`}
      leading={<Avatar name={inv.customer_name} />}
      title={inv.customer_name}
      subtitle={`${inv.display_number} · ${date(inv.invoice_date, true)}${due}`}
      trailing={
        <View style={{ alignItems: 'flex-end', gap: 5 }}>
          <AppText variant="money">{money(inv.grand_total)}</AppText>
          <InvoicePill status={inv.status} dueDate={inv.due_date} balance={inv.effective_balance} />
        </View>
      }
    />
  );
}
