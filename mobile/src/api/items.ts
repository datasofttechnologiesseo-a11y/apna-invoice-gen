/**
 * Items (products) — mirrors app/Http/Controllers/Api/ProductController.php.
 * Validation (HSN format, units, GST slabs, unique SKU) stays on the server.
 */
import { del, get, post, put, qs } from './client';
import type { Paginated, Product } from './types';

/** List filter. "archived" = only_inactive=1 (items used on documents are archived, not deleted). */
export type ItemFilter = 'all' | 'goods' | 'service' | 'low' | 'archived';

export type ItemInput = {
  name: string;
  sku: string | null;
  kind: 'goods' | 'service';
  hsn_sac: string | null;
  unit: string;
  rate: number;
  gst_rate: number;
  description: string | null;
  is_active?: boolean;
  track_stock?: boolean;
  opening_stock?: number | null;
  low_stock_level?: number | null;
  purchase_rate?: number | null;
};

export type ItemDetail = Product & { kind_label?: string };

/** UQC units (config/uqc_units.php; also served at GET /api/units). */
export const UNITS: { code: string; label: string }[] = [
  { code: 'NOS', label: 'Numbers / Count' },
  { code: 'PCS', label: 'Pieces' },
  { code: 'BOX', label: 'Box' },
  { code: 'BAG', label: 'Bags' },
  { code: 'BAL', label: 'Bale' },
  { code: 'BTL', label: 'Bottles' },
  { code: 'BDL', label: 'Bundles' },
  { code: 'BKL', label: 'Buckles' },
  { code: 'BOU', label: 'Billion units' },
  { code: 'BUN', label: 'Bunches' },
  { code: 'CAN', label: 'Cans' },
  { code: 'CBM', label: 'Cubic metre' },
  { code: 'CCM', label: 'Cubic centimetre' },
  { code: 'CMS', label: 'Centimetres' },
  { code: 'CTN', label: 'Cartons' },
  { code: 'DOZ', label: 'Dozen' },
  { code: 'DRM', label: 'Drums' },
  { code: 'GMS', label: 'Grams' },
  { code: 'GGK', label: 'Great Gross' },
  { code: 'GRS', label: 'Gross' },
  { code: 'GYD', label: 'Gross yards' },
  { code: 'KGS', label: 'Kilograms' },
  { code: 'KLR', label: 'Kilolitre' },
  { code: 'KME', label: 'Kilometre' },
  { code: 'LTR', label: 'Litres' },
  { code: 'MLT', label: 'Millilitre' },
  { code: 'MTR', label: 'Metres' },
  { code: 'MTS', label: 'Metric tons' },
  { code: 'PAC', label: 'Packs' },
  { code: 'PRS', label: 'Pairs' },
  { code: 'QTL', label: 'Quintal' },
  { code: 'ROL', label: 'Rolls' },
  { code: 'SET', label: 'Sets' },
  { code: 'SQF', label: 'Square feet' },
  { code: 'SQM', label: 'Square metres' },
  { code: 'SQY', label: 'Square yards' },
  { code: 'TBS', label: 'Tablets' },
  { code: 'TGM', label: 'Ten gross' },
  { code: 'THD', label: 'Thousands' },
  { code: 'TON', label: 'Tonnes' },
  { code: 'TUB', label: 'Tubes' },
  { code: 'UGS', label: 'US gallons' },
  { code: 'UNT', label: 'Units' },
  { code: 'YDS', label: 'Yards' },
  { code: 'OTH', label: 'Others' },
];

export const itemsApi = {
  list: (p: { page?: number; filter: ItemFilter; search?: string }) =>
    get<Paginated<ItemDetail>>(
      `/products${qs({
        page: p.page,
        per_page: 30,
        search: p.search,
        kind: p.filter === 'goods' || p.filter === 'service' ? p.filter : undefined,
        only_inactive: p.filter === 'archived' ? 1 : undefined,
        stock: p.filter === 'low' ? 'low' : undefined,
      })}`,
    ),
  show: (id: number) => get<{ data: ItemDetail }>(`/products/${id}`),
  create: (input: ItemInput) => post<{ data: ItemDetail }>('/products', input),
  update: (id: number, input: ItemInput) => put<{ data: ItemDetail }>(`/products/${id}`, input),
  /** An item already used on documents is archived instead (archived: true). */
  remove: (id: number) => del<{ message: string; archived: boolean }>(`/products/${id}`),
};

/** The editable fields of a saved item, ready to send back with changes. */
export function itemInputOf(p: ItemDetail): ItemInput {
  return {
    name: p.name,
    sku: p.sku,
    kind: p.kind,
    hsn_sac: p.hsn_sac || null,
    unit: p.unit,
    rate: Number(p.rate),
    gst_rate: Number(p.gst_rate),
    description: p.description,
    track_stock: p.track_stock,
    opening_stock: p.opening_stock ?? null,
    low_stock_level: p.low_stock_level,
    purchase_rate: p.purchase_rate ?? null,
  };
}
