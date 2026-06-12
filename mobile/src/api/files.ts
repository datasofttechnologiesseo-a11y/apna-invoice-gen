import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { loadToken } from './client';
import { API_BASE_URL } from '../config';

/**
 * Download an authenticated API file (PDF / CSV) to the cache directory and open
 * the system share sheet so the user can save / forward it.
 *
 * The signed public invoice PDF can be opened straight in the browser, but most
 * document endpoints (credit-note PDF, cash-memo PDF, GSTR/aging CSV exports)
 * sit behind `auth:sanctum` — a browser tab won't carry the bearer token, so we
 * fetch the bytes with the token attached and hand the local file to Sharing.
 *
 * @param path     API path relative to the `/api` base, e.g. `/credit-notes/5/pdf`
 * @param filename Suggested file name shown in the share sheet
 */
export async function downloadAndShare(path: string, filename: string): Promise<void> {
  const token = await loadToken();
  const url = `${API_BASE_URL}${path}`;
  const dest = (FileSystem.cacheDirectory ?? '') + filename;

  const res = await FileSystem.downloadAsync(url, dest, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });

  if (res.status !== 200) {
    throw new Error(`Download failed (${res.status}).`);
  }

  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(res.uri);
  }
}
