import { Href, router } from 'expo-router';

import { ItemForm } from '@/screens/items/item-form';

/** New item (from the + sheet or the Items empty state). */
export default function NewItem() {
  return <ItemForm title="New item" subtitle="Goods or a service you bill" onSaved={(item) => router.replace(`/items/${item.id}` as Href)} />;
}
