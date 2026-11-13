import { File, Paths } from 'expo-file-system';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';
import { Alert } from 'react-native';

import { documentsApi } from '@/api/workspace';

/** mm → PDF points (72 per inch). */
const pt = (mm: number) => Math.round((mm / 25.4) * 72);

/**
 * The web's 80 mm thermal slip (GET invoices/{id}/print). `print` opens the
 * system print dialog (Bluetooth / AirPrint printers); `pdf` renders the slip to
 * a PDF at slip width and opens the share sheet.
 */
export async function thermalSlip(invoiceId: number, fileName: string, mode: 'print' | 'pdf') {
  const slip = await documentsApi.thermal(invoiceId);
  if (mode === 'print') {
    try {
      await Print.printAsync({ html: slip.html });
    } catch {
      // the user closed the print dialog — nothing to report
    }
    return;
  }
  const { uri } = await Print.printToFileAsync({ html: slip.html, width: pt(slip.paper_width_mm || 80) });
  // give the file a readable name before sharing
  const named = new File(Paths.cache, fileName);
  try {
    if (named.exists) named.delete();
    new File(uri).move(named);
  } catch {
    // keep the generated name if renaming fails
  }
  const target = named.exists ? named.uri : uri;
  if (await Sharing.isAvailableAsync()) await Sharing.shareAsync(target, { mimeType: 'application/pdf', UTI: 'com.adobe.pdf', dialogTitle: fileName });
  else Alert.alert('Saved', `${fileName} was created.`);
}
