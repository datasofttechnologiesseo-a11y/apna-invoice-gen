import { router, type Href } from 'expo-router';
import { View } from 'react-native';

import type { PurchaseListItem } from '@/api/purchases';
import { AppText } from '@/components/app-text';
import { Row } from '@/components/blocks';
import { colors } from '@/theme';
import { date, money } from '@/utils/format';

import { PurchasePill } from './parts';

/** One purchase invoice in a list: vendor, number and date, balance (or total once paid). */
export function PurchaseRow({ bill, divider = false, showVendor = true }: { bill: PurchaseListItem; divider?: boolean; showVendor?: boolean }) {
  const open = bill.balance > 0;
  return (
    <Row
      divider={divider}
      onPress={() => router.push(`/purchases/${bill.id}` as Href)}
      accessibilityLabel={`${bill.vendor_name ?? 'Vendor'}, bill ${bill.bill_number}, ${open ? `${money(bill.balance)} to pay` : `${money(bill.grand_total)} paid`}, ${bill.status_label}`}
      title={showVendor ? (bill.vendor_name ?? 'Vendor') : bill.bill_number}
      subtitle={`${showVendor ? `${bill.bill_number} · ` : ''}${date(bill.bill_date, true)}${open && bill.due_date ? ` · due ${date(bill.due_date, true)}` : ''}`}
      trailing={
        <View style={{ alignItems: 'flex-end', gap: 4 }}>
          <AppText variant="money">{money(open ? bill.balance : bill.grand_total)}</AppText>
          <PurchasePill bill={bill} />
        </View>
      }
    />
  );
}

export function rowCard() {
  return { backgroundColor: colors.surface, borderRadius: 18, paddingHorizontal: 14 } as const;
}
