import React, { createContext, useContext, useEffect, useMemo, useState, useCallback } from 'react';
import { loadToken, setToken, setUnauthorizedHandler } from '../api/client';
import * as endpoints from '../api/endpoints';
import type { ActiveCompanySummary, AuthUser } from '../api/types';

interface AuthState {
  initializing: boolean;
  user: AuthUser | null;
  company: ActiveCompanySummary | null;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (input: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => Promise<void>;
  signOut: () => Promise<void>;
  refresh: () => Promise<void>;
}

const AuthContext = createContext<AuthState | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [initializing, setInitializing] = useState(true);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [company, setCompany] = useState<ActiveCompanySummary | null>(null);

  const signOut = useCallback(async () => {
    try {
      if (await loadToken()) await endpoints.logout();
    } catch {
      // Ignore network errors on logout — we clear locally regardless.
    }
    await setToken(null);
    setUser(null);
    setCompany(null);
  }, []);

  // Wire the 401 handler so an expired token anywhere drops us to the login screen.
  useEffect(() => {
    setUnauthorizedHandler(() => {
      void setToken(null);
      setUser(null);
      setCompany(null);
    });
    return () => setUnauthorizedHandler(null);
  }, []);

  // On boot: if we have a stored token, validate it via /me.
  useEffect(() => {
    (async () => {
      try {
        const token = await loadToken();
        if (token) {
          const res = await endpoints.me();
          setUser(res.user);
          setCompany(res.active_company);
        }
      } catch {
        await setToken(null);
      } finally {
        setInitializing(false);
      }
    })();
  }, []);

  const signIn = useCallback(async (email: string, password: string) => {
    const res = await endpoints.login({ email, password });
    await setToken(res.token);
    setUser(res.user);
    setCompany(res.active_company);
  }, []);

  const signUp = useCallback(
    async (input: { name: string; email: string; password: string; password_confirmation: string }) => {
      const res = await endpoints.register(input);
      await setToken(res.token);
      setUser(res.user);
      setCompany(res.active_company);
    },
    [],
  );

  const refresh = useCallback(async () => {
    const res = await endpoints.me();
    setUser(res.user);
    setCompany(res.active_company);
  }, []);

  const value = useMemo<AuthState>(
    () => ({ initializing, user, company, signIn, signUp, signOut, refresh }),
    [initializing, user, company, signIn, signUp, signOut, refresh],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
