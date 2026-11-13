/** Expense queries and mutations. Keys start with 'expenses'. */
import { QueryClient, useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { Expense, ExpenseFilter, ExpenseInput, ExpensePage, expensesApi } from '@/api/expenses';

export const expenseKeys = {
  all: ['expenses'] as const,
  list: (f: ExpenseFilter) => ['expenses', 'list', f.category ?? '', f.from ?? '', f.to ?? '', f.search ?? ''] as const,
  one: (id: number) => ['expenses', 'one', id] as const,
};

export function useExpenses(f: ExpenseFilter) {
  return useInfiniteQuery({
    queryKey: expenseKeys.list(f),
    queryFn: ({ pageParam }) => expensesApi.list({ ...f, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: (last: ExpensePage) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  });
}

/** Category list (value/label/colour) as the server sends it in the list meta. */
export function useExpenseCategories() {
  return useQuery({
    queryKey: ['expenses', 'categories'],
    queryFn: async () => (await expensesApi.list({ per_page: 1 })).meta.categories,
    staleTime: Infinity,
  });
}

/** Show a row from any loaded list at once, then refresh it from GET /expenses/{id}. */
function fromCache(qc: QueryClient, id: number): Expense | undefined {
  for (const [, data] of qc.getQueriesData<{ pages: ExpensePage[] }>({ queryKey: ['expenses', 'list'] })) {
    const hit = data?.pages.flatMap((p) => p.data).find((e) => e.id === id);
    if (hit) return hit;
  }
  return undefined;
}

export function useExpense(id: number) {
  const qc = useQueryClient();
  return useQuery({
    queryKey: expenseKeys.one(id),
    queryFn: async () => (await expensesApi.show(id)).data,
    placeholderData: () => fromCache(qc, id),
    enabled: id > 0,
  });
}

/** Lists, totals, P&L and the dashboard all move when an expense changes. */
function useRefreshExpenses() {
  const qc = useQueryClient();
  return () => {
    qc.invalidateQueries({ queryKey: ['expenses', 'list'] });
    qc.invalidateQueries({ queryKey: ['dashboard'] });
    qc.invalidateQueries({ queryKey: ['finance'] });
  };
}

export function useSaveExpense() {
  const qc = useQueryClient();
  const refresh = useRefreshExpenses();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: ExpenseInput }) =>
      (id ? await expensesApi.update(id, input) : await expensesApi.create(input)).data,
    onSuccess: (saved) => {
      qc.setQueryData(expenseKeys.one(saved.id), saved);
      refresh();
    },
  });
}

export function useDeleteExpense() {
  const refresh = useRefreshExpenses();
  return useMutation({
    mutationFn: (id: number) => expensesApi.remove(id),
    onSuccess: refresh,
  });
}
