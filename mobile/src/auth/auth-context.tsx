import { useQueryClient } from '@tanstack/react-query';
import * as SecureStore from 'expo-secure-store';
import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import { setUnauthorizedHandler, tokenStore } from '@/api/client';
import { authApi } from '@/api/endpoints';
import type { ActiveCompany, AuthResponse, User } from '@/api/types';

type Status = 'loading' | 'signedOut' | 'signedIn';

type AuthState = {
  status: Status;
  user: User | null;
  company: ActiveCompany | null;
  seenWelcome: boolean;
  markWelcomeSeen: () => void;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (input: Parameters<typeof authApi.register>[0]) => Promise<void>;
  signOut: () => Promise<void>;
  refreshMe: () => Promise<void>;
  /** After account deletion: forget everything locally without calling logout. */
  forget: () => Promise<void>;
};

const Ctx = createContext<AuthState | null>(null);
const WELCOME_KEY = 'apna.welcome';

export function AuthProvider({ children }: { children: ReactNode }) {
  const qc = useQueryClient();
  const [status, setStatus] = useState<Status>('loading');
  const [user, setUser] = useState<User | null>(null);
  const [company, setCompany] = useState<ActiveCompany | null>(null);
  const [seenWelcome, setSeenWelcome] = useState(true);

  const forget = useCallback(async () => {
    await tokenStore.clear();
    qc.clear();
    setUser(null);
    setCompany(null);
    setStatus('signedOut');
  }, [qc]);

  useEffect(() => {
    setUnauthorizedHandler(() => {
      forget();
    });
    (async () => {
      setSeenWelcome((await SecureStore.getItemAsync(WELCOME_KEY)) === '1');
      const token = await tokenStore.load();
      if (!token) return setStatus('signedOut');
      try {
        const me = await authApi.me();
        setUser(me.user);
        setCompany(me.active_company);
        setStatus('signedIn');
      } catch {
        await forget();
      }
    })();
  }, [forget]);

  const accept = useCallback(async (res: AuthResponse) => {
    await tokenStore.save(res.token);
    await SecureStore.setItemAsync(WELCOME_KEY, '1');
    setSeenWelcome(true);
    setUser(res.user);
    setCompany(res.active_company);
    setStatus('signedIn');
  }, []);

  const value = useMemo<AuthState>(
    () => ({
      status,
      user,
      company,
      seenWelcome,
      markWelcomeSeen: () => {
        setSeenWelcome(true);
        SecureStore.setItemAsync(WELCOME_KEY, '1').catch(() => {});
      },
      signIn: async (email, password) => accept(await authApi.login(email.trim().toLowerCase(), password)),
      signUp: async (input) => accept(await authApi.register(input)),
      signOut: async () => {
        await authApi.logout().catch(() => {});
        await forget();
      },
      refreshMe: async () => {
        const me = await authApi.me();
        setUser(me.user);
        setCompany(me.active_company);
      },
      forget,
    }),
    [status, user, company, seenWelcome, accept, forget],
  );

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useAuth() {
  const v = useContext(Ctx);
  if (!v) throw new Error('useAuth outside AuthProvider');
  return v;
}
