import * as SecureStore from 'expo-secure-store';

import { API_URL } from '@/config';

const TOKEN_KEY = 'apna.token';

let memoryToken: string | null = null;
let onUnauthorized: (() => void) | null = null;

export const tokenStore = {
  async load() {
    memoryToken = await SecureStore.getItemAsync(TOKEN_KEY);
    return memoryToken;
  },
  async save(token: string) {
    memoryToken = token;
    await SecureStore.setItemAsync(TOKEN_KEY, token);
  },
  async clear() {
    memoryToken = null;
    await SecureStore.deleteItemAsync(TOKEN_KEY);
  },
  get: () => memoryToken,
};

export function setUnauthorizedHandler(fn: () => void) {
  onUnauthorized = fn;
}

/** Every failed request becomes one of these — screens show `message`, forms show `fieldErrors`. */
export class ApiError extends Error {
  status: number;
  fieldErrors: Record<string, string>;
  constructor(status: number, message: string, fieldErrors: Record<string, string> = {}) {
    super(message);
    this.status = status;
    this.fieldErrors = fieldErrors;
  }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export async function api<T>(method: Method, path: string, body?: unknown): Promise<T> {
  let res: Response;
  try {
    res = await fetch(`${API_URL}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(memoryToken ? { Authorization: `Bearer ${memoryToken}` } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiError(0, 'Cannot reach Apna Invoice. Check your internet connection and try again.');
  }

  const text = await res.text();
  let json: any = null;
  try {
    json = text ? JSON.parse(text) : null;
  } catch {
    json = null;
  }

  if (!res.ok) {
    if (res.status === 401 && memoryToken) onUnauthorized?.();
    const fieldErrors: Record<string, string> = {};
    if (json?.errors && typeof json.errors === 'object') {
      for (const [k, v] of Object.entries(json.errors)) fieldErrors[k] = Array.isArray(v) ? String(v[0]) : String(v);
    }
    const message =
      res.status === 429
        ? 'Too many tries. Please wait a minute and try again.'
        : res.status === 403
          ? json?.message || 'Your role does not allow this. Ask the business owner.'
          : json?.message || `Something went wrong (${res.status}). Please try again.`;
    throw new ApiError(res.status, message, fieldErrors);
  }
  return json as T;
}

export const get = <T>(path: string) => api<T>('GET', path);
export const post = <T>(path: string, body: unknown = {}) => api<T>('POST', path, body);
export const put = <T>(path: string, body: unknown) => api<T>('PUT', path, body);
export const del = <T>(path: string, body?: unknown) => api<T>('DELETE', path, body);

/** Query string from an object, skipping empty values. */
export function qs(params: Record<string, string | number | undefined | null>): string {
  const parts = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`);
  return parts.length ? `?${parts.join('&')}` : '';
}

export function authHeader(): Record<string, string> {
  return memoryToken ? { Authorization: `Bearer ${memoryToken}` } : {};
}
