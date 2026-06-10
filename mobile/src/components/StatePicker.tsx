import React from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { getStates } from '../api/endpoints';
import { colors } from '../theme';

/**
 * Bottom-sheet picker for Indian states. Shared by the customer and company
 * forms — both need a state_id for GST determination.
 */
export function StatePicker({
  visible,
  onClose,
  onPick,
}: {
  visible: boolean;
  onClose: () => void;
  onPick: (id: number, name: string) => void;
}) {
  const statesQ = useQuery({ queryKey: ['states'], queryFn: getStates, enabled: visible });

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={styles.sheet}>
          <View style={styles.header}>
            <Text style={styles.title}>Select state</Text>
            <Text style={styles.close} onPress={onClose}>
              Close
            </Text>
          </View>
          <ScrollView style={{ maxHeight: 460 }}>
            {(statesQ.data ?? []).map((s) => (
              <Pressable
                key={s.id}
                style={styles.row}
                onPress={() => onPick(s.id, s.name)}
              >
                <Text style={styles.name}>{s.name}</Text>
                <Text style={styles.code}>{s.gst_code}</Text>
              </Pressable>
            ))}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: '#00000066', justifyContent: 'flex-end' },
  sheet: { backgroundColor: colors.card, borderTopLeftRadius: 20, borderTopRightRadius: 20, paddingBottom: 30 },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  title: { fontSize: 18, fontWeight: '700', color: colors.text },
  close: { fontSize: 15, color: colors.primary, fontWeight: '600' },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 14,
    paddingHorizontal: 20,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  name: { fontSize: 16, color: colors.text },
  code: { fontSize: 14, color: colors.muted },
});
