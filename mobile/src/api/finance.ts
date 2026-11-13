/** Finance reports — Api\FinanceController (P&L, ageing, GSTR-1, GSTR-3B) and their CSV/PDF exports. */
import { get, qs } from './client';

export type Period = { start: string; end: string; label: string; key: string };

export type PnlCategory = { category: string; label: string; color: string; total: number; count: number; share: number };

export type Pnl = {
  period: Period;
  revenue: { taxable: number; gst_collected: number; grand_total: number; received: number; outstanding: number };
  expense: { taxable: number; gst_itc: number; cash_out: number };
  net_profit: number;
  margin: number;
  cash_in_hand: number;
  gst_payable: number;
  by_category: PnlCategory[];
  trend: { label: string; ym: string; revenue: number; expenses: number }[];
};

export type PnlPeriod = 'this_month' | 'last_month' | 'this_quarter' | 'this_fy' | 'last_fy' | 'ytd';

export type AgingBuckets = { total: number; current: number; b30_60: number; b60_90: number; b90_plus: number };

export type Aging = {
  as_on: string;
  summary: AgingBuckets & { customers: number; invoices: number };
  by_customer: (AgingBuckets & { name: string; gstin: string | null; invoice_count: number; oldest_days: number })[];
};

type Heads = { igst: number; cgst: number; sgst: number };
type HeadsTotal = Heads & { total: number };

export type Gstr3b = {
  outward: Heads & { taxable: number };
  outward_nil: number;
  outward_zero: { taxable: number; igst: number };
  rcm_inward: Heads & { taxable: number };
  purchase_bill_count: number;
  itc: HeadsTotal;
  itc_rcm: Heads;
  itc_other: Heads;
  itc_used: Heads;
  net_cash: HeadsTotal;
  itc_carried: HeadsTotal;
  negative_liability_in: Heads;
  negative_liability_out: Heads;
  itc_reversed: HeadsTotal;
  itc_reclaimed: HeadsTotal;
  itc_time_barred: HeadsTotal & { count: number };
  invoice_count: number;
  credit_note_count: number;
  expense_count: number;
  cash_memo_count: number;
  period: { start: string; end: string; label: string; month: string; frequency: 'monthly' | 'quarterly'; due_date: string };
};

export type Gstr1Summary = { count: number; taxable: number; cgst: number; sgst: number; igst: number; total: number };

export type Gstr1Table = { table: string; label: string; records: number; taxable: number; igst: number; cgst: number; sgst: number };

export type Gstr1Hsn = {
  key: string;
  tab: 'B2B' | 'B2C' | string;
  hsn: string;
  description: string;
  uqc: string;
  qty: number;
  value: number;
  rate: number;
  taxable: number;
  igst: number;
  cgst: number;
  sgst: number;
  cess: number;
};

export type Gstr1 = {
  period: { from: string; to: string };
  b2b: Gstr1Summary;
  b2c: Gstr1Summary;
  all: Gstr1Summary;
  tables: Record<string, Gstr1Table>;
  hsn: Gstr1Hsn[];
  gstr3b_outward: Heads & { taxable: number; nil: number; rcm_taxable: number; zero_taxable: number; zero_igst: number; invoices: number; credit_notes: number };
};

/** A GST return period as the server reads it (App\Support\ReturnPeriod): ?month=YYYY-MM[&frequency=quarterly]. */
export type ReturnPeriodParams = { month: string; frequency?: 'monthly' | 'quarterly' };

const rp = (p: ReturnPeriodParams) => qs({ month: p.month, frequency: p.frequency === 'quarterly' ? 'quarterly' : undefined });

export const financeApi = {
  pnl: (period: PnlPeriod) => get<Pnl>(`/finance/pnl${qs({ period })}`),
  aging: () => get<Aging>('/finance/aging'),
  agingCsvPath: '/finance/aging/export/csv',
  agingPdfPath: '/finance/aging/export/pdf',
  gstr3b: (p: ReturnPeriodParams) => get<Gstr3b>(`/finance/gstr3b${rp(p)}`),
  gstr3bCsvPath: (p: ReturnPeriodParams) => `/finance/gstr3b/export/csv${rp(p)}`,
  gstr3bPdfPath: (p: ReturnPeriodParams) => `/finance/gstr3b/export/pdf${rp(p)}`,
  gstr1: (from: string, to: string) => get<Gstr1>(`/finance/gstr1${qs({ from, to })}`),
  gstr1CsvPath: (from: string, to: string) => `/finance/gstr1/export/csv${qs({ from, to })}`,
  /** Portal JSON / offline-tool workbook take the return period, not dates. */
  gstr1FilePath: (kind: 'json' | 'xlsx', periodQs: string) => `/finance/gstr1/export/${kind}?${periodQs}`,
};
