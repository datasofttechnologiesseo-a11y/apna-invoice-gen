import axios, { AxiosError } from 'axios';
import * as SecureStore from 'expo-secure-store';
import { API_BASE_URL, TOKEN_STORAGE_KEY } from '../config';

export const api = axios.create({
  baseURL: API_BASE_URL,
  headers: { Accept: 'application/json' },
  timeout: 20000,
});

let inMemoryToken: string | null = null;

export async function loadToken(): Promise<string | null> {
  if (inMemoryToken) return inMemoryToken;
  inMemoryToken = await SecureStore.getItemAsync(TOKEN_STORAGE_KEY);
  return inMemoryToken;
}

export async function setToken(token: string | null): Promise<void> {
  inMemoryToken = token;
  if (token) {
    await SecureStore.setItemAsync(TOKEN_STORAGE_KEY, token);
  } else {
    await SecureStore.deleteItemAsync(TOKEN_STORAGE_KEY);
  }
}

// Attach the bearer token to every request.
api.interceptors.request.use(async (config) => {
  const token = await loadToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Callback the AuthProvider registers so a 401 anywhere forces a logout.
let onUnauthorized: (() => void) | null = null;
export function setUnauthorizedHandler(fn: (() => void) | null) {
  onUnauthorized = fn;
}

api.interceptors.response.use(
  (res) => res,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      onUnauthorized?.();
    }
    return Promise.reject(error);
  },
);

/**
 * Normalise an axios error into a human message. Laravel returns
 * `{ message, errors: { field: [msg] } }` for 422 validation failures.
 */
export function apiErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined;
    if (data?.errors) {
      const first = Object.values(data.errors)[0];
      if (first && first.length) return first[0];
    }
    if (data?.message) return data.message;
    if (error.code === 'ECONNABORTED') return 'Request timed out. Check your connection.';
    if (!error.response) return 'Cannot reach the server. Check the API URL and that it is running.';
    return `Request failed (${error.response.status}).`;
  }
  return 'Something went wrong.';
}
