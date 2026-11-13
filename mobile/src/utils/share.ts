import { File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import { Alert, Linking, Share } from 'react-native';

import { authHeader } from '@/api/client';
import { API_URL } from '@/config';

/** Download an authed API file (PDF/CSV) and hand it to the share sheet. */
export async function downloadAndShare(apiPath: string, fileName: string, mimeType = 'application/pdf') {
  try {
    const target = new File(Paths.cache, fileName);
    const file = await File.downloadFileAsync(`${API_URL}${apiPath}`, target, { headers: { ...authHeader(), Accept: mimeType }, idempotent: true });
    if (await Sharing.isAvailableAsync()) {
      await Sharing.shareAsync(file.uri, { mimeType, dialogTitle: fileName, UTI: mimeType === 'application/pdf' ? 'com.adobe.pdf' : undefined });
    } else {
      Alert.alert('Saved', `${fileName} was downloaded.`);
    }
  } catch {
    Alert.alert('Could not download', 'Check your internet connection and try again.');
  }
}

/** Open a wa.me link in WhatsApp; fall back to the system share sheet. */
export async function openWhatsApp(link: string | null | undefined, fallbackText: string) {
  if (link && (await Linking.canOpenURL(link).catch(() => false))) return Linking.openURL(link);
  return Share.share({ message: fallbackText });
}

export function shareText(message: string) {
  return Share.share({ message });
}
