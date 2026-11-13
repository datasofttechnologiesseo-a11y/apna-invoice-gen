import { useQuery } from '@tanstack/react-query';

import { authApi } from '@/api/endpoints';
import type { Can } from '@/api/types';

const useMe = () => useQuery({ queryKey: ['me'], queryFn: authApi.me, staleTime: 5 * 60_000 });

/**
 * The signed-in user's team role in the active business ('owner' for their own).
 * Used only to hide actions the API would refuse (CompanyMember::RULES);
 * the server still decides and a 403 message is shown if it refuses.
 */
export function useRole(): string | null {
  return useMe().data?.role ?? null;
}

/** Which app areas the role opens (from /me). Unknown = allowed; the server has the last word. */
export function useCan(): Required<Can> & Record<string, boolean> {
  const can = useMe().data?.can ?? {};
  return new Proxy(can, { get: (t, k: string) => (t as Record<string, boolean>)[k] ?? true }) as Required<Can> & Record<string, boolean>;
}

/** Staff cannot delete items, customers or quotations (CompanyMember::RULES "staff"). */
export const canDelete = (role: string | null) => role !== 'staff';
