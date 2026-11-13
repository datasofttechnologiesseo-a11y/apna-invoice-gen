import { FlashList } from '@shopify/flash-list';
import { ReactElement, ReactNode, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Modal, Pressable, StyleSheet, TextInput, TextInputInstance, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useDelayedFocus } from '@/hooks/use-delayed-focus';
import { colors, fonts } from '@/theme';

import { AppText } from './app-text';
import { Button } from './button';
import { Icon } from './icon';

/**
 * Full-height searchable picker (customers, items, states). One component so
 * every picker in the app searches, scrolls and closes the same way.
 */
export function PickerSheet<T>({
  visible,
  title,
  placeholder,
  items,
  loading,
  onSearch,
  onClose,
  renderItem,
  keyOf,
  header,
  empty,
}: {
  visible: boolean;
  title: string;
  placeholder: string;
  items: T[];
  loading?: boolean;
  onSearch: (q: string) => void;
  onClose: () => void;
  renderItem: (item: T) => ReactElement;
  keyOf: (item: T) => string;
  header?: ReactNode;
  empty?: ReactNode;
}) {
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={onClose}>
      <View style={[styles.root, { paddingBottom: insets.bottom }]}>
        <View style={styles.top}>
          <AppText variant="h2" style={{ flex: 1 }} accessibilityRole="header">
            {title}
          </AppText>
          <Button kind="outline" size="md" icon="close" accessibilityLabel="Close" onPress={onClose} />
        </View>
        <SearchInput placeholder={placeholder} loading={loading} onSearch={onSearch} />
        {header}
        <FlashList
          data={items}
          keyExtractor={keyOf}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="on-drag"
          renderItem={({ item }) => renderItem(item)}
          ListEmptyComponent={loading ? null : <View style={{ padding: 24 }}>{empty}</View>}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 24 }}
        />
      </View>
    </Modal>
  );
}

/** Lives inside the Modal, so it unmounts (and clears) every time the sheet closes. */
function SearchInput({ placeholder, loading, onSearch }: { placeholder: string; loading?: boolean; onSearch: (q: string) => void }) {
  const [text, setText] = useState('');
  const input = useRef<TextInputInstance>(null);
  useDelayedFocus(input, true);
  useEffect(() => {
    const t = setTimeout(() => onSearch(text.trim()), 250);
    return () => clearTimeout(t);
  }, [text, onSearch]);
  return (
    <View style={styles.search}>
      <Icon name="search" size={18} color={colors.textMuted} />
      <TextInput ref={input} value={text} onChangeText={setText} placeholder={placeholder} placeholderTextColor="#8A94A6" style={styles.input} autoCorrect={false} accessibilityLabel={placeholder} />
      {loading ? <ActivityIndicator color={colors.teal} /> : null}
    </View>
  );
}

export function PickerRow({ title, subtitle, right, onPress }: { title: string; subtitle?: string; right?: string; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => [styles.row, { opacity: pressed ? 0.6 : 1 }]}>
      <View style={{ flex: 1, gap: 2 }}>
        <AppText style={{ fontFamily: fonts.semibold, fontSize: 15 }}>{title}</AppText>
        {subtitle ? (
          <AppText variant="small" color={colors.textMuted}>
            {subtitle}
          </AppText>
        ) : null}
      </View>
      {right ? <AppText variant="money">{right}</AppText> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.surface },
  top: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 16, paddingTop: 20 },
  search: { marginHorizontal: 16, marginBottom: 8, height: 50, borderRadius: 14, borderWidth: 1.5, borderColor: colors.teal, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 10 },
  input: { flex: 1, fontFamily: fonts.medium, fontSize: 16, color: colors.text },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 60, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.border },
});
