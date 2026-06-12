import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { getActiveCompany, updateCompany } from '../api/endpoints';
import { apiErrorMessage } from '../api/client';
import { Button, TextField } from '../components/ui';
import { StatePicker } from '../components/StatePicker';
import { colors } from '../theme';

/**
 * Full-screen company editor. Lets the user set the state + GSTIN so invoices
 * compute GST correctly, plus bank/UPI details that appear on the PDF — making
 * the mobile app self-sufficient (no need to open the web app to onboard).
 */
export function CompanyEditModal({
  visible,
  onClose,
  onSaved,
}: {
  visible: boolean;
  onClose: () => void;
  onSaved: () => void;
}) {
  const qc = useQueryClient();
  const companyQ = useQuery({ queryKey: ['active-company'], queryFn: getActiveCompany, enabled: visible });

  const [form, setForm] = useState({
    name: '',
    gstin: '',
    pan: '',
    state_id: null as number | null,
    stateName: '',
    city: '',
    address_line1: '',
    postal_code: '',
    phone: '',
    email: '',
    bank_name: '',
    bank_account_number: '',
    bank_ifsc: '',
    upi_id: '',
    invoice_prefix: 'INV',
  });
  const [statePicker, setStatePicker] = useState(false);

  useEffect(() => {
    const c = companyQ.data;
    if (c) {
      setForm({
        name: c.name ?? '',
        gstin: c.gstin ?? '',
        pan: c.pan ?? '',
        state_id: c.state_id,
        stateName: c.state?.name ?? '',
        city: c.city ?? '',
        address_line1: c.address_line1 ?? '',
        postal_code: c.postal_code ?? '',
        phone: c.phone ?? '',
        email: c.email ?? '',
        bank_name: c.bank_name ?? '',
        bank_account_number: c.bank_account_number ?? '',
        bank_ifsc: c.bank_ifsc ?? '',
        upi_id: c.upi_id ?? '',
        invoice_prefix: c.invoice_prefix ?? 'INV',
      });
    }
  }, [companyQ.data]);

  const set = (k: keyof typeof form, v: string) => setForm((f) => ({ ...f, [k]: v }));

  const mut = useMutation({
    mutationFn: () =>
      updateCompany(companyQ.data!.id, {
        name: form.name.trim(),
        state_id: form.state_id!,
        country: 'India',
        default_currency: companyQ.data?.default_currency || 'INR',
        invoice_prefix: form.invoice_prefix || 'INV',
        invoice_number_padding: 4,
        gstin: form.gstin || null,
        pan: form.pan || null,
        city: form.city || null,
        address_line1: form.address_line1 || null,
        postal_code: form.postal_code || null,
        phone: form.phone || null,
        email: form.email || null,
        bank_name: form.bank_name || null,
        bank_account_number: form.bank_account_number || null,
        bank_ifsc: form.bank_ifsc || null,
        upi_id: form.upi_id || null,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['active-company'] });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
      onSaved();
    },
    onError: (e) => alert(apiErrorMessage(e)),
  });

  function save() {
    if (!form.name.trim()) return alert('Business name is required');
    if (!form.state_id) return alert('State is required (drives GST on every invoice)');
    mut.mutate();
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.bg }}>
        <View style={styles.header}>
          <Text style={styles.title}>Edit company</Text>
          <Text style={styles.close} onPress={onClose}>
            Close
          </Text>
        </View>

        {companyQ.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 40 }} />
        ) : (
          <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
            <Text style={styles.section}>Business</Text>
            <TextField label="Business name *" value={form.name} onChangeText={(v) => set('name', v)} />

            <Text style={styles.label}>State * (drives CGST/SGST vs IGST)</Text>
            <Pressable style={styles.picker} onPress={() => setStatePicker(true)}>
              <Text style={form.stateName ? styles.pickerText : styles.pickerPlaceholder}>
                {form.stateName || 'Select state'}
              </Text>
            </Pressable>
            <View style={{ height: 14 }} />

            <TextField label="GSTIN" value={form.gstin} onChangeText={(v) => set('gstin', v)} autoCapitalize="characters" placeholder="15-char GSTIN (optional)" />
            <TextField label="PAN" value={form.pan} onChangeText={(v) => set('pan', v)} autoCapitalize="characters" placeholder="Optional" />
            <TextField label="Invoice number prefix" value={form.invoice_prefix} onChangeText={(v) => set('invoice_prefix', v)} autoCapitalize="characters" />

            <Text style={styles.section}>Address</Text>
            <TextField label="Address" value={form.address_line1} onChangeText={(v) => set('address_line1', v)} />
            <TextField label="City" value={form.city} onChangeText={(v) => set('city', v)} />
            <TextField label="PIN code" value={form.postal_code} onChangeText={(v) => set('postal_code', v)} keyboardType="number-pad" />
            <TextField label="Phone" value={form.phone} onChangeText={(v) => set('phone', v)} keyboardType="phone-pad" />
            <TextField label="Email" value={form.email} onChangeText={(v) => set('email', v)} keyboardType="email-address" autoCapitalize="none" />

            <Text style={styles.section}>Bank / payment (shown on invoice)</Text>
            <TextField label="Bank name" value={form.bank_name} onChangeText={(v) => set('bank_name', v)} />
            <TextField label="Account number" value={form.bank_account_number} onChangeText={(v) => set('bank_account_number', v)} />
            <TextField label="IFSC" value={form.bank_ifsc} onChangeText={(v) => set('bank_ifsc', v)} autoCapitalize="characters" />
            <TextField label="UPI ID" value={form.upi_id} onChangeText={(v) => set('upi_id', v)} autoCapitalize="none" placeholder="name@bank" />

            <Button title="Save company" onPress={save} loading={mut.isPending} style={{ marginTop: 8, marginBottom: 40 }} />
          </ScrollView>
        )}

        <StatePicker
          visible={statePicker}
          onClose={() => setStatePicker(false)}
          onPick={(id, name) => {
            setForm((f) => ({ ...f, state_id: id, stateName: name }));
            setStatePicker(false);
          }}
        />
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    paddingTop: 50,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  title: { fontSize: 20, fontWeight: '800', color: colors.text },
  close: { fontSize: 16, color: colors.primary, fontWeight: '600' },
  section: { fontSize: 15, fontWeight: '800', color: colors.text, marginTop: 16, marginBottom: 10 },
  label: { fontSize: 13, fontWeight: '600', color: colors.text, marginBottom: 6 },
  picker: { borderWidth: 1, borderColor: colors.border, borderRadius: 10, padding: 14, backgroundColor: colors.card },
  pickerText: { fontSize: 16, color: colors.text },
  pickerPlaceholder: { fontSize: 16, color: colors.muted },
});
