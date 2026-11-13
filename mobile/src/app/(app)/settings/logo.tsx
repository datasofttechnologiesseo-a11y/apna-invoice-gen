import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { Alert, StyleSheet, View } from 'react-native';

import { Company } from '@/api/workspace';
import { AppText } from '@/components/app-text';
import { Card, IconTile, Overline } from '@/components/blocks';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { useBranding } from '@/hooks/workspace';
import { CompanyGate, fieldErrors, FormError, NO_SETTINGS_ROLE, Notice, useCan } from '@/screens/settings/shared';
import { colors, radius } from '@/theme';

export default function LogoSettingsRoute() {
  return <CompanyGate title="Logo & signature">{(c) => <Branding key={c.id} company={c} />}</CompanyGate>;
}

type Field = 'logo' | 'signature';

const INFO: Record<Field, { label: string; max: string; aspect: [number, number]; box: number; hint: string }> = {
  logo: { label: 'Logo', max: '2 MB', aspect: [3, 2], box: 120, hint: 'Top of every invoice, quotation and receipt.' },
  signature: { label: 'Signature', max: '1 MB', aspect: [3, 1], box: 90, hint: 'Above “Authorised signatory”. A clear signature on white paper works best.' },
};

/** Logo and signature (POST companies/{id}/branding). Size and type are checked by the server, as on the web. */
function Branding({ company: c }: { company: Company }) {
  const allowed = useCan('company_settings');
  const brand = useBranding();
  const errors = fieldErrors(brand.error);
  const busyField = brand.isPending ? brand.variables?.field : null;

  const pick = async (field: Field) => {
    const res = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsEditing: true, aspect: INFO[field].aspect, quality: 0.85 });
    if (res.canceled || !res.assets?.[0]) return;
    const a = res.assets[0];
    const type = a.mimeType && a.mimeType !== 'image/heic' ? a.mimeType : 'image/jpeg';
    const ext = type.split('/')[1]?.replace('jpeg', 'jpg') ?? 'jpg';
    brand.mutate({ id: c.id, field, file: { uri: a.uri, name: `${field}.${ext}`, type } });
  };

  const remove = (field: Field) =>
    Alert.alert(`Remove the ${field}?`, `It will no longer print on your invoices.`, [
      { text: 'Keep', style: 'cancel' },
      { text: 'Remove', style: 'destructive', onPress: () => brand.mutate({ id: c.id, field, file: null }) },
    ]);

  const block = (field: Field) => {
    const i = INFO[field];
    const url = field === 'logo' ? c.logo_url : c.signature_url;
    return (
      <View key={field} style={{ gap: 8 }}>
        <Overline>{i.label}</Overline>
        <Card>
          <View style={[styles.box, { height: i.box }]}>
            {url ? (
              <Image source={{ uri: url }} style={StyleSheet.absoluteFill} contentFit="contain" accessibilityLabel={`Current ${field}`} transition={150} />
            ) : (
              <View style={{ alignItems: 'center', gap: 6 }}>
                <IconTile icon={field === 'logo' ? 'image-outline' : 'create-outline'} size={40} />
                <AppText variant="small" color={colors.textMuted}>
                  No {field} yet
                </AppText>
              </View>
            )}
          </View>
          <AppText variant="small" color={colors.textMuted}>
            {i.hint} PNG, JPG or WebP, up to {i.max}.
          </AppText>
          {errors[field] ? <AppText variant="small" color={colors.redText}>{errors[field]}</AppText> : null}
          {allowed ? (
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <Button label={url ? 'Replace' : `Add ${field}`} icon="image-outline" size="md" style={{ flex: 1 }} loading={busyField === field} disabled={!!busyField && busyField !== field} onPress={() => pick(field)} />
              {url ? <Button label="Remove" kind="danger" icon="trash-outline" size="md" disabled={!!busyField} onPress={() => remove(field)} /> : null}
            </View>
          ) : null}
        </Card>
      </View>
    );
  };

  return (
    <Screen nav="back" title="Logo & signature" subtitle="Printed on every PDF">
      {!allowed ? <Notice text={NO_SETTINGS_ROLE} /> : null}
      {block('logo')}
      {block('signature')}
      <FormError error={brand.error} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  box: { borderRadius: radius.md, borderWidth: 1, borderStyle: 'dashed', borderColor: colors.border, backgroundColor: colors.ground, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
});
