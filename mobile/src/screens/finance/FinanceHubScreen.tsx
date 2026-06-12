import React from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Card } from '../../components/ui';
import { AppHeader } from '../../components/AppHeader';
import { colors } from '../../theme';
import type { FinanceStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<FinanceStackParamList, 'FinanceHub'>;

interface MenuItem {
  icon: string;
  title: string;
  subtitle: string;
  route: keyof FinanceStackParamList;
}

const MENU: MenuItem[] = [
  { icon: '📈', title: 'Profit & Loss', subtitle: 'Revenue vs expenses, margins, cash', route: 'Pnl' },
  { icon: '⏳', title: 'Receivables aging', subtitle: 'Who owes you, and for how long', route: 'Aging' },
  { icon: '📄', title: 'GSTR-1', subtitle: 'Outward supplies summary + CSV', route: 'Gstr1' },
  { icon: '🧮', title: 'GSTR-3B', subtitle: 'Monthly tax summary + ITC', route: 'Gstr3b' },
  { icon: '🧾', title: 'Cash memos', subtitle: 'Purchase vouchers for cash buys', route: 'CashMemos' },
  { icon: '💸', title: 'Expenses', subtitle: 'Track business costs for your P&L', route: 'Expenses' },
];

export default function FinanceHubScreen() {
  const insets = useSafeAreaInsets();
  const navigation = useNavigation<Nav>();

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <AppHeader title="Finance" subtitle="Purchases, expenses & GST reports" />
      <ScrollView contentContainerStyle={{ padding: 16, paddingTop: 12 }}>
      {MENU.map((item) => (
        <Pressable key={item.route} onPress={() => navigation.navigate(item.route as never)}>
          <Card style={styles.row}>
            <Text style={styles.rowIcon}>{item.icon}</Text>
            <View style={{ flex: 1 }}>
              <Text style={styles.rowTitle}>{item.title}</Text>
              <Text style={styles.rowSub}>{item.subtitle}</Text>
            </View>
            <Text style={styles.chev}>›</Text>
          </Card>
        </Pressable>
      ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  title: { fontSize: 26, fontWeight: '800', color: colors.text },
  subtitle: { fontSize: 14, color: colors.muted, marginTop: 4 },
  row: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  rowIcon: { fontSize: 22, marginRight: 14 },
  rowTitle: { fontSize: 16, fontWeight: '700', color: colors.text },
  rowSub: { fontSize: 13, color: colors.muted, marginTop: 2 },
  chev: { fontSize: 26, color: colors.muted, marginLeft: 8 },
});
