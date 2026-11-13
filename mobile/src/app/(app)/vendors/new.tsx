import { router, type Href } from 'expo-router';

import { VendorFormScreen } from '@/screens/purchases/vendor-form';

export default function NewVendor() {
  return <VendorFormScreen onSaved={(v) => router.replace(`/vendors/${v.id}` as Href)} />;
}
