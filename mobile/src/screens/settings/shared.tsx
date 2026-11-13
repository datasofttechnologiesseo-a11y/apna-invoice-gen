import { ReactElement } from 'react';
import { Switch, View } from 'react-native';

import { ApiError } from '@/api/client';
import type { Company } from '@/api/workspace';
import { AppText } from '@/components/app-text';
import { Card, ErrorState, Loading } from '@/components/blocks';
import { Icon } from '@/components/icon';
import { useCan as useAreas } from '@/hooks/role';
import { Screen } from '@/components/screen';
import { useActiveCompany } from '@/hooks/workspace';
import { colors, fonts } from '@/theme';

/** /me `can` map: which areas the signed-in role opens (CompanyMember::RULES). Missing = allowed (owner). */
export function useCan(area: 'company_settings' | 'team' | 'reports'): boolean {
  return useAreas()[area];
}

/** Loads the active company, then renders the settings form for it. */
export function CompanyGate({ title, children }: { title: string; children: (c: Company) => ReactElement }) {
  const q = useActiveCompany();
  if (q.isLoading) {
    return (
      <Screen nav="back" title={title}>
        <Loading />
      </Screen>
    );
  }
  if (q.error || !q.data) {
    return (
      <Screen nav="back" title={title}>
        <ErrorState message={(q.error as Error)?.message ?? 'Could not load your business.'} onRetry={() => q.refetch()} />
      </Screen>
    );
  }
  return children(q.data);
}

/** Field errors of a failed save (422), or {} — shown under each field. */
export function fieldErrors(error: unknown): Record<string, string> {
  return error instanceof ApiError ? error.fieldErrors : {};
}

/** A save error that is not about one field (403 role message, network…). */
export function FormError({ error }: { error: unknown }) {
  if (!error || Object.keys(fieldErrors(error)).length) return null;
  return (
    <AppText variant="small" color={colors.redText} accessibilityLiveRegion="polite">
      {(error as Error).message}
    </AppText>
  );
}

export function Notice({ text, tone = 'info' }: { text: string; tone?: 'info' | 'warn' }) {
  const warn = tone === 'warn';
  return (
    <Card style={{ flexDirection: 'row', gap: 10, alignItems: 'flex-start', backgroundColor: warn ? colors.amberTint : colors.tealTint }}>
      <Icon name={warn ? 'warning-outline' : 'information-circle-outline'} size={18} color={warn ? colors.amberText : colors.tealDark} />
      <AppText variant="small" color={warn ? colors.amberText : colors.tealDark} style={{ flex: 1 }}>
        {text}
      </AppText>
    </Card>
  );
}

/** Labelled on/off row for a settings form. */
export function ToggleRow({ label, hint, value, onChange }: { label: string; hint?: string; value: boolean; onChange: (v: boolean) => void }) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 44 }}>
      <View style={{ flex: 1, gap: 2 }}>
        <AppText style={{ fontFamily: fonts.semibold, fontSize: 14 }}>{label}</AppText>
        {hint ? (
          <AppText variant="small" color={colors.textMuted}>
            {hint}
          </AppText>
        ) : null}
      </View>
      <Switch value={value} onValueChange={onChange} accessibilityLabel={label} trackColor={{ true: colors.teal, false: colors.border }} thumbColor={colors.surface} />
    </View>
  );
}

/** "" → null for optional text fields. */
export const orNull = (v: string) => (v.trim() ? v.trim() : null);

export const NO_SETTINGS_ROLE = 'Your role in this business cannot change company settings. Ask the owner if something here needs to change.';
