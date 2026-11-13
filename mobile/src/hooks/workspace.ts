import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { companyApi, CompanyInput, documentsApi, IssuedInvoiceEdit, notificationsApi, PickedFile, reportsApi, searchApi, SearchScope, teamApi } from '@/api/workspace';
import { useAuth } from '@/auth/auth-context';
import { keys } from '@/hooks/queries';

export const wsKeys = {
  team: ['team'] as const,
  notifications: ['notifications'] as const,
  search: (q: string, scope: SearchScope) => ['search', scope, q] as const,
  reportsHub: ['reports', 'hub'] as const,
  report: (key: string, from?: string, to?: string) => ['reports', 'show', key, from ?? '', to ?? ''] as const,
  company: ['company', 'active'] as const,
  companies: ['company', 'list'] as const,
  templates: ['invoice-templates'] as const,
};

/* ── Team ── */

export function useTeam(enabled = true) {
  return useQuery({ queryKey: wsKeys.team, queryFn: teamApi.list, enabled });
}

export function useTeamAction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (a: { type: 'invite'; email: string; role: string } | { type: 'role'; id: number; role: string } | { type: 'remove'; id: number }) => {
      if (a.type === 'invite') return (await teamApi.invite(a.email, a.role)).meta.message;
      if (a.type === 'role') return (await teamApi.changeRole(a.id, a.role)).message;
      return (await teamApi.remove(a.id)).message;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: wsKeys.team });
      qc.invalidateQueries({ queryKey: keys.dashboard });
    },
  });
}

export function useResendInvite() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => teamApi.resend(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: wsKeys.team }),
  });
}

/** Leave a business this user joined as a team member; their own business becomes active. */
export function useLeaveBusiness() {
  const qc = useQueryClient();
  const { refreshMe } = useAuth();
  return useMutation({
    mutationFn: (memberId: number) => teamApi.leave(memberId),
    onSuccess: async () => {
      await refreshMe().catch(() => {});
      await qc.resetQueries();
    },
  });
}

/* ── Notifications ── */

export function useNotifications() {
  return useQuery({ queryKey: wsKeys.notifications, queryFn: notificationsApi.feed, refetchOnWindowFocus: true });
}

export function useMarkNotificationsSeen() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: notificationsApi.seen,
    onSuccess: () => {
      qc.setQueryData(wsKeys.notifications, (old: Awaited<ReturnType<typeof notificationsApi.feed>> | undefined) => (old ? { ...old, has_unseen: false } : old));
      qc.invalidateQueries({ queryKey: keys.dashboard });
    },
  });
}

/* ── Search ── */

export function useSearch(q: string, scope: SearchScope) {
  return useQuery({
    queryKey: wsKeys.search(q, scope),
    queryFn: () => searchApi.suggest(q, scope),
    enabled: q.length > 0,
    placeholderData: keepPreviousData,
    staleTime: 30_000,
  });
}

/* ── Reports ── */

export function useReportsHub() {
  return useQuery({ queryKey: wsKeys.reportsHub, queryFn: reportsApi.hub, staleTime: 10 * 60_000 });
}

export function useReport(key: string, from?: string, to?: string) {
  return useQuery({ queryKey: wsKeys.report(key, from, to), queryFn: () => reportsApi.show(key, { from, to }), enabled: !!key, placeholderData: keepPreviousData });
}

/* ── Documents ── */

export function useInvoiceTemplates() {
  return useQuery({ queryKey: wsKeys.templates, queryFn: documentsApi.templates, staleTime: Infinity });
}

/** Copy an invoice into a new editable draft. */
export function useDuplicateInvoice() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (invoiceId: number) => (await documentsApi.duplicate(invoiceId)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: keys.invoices });
      qc.invalidateQueries({ queryKey: keys.dashboard });
    },
  });
}

/** Save the soft fields of an issued invoice. */
export function useEditIssuedInvoice(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: IssuedInvoiceEdit) => (await documentsApi.editIssued(id, input)).data,
    onSuccess: (inv) => {
      qc.setQueryData(keys.invoice(id), inv);
      qc.invalidateQueries({ queryKey: keys.invoice(id) });
      qc.invalidateQueries({ queryKey: keys.invoices });
      qc.invalidateQueries({ queryKey: keys.dashboard });
      qc.invalidateQueries({ queryKey: ['ledger'] });
    },
  });
}

/* ── Company ── */

export function useActiveCompany() {
  return useQuery({ queryKey: wsKeys.company, queryFn: async () => (await companyApi.active()).data });
}

export function useCompanies() {
  return useQuery({ queryKey: wsKeys.companies, queryFn: companyApi.list });
}

/** After any company change: refetch the settings, the company list and the auth context's company. */
function useCompanyRefresh() {
  const qc = useQueryClient();
  const { refreshMe } = useAuth();
  return async () => {
    qc.invalidateQueries({ queryKey: ['company'] });
    qc.invalidateQueries({ queryKey: keys.dashboard });
    qc.invalidateQueries({ queryKey: ['me'] });
    await refreshMe().catch(() => {});
  };
}

export function useSaveCompany() {
  const refresh = useCompanyRefresh();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: CompanyInput }) => companyApi.update(id, input),
    onSuccess: async (res) => {
      qc.setQueryData(wsKeys.company, res.data);
      await refresh();
    },
  });
}

export function useBranding() {
  const refresh = useCompanyRefresh();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (a: { id: number; field: 'logo' | 'signature'; file: PickedFile | null }) =>
      (a.file ? await companyApi.uploadImage(a.id, a.field, a.file) : await companyApi.removeImage(a.id, a.field)).data,
    onSuccess: async (c) => {
      qc.setQueryData(wsKeys.company, c);
      await refresh();
    },
  });
}

/** Open another business: every cached list belongs to the old one, so the cache is cleared. */
export function useSwitchCompany() {
  const qc = useQueryClient();
  const { refreshMe } = useAuth();
  return useMutation({
    mutationFn: (id: number) => companyApi.switchTo(id),
    onSuccess: async () => {
      await refreshMe().catch(() => {});
      await qc.resetQueries();
    },
  });
}
