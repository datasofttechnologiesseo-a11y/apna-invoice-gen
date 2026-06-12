import React, { useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { Button } from './ui';
import { colors } from '../theme';

export type CopyType = 'recipient' | 'transporter' | 'supplier';

// Party-friendly label on top, the legal Rule 48(1) wording underneath.
const OPTIONS: { key: CopyType; label: string; sub: string }[] = [
  { key: 'recipient', label: 'Customer', sub: 'Original for Recipient' },
  { key: 'supplier', label: 'Supplier', sub: 'Duplicate for Supplier' },
  { key: 'transporter', label: 'Delivery', sub: 'Triplicate for Transporter' },
];

/**
 * Asks which invoice copies to include before downloading the PDF. Only the
 * Customer copy is ticked by default; the caller receives the selected copy
 * types in Rule 48(1) order to pass to the `?copyTypes=` PDF endpoint.
 */
export function CopiesPickerModal({
  visible,
  onClose,
  onConfirm,
}: {
  visible: boolean;
  onClose: () => void;
  onConfirm: (types: CopyType[]) => void;
}) {
  const [selected, setSelected] = useState<Record<CopyType, boolean>>({
    recipient: true,
    transporter: false,
    supplier: false,
  });

  const toggle = (k: CopyType) => setSelected((s) => ({ ...s, [k]: !s[k] }));
  const chosen = OPTIONS.filter((o) => selected[o.key]).map((o) => o.key);

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <Pressable style={styles.backdrop} onPress={onClose}>
        <Pressable style={styles.sheet} onPress={() => {}}>
          <Text style={styles.title}>Which copies?</Text>
          <Text style={styles.subtitle}>Select the copies to include in the PDF.</Text>

          {OPTIONS.map((o) => {
            const on = selected[o.key];
            return (
              <Pressable key={o.key} style={styles.row} onPress={() => toggle(o.key)}>
                <View style={[styles.box, on && styles.boxOn]}>
                  {on ? <Text style={styles.tick}>✓</Text> : null}
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.label}>{o.label}</Text>
                  <Text style={styles.sub}>{o.sub}</Text>
                </View>
              </Pressable>
            );
          })}

          <Button
            title={`Download ${chosen.length} ${chosen.length === 1 ? 'copy' : 'copies'}`}
            onPress={() => onConfirm(chosen)}
            disabled={chosen.length === 0}
            style={{ marginTop: 16 }}
          />
          <Button title="Cancel" variant="secondary" onPress={onClose} style={{ marginTop: 10 }} />
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    justifyContent: 'flex-end',
  },
  sheet: {
    backgroundColor: colors.bg,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 20,
    paddingBottom: 32,
  },
  title: { fontSize: 20, fontWeight: '800', color: colors.text },
  subtitle: { fontSize: 14, color: colors.muted, marginTop: 4, marginBottom: 12 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  box: {
    width: 24,
    height: 24,
    borderRadius: 6,
    borderWidth: 2,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
  },
  boxOn: { backgroundColor: colors.primary, borderColor: colors.primary },
  tick: { color: '#fff', fontSize: 15, fontWeight: '900', lineHeight: 18 },
  label: { fontSize: 16, fontWeight: '700', color: colors.text },
  sub: { fontSize: 13, color: colors.muted, marginTop: 2 },
});
