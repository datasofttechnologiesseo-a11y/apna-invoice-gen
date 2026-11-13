/**
 * Where the app talks to. Override with EXPO_PUBLIC_API_URL in mobile/.env
 * (e.g. your Mac's LAN IP when testing on a real phone, or a staging server).
 * The iOS simulator reaches the Mac's localhost directly.
 */
const DEV_URL = 'http://127.0.0.1:8741';
const PROD_URL = 'https://apnainvoice.com';

export const WEB_URL = (process.env.EXPO_PUBLIC_WEB_URL || (__DEV__ ? DEV_URL : PROD_URL)).replace(/\/$/, '');
export const API_URL = (process.env.EXPO_PUBLIC_API_URL || `${WEB_URL}/api`).replace(/\/$/, '');

export const LINKS = {
  privacy: `${WEB_URL}/privacy`,
  terms: `${WEB_URL}/terms`,
  refund: `${WEB_URL}/refund`,
  contact: `${WEB_URL}/contact`,
  forgotPassword: `${WEB_URL}/forgot-password`,
  web: WEB_URL,
};

/** Features that need API work before they can be switched on (see docs/mobile/07). */
export const FEATURES = {
  googleSignIn: false,
};
