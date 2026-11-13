import * as Updates from 'expo-updates';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, AppState, Modal, StyleSheet, View } from 'react-native';

import { colors, fonts } from '@/theme';

import { AppText } from './app-text';
import { Button } from './button';
import { Icon } from './icon';

type Phase = 'idle' | 'downloading' | 'ready' | 'failed';

/**
 * Over-the-air updates (EAS Update). On launch and whenever the app comes back
 * to the foreground we ask EAS for a newer JS bundle. If there is one we show a
 * blocking "Updating Apna Invoice" modal with progress, then restart into it.
 * Disabled in development builds (Updates.isEnabled === false).
 */
export function UpdateGate() {
  const { isDownloading, downloadProgress } = Updates.useUpdates();
  const [phase, setPhase] = useState<Phase>('idle');
  const busy = useRef(false);

  const download = async () => {
    setPhase('downloading');
    try {
      await Updates.fetchUpdateAsync();
      setPhase('ready');
      setTimeout(() => Updates.reloadAsync().catch(() => setPhase('failed')), 900);
    } catch {
      setPhase('failed');
    }
  };

  useEffect(() => {
    if (!Updates.isEnabled) return;
    const check = async () => {
      if (busy.current) return;
      busy.current = true;
      try {
        const res = await Updates.checkForUpdateAsync();
        if (res.isAvailable) await download();
      } catch {
        // Offline or EAS unreachable: keep running the current version silently.
      } finally {
        busy.current = false;
      }
    };
    check();
    const sub = AppState.addEventListener('change', (s) => s === 'active' && check());
    return () => sub.remove();
  }, []);

  const visible = phase !== 'idle';
  const pct = Math.round((downloadProgress ?? 0) * 100);

  return (
    <Modal visible={visible} transparent animationType="fade" statusBarTranslucent>
      <View style={styles.scrim}>
        <View style={styles.card} accessibilityLiveRegion="polite">
          <View style={styles.badge}>
            {phase === 'failed' ? <Icon name="alert" size={30} color={colors.redText} /> : phase === 'ready' ? <Icon name="checkmark" size={32} color={colors.navy} /> : <ActivityIndicator color={colors.navy} />}
          </View>
          <AppText variant="h2" align="center">
            {phase === 'failed' ? 'Update could not finish' : phase === 'ready' ? 'Update ready' : 'Updating Apna Invoice'}
          </AppText>
          <AppText variant="small" color={colors.textMuted} align="center" style={{ fontSize: 14, lineHeight: 20 }}>
            {phase === 'failed'
              ? 'Check your internet and try again. You can keep using the current version.'
              : phase === 'ready'
                ? 'Restarting with the newest version…'
                : 'Getting the latest improvements. Your data is safe and this takes a few seconds.'}
          </AppText>
          {phase === 'downloading' ? (
            <View style={{ alignSelf: 'stretch', gap: 6 }}>
              <View style={styles.track} accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: 100, now: pct }}>
                <View style={[styles.fill, { width: `${isDownloading ? Math.max(pct, 6) : 6}%` }]} />
              </View>
              <AppText style={{ fontFamily: fonts.semibold, fontSize: 12 }} color={colors.textMuted} align="center">
                {pct > 0 ? `${pct}%` : 'Starting download…'}
              </AppText>
            </View>
          ) : null}
          {phase === 'failed' ? (
            <View style={{ alignSelf: 'stretch', gap: 8 }}>
              <Button label="Try again" onPress={download} />
              <Button label="Not now" kind="ghost" size="md" onPress={() => setPhase('idle')} />
            </View>
          ) : null}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  scrim: { flex: 1, backgroundColor: colors.scrim, alignItems: 'center', justifyContent: 'center', padding: 28 },
  card: { width: '100%', maxWidth: 360, backgroundColor: colors.surface, borderRadius: 28, padding: 24, gap: 12, alignItems: 'center' },
  badge: { width: 64, height: 64, borderRadius: 32, backgroundColor: colors.amber, alignItems: 'center', justifyContent: 'center', marginBottom: 4 },
  track: { height: 8, borderRadius: 999, backgroundColor: colors.tealSoft, overflow: 'hidden' },
  fill: { height: '100%', borderRadius: 999, backgroundColor: colors.teal },
});
