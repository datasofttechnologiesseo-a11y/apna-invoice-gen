/** Finance report queries (P&L, ageing, GSTR-1, GSTR-3B). Keys start with 'finance'; money mutations invalidate it. */
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { financeApi, PnlPeriod, ReturnPeriodParams } from '@/api/finance';

export const financeKeys = {
  all: ['finance'] as const,
  pnl: (period: PnlPeriod) => ['finance', 'pnl', period] as const,
  aging: ['finance', 'aging'] as const,
  gstr3b: (p: ReturnPeriodParams) => ['finance', 'gstr3b', p.month, p.frequency ?? 'monthly'] as const,
  gstr1: (from: string, to: string) => ['finance', 'gstr1', from, to] as const,
};

export function usePnl(period: PnlPeriod) {
  return useQuery({ queryKey: financeKeys.pnl(period), queryFn: () => financeApi.pnl(period), placeholderData: keepPreviousData });
}

export function useAging() {
  return useQuery({ queryKey: financeKeys.aging, queryFn: financeApi.aging });
}

export function useGstr3b(p: ReturnPeriodParams) {
  return useQuery({ queryKey: financeKeys.gstr3b(p), queryFn: () => financeApi.gstr3b(p), placeholderData: keepPreviousData });
}

export function useGstr1(from: string, to: string) {
  return useQuery({ queryKey: financeKeys.gstr1(from, to), queryFn: () => financeApi.gstr1(from, to), placeholderData: keepPreviousData });
}
