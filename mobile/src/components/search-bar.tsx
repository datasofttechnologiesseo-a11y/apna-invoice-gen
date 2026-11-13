import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, TextInput, View } from 'react-native';

import { colors, fonts } from '@/theme';

import { Icon } from './icon';

/** Search field for the gradient header. Calls onSearch 300 ms after typing stops. */
export function SearchBar({ placeholder, onSearch, autoFocus }: { placeholder: string; onSearch: (q: string) => void; autoFocus?: boolean }) {
  const [text, setText] = useState('');
  useEffect(() => {
    const t = setTimeout(() => onSearch(text.trim()), 300);
    return () => clearTimeout(t);
  }, [text, onSearch]);
  return (
    <View style={styles.box}>
      <Icon name="search" size={18} color={colors.onDarkMuted} />
      <TextInput
        value={text}
        onChangeText={setText}
        placeholder={placeholder}
        placeholderTextColor="#B8C7D6"
        style={styles.input}
        returnKeyType="search"
        autoFocus={autoFocus}
        accessibilityLabel={placeholder}
        autoCorrect={false}
        maxFontSizeMultiplier={1.4}
      />
      {text ? (
        <Pressable accessibilityRole="button" accessibilityLabel="Clear search" onPress={() => setText('')} hitSlop={10}>
          <Icon name="close-circle" size={18} color={colors.onDarkMuted} />
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  box: { height: 48, borderRadius: 14, backgroundColor: 'rgba(255,255,255,0.12)', flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 10 },
  input: { flex: 1, color: colors.onDark, fontFamily: fonts.medium, fontSize: 15, paddingVertical: 10 },
});
