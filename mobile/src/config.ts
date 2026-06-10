import { Platform } from 'react-native';
import Constants from 'expo-constants';

/**
 * Base URL of the Laravel API.
 *
 * Resolution order:
 *   1. `expo.extra.apiBaseUrl` in app.json (set this for real devices / prod).
 *   2. Platform-aware localhost default for the local `php artisan serve`:
 *        - Android emulator reaches the host machine at 10.0.2.2
 *        - iOS simulator / web can use 127.0.0.1
 *
 * NOTE: a *physical* phone running Expo Go cannot reach 127.0.0.1 / 10.0.2.2 —
 * it must use your computer's LAN IP (e.g. http://192.168.1.50:8741). Put that
 * in app.json → expo.extra.apiBaseUrl, or override here.
 */
const DEV_PORT = 8741;

function defaultDevHost(): string {
  if (Platform.OS === 'android') return `http://10.0.2.2:${DEV_PORT}`;
  return `http://127.0.0.1:${DEV_PORT}`;
}

const configured = (Constants.expoConfig?.extra as { apiBaseUrl?: string } | undefined)?.apiBaseUrl;

export const API_BASE_URL = `${(configured && configured.length > 0 ? configured : defaultDevHost()).replace(/\/$/, '')}/api`;

export const TOKEN_STORAGE_KEY = 'apna_invoice_token';
