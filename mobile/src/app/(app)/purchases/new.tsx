import { useLocalSearchParams } from 'expo-router';

import { Loading } from '@/components/blocks';
import { Screen } from '@/components/screen';
import { useVendor } from '@/hooks/purchases';
import { PurchaseForm } from '@/screens/purchases/purchase-form';

/** New purchase invoice. ?vendor= pre-selects the vendor (from the vendor page). */
export default function NewPurchase() {
  const { vendor } = useLocalSearchParams<{ vendor?: string }>();
  const vendorId = Number(vendor) || 0;
  const v = useVendor(vendorId);
  if (vendorId && v.isLoading) return <Screen nav="close" title="New purchase invoice"><Loading /></Screen>;
  return <PurchaseForm initialVendor={v.data ?? null} />;
}
