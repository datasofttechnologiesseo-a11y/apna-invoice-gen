import { Href, router } from 'expo-router';

import { CustomerForm } from '@/screens/parties/customer-form';

/** New customer — opens their ledger once saved. */
export default function NewCustomer() {
  return <CustomerForm title="New customer" onSaved={(c) => router.replace(`/parties/${c.id}` as Href)} />;
}
