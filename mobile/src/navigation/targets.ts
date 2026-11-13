import type { Href } from 'expo-router';

import type { DashboardTarget } from '@/api/types';

/** Server "targets" (dashboard action items, notifications) → app routes. One map for the whole app. */
export function hrefFor(t: DashboardTarget): Href {
  const p = t.params ?? {};
  const q = (o: Record<string, string | number>) => {
    const s = Object.entries(o)
      .filter(([, v]) => v !== '' && v != null)
      .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`)
      .join('&');
    return s ? `?${s}` : '';
  };
  switch (t.screen) {
    case 'invoice':
      return `/invoices/${p.id}` as Href;
    case 'invoices':
      return `/invoices${q(p)}` as Href;
    case 'quotations':
      return '/quotations';
    case 'customers':
      return '/parties';
    case 'gstr1':
    case 'gstr3b':
    case 'gstr2b':
      return `/gst/${t.screen}${q(p)}` as Href;
    case 'expenses':
      return `/expenses${q(p)}` as Href;
    case 'items':
      return '/items' as Href;
    case 'purchases':
      return `/purchases${q(p)}` as Href;
    case 'team':
      return '/settings/team' as Href;
    case 'settings':
    default:
      return '/settings' as Href;
  }
}
