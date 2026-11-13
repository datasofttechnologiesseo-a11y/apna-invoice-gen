import { Href, router } from 'expo-router';

import { QuoteForm } from '@/screens/quotations/quote-form';

/** New quotation (from the + sheet or the Quotations empty state). */
export default function NewQuotation() {
  return <QuoteForm onSaved={(q) => router.replace(`/quotations/${q.id}` as Href)} />;
}
