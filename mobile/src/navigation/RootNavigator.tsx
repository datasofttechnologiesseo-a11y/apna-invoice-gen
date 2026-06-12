import React from 'react';
import { ActivityIndicator } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useAuth } from '../auth/AuthContext';
import { Centered } from '../components/ui';
import { TabIcon, type IconName } from '../components/icons';
import { colors } from '../theme';

import LoginScreen from '../screens/auth/LoginScreen';
import RegisterScreen from '../screens/auth/RegisterScreen';
import DashboardScreen from '../screens/DashboardScreen';
import InvoicesScreen from '../screens/invoices/InvoicesScreen';
import InvoiceDetailScreen from '../screens/invoices/InvoiceDetailScreen';
import InvoiceEditScreen from '../screens/invoices/InvoiceEditScreen';
import CreditNotesScreen from '../screens/invoices/CreditNotesScreen';
import QuotationsScreen from '../screens/quotations/QuotationsScreen';
import QuotationDetailScreen from '../screens/quotations/QuotationDetailScreen';
import QuotationEditScreen from '../screens/quotations/QuotationEditScreen';
import CustomersScreen from '../screens/customers/CustomersScreen';
import CustomerLedgerScreen from '../screens/customers/CustomerLedgerScreen';
import ProductsScreen from '../screens/products/ProductsScreen';
import SettingsScreen from '../screens/SettingsScreen';
import CompaniesScreen from '../screens/CompaniesScreen';
import ReferralsScreen from '../screens/ReferralsScreen';
import ProfileScreen from '../screens/ProfileScreen';
import ActivityScreen from '../screens/ActivityScreen';
import BackupScreen from '../screens/BackupScreen';
import BlogListScreen from '../screens/blog/BlogListScreen';
import BlogPostScreen from '../screens/blog/BlogPostScreen';
import FinanceHubScreen from '../screens/finance/FinanceHubScreen';
import CashMemosScreen from '../screens/finance/CashMemosScreen';
import CashMemoDetailScreen from '../screens/finance/CashMemoDetailScreen';
import CashMemoCreateScreen from '../screens/finance/CashMemoCreateScreen';
import ExpensesScreen from '../screens/finance/ExpensesScreen';
import ExpenseFormScreen from '../screens/finance/ExpenseFormScreen';
import PnlScreen from '../screens/finance/PnlScreen';
import AgingScreen from '../screens/finance/AgingScreen';
import Gstr3bScreen from '../screens/finance/Gstr3bScreen';
import Gstr1Screen from '../screens/finance/Gstr1Screen';

import type {
  AuthStackParamList,
  CustomersStackParamList,
  FinanceStackParamList,
  InvoicesStackParamList,
  MainTabParamList,
  QuotationsStackParamList,
  SettingsStackParamList,
} from './types';

const AuthStack = createNativeStackNavigator<AuthStackParamList>();
const InvoicesStack = createNativeStackNavigator<InvoicesStackParamList>();
const QuotationsStack = createNativeStackNavigator<QuotationsStackParamList>();
const CustomersStack = createNativeStackNavigator<CustomersStackParamList>();
const SettingsStack = createNativeStackNavigator<SettingsStackParamList>();
const FinanceStack = createNativeStackNavigator<FinanceStackParamList>();
const Tab = createBottomTabNavigator<MainTabParamList>();

function AuthNavigator() {
  return (
    <AuthStack.Navigator screenOptions={{ headerShown: false }}>
      <AuthStack.Screen name="Login" component={LoginScreen} />
      <AuthStack.Screen name="Register" component={RegisterScreen} />
    </AuthStack.Navigator>
  );
}

function InvoicesNavigator() {
  return (
    <InvoicesStack.Navigator screenOptions={{ headerTintColor: colors.primary }}>
      <InvoicesStack.Screen
        name="InvoicesList"
        component={InvoicesScreen}
        options={{ headerShown: false }}
      />
      <InvoicesStack.Screen
        name="InvoiceDetail"
        component={InvoiceDetailScreen}
        options={{ title: 'Invoice' }}
      />
      <InvoicesStack.Screen
        name="InvoiceEdit"
        component={InvoiceEditScreen}
        options={{ title: 'Invoice' }}
      />
      <InvoicesStack.Screen
        name="CreditNotes"
        component={CreditNotesScreen}
        options={{ title: 'Credit notes' }}
      />
    </InvoicesStack.Navigator>
  );
}

function QuotationsNavigator() {
  return (
    <QuotationsStack.Navigator screenOptions={{ headerTintColor: colors.primary }}>
      <QuotationsStack.Screen
        name="QuotationsList"
        component={QuotationsScreen}
        options={{ headerShown: false }}
      />
      <QuotationsStack.Screen
        name="QuotationDetail"
        component={QuotationDetailScreen}
        options={{ title: 'Quotation' }}
      />
      <QuotationsStack.Screen
        name="QuotationEdit"
        component={QuotationEditScreen}
        options={{ title: 'Quotation' }}
      />
    </QuotationsStack.Navigator>
  );
}

function CustomersNavigator() {
  return (
    <CustomersStack.Navigator screenOptions={{ headerTintColor: colors.primary }}>
      <CustomersStack.Screen
        name="CustomersList"
        component={CustomersScreen}
        options={{ headerShown: false }}
      />
      <CustomersStack.Screen
        name="CustomerLedger"
        component={CustomerLedgerScreen}
        options={({ route }) => ({ title: route.params.name })}
      />
    </CustomersStack.Navigator>
  );
}

function FinanceNavigator() {
  return (
    <FinanceStack.Navigator screenOptions={{ headerTintColor: colors.primary }}>
      <FinanceStack.Screen name="FinanceHub" component={FinanceHubScreen} options={{ headerShown: false }} />
      <FinanceStack.Screen name="CashMemos" component={CashMemosScreen} options={{ title: 'Cash memos' }} />
      <FinanceStack.Screen name="CashMemoDetail" component={CashMemoDetailScreen} options={{ title: 'Cash memo' }} />
      <FinanceStack.Screen name="CashMemoCreate" component={CashMemoCreateScreen} options={{ title: 'New cash memo' }} />
      <FinanceStack.Screen name="Expenses" component={ExpensesScreen} options={{ title: 'Expenses' }} />
      <FinanceStack.Screen
        name="ExpenseForm"
        component={ExpenseFormScreen}
        options={({ route }) => ({ title: route.params?.id ? 'Edit expense' : 'New expense' })}
      />
      <FinanceStack.Screen name="Pnl" component={PnlScreen} options={{ title: 'Profit & Loss' }} />
      <FinanceStack.Screen name="Aging" component={AgingScreen} options={{ title: 'Receivables aging' }} />
      <FinanceStack.Screen name="Gstr3b" component={Gstr3bScreen} options={{ title: 'GSTR-3B' }} />
      <FinanceStack.Screen name="Gstr1" component={Gstr1Screen} options={{ title: 'GSTR-1' }} />
    </FinanceStack.Navigator>
  );
}

function SettingsNavigator() {
  return (
    <SettingsStack.Navigator screenOptions={{ headerTintColor: colors.primary }}>
      <SettingsStack.Screen
        name="SettingsHome"
        component={SettingsScreen}
        options={{ headerShown: false }}
      />
      <SettingsStack.Screen name="Companies" component={CompaniesScreen} options={{ title: 'Companies' }} />
      <SettingsStack.Screen name="Referrals" component={ReferralsScreen} options={{ title: 'Refer & earn' }} />
      <SettingsStack.Screen name="Profile" component={ProfileScreen} options={{ title: 'Profile & account' }} />
      <SettingsStack.Screen name="Activity" component={ActivityScreen} options={{ title: 'Activity log' }} />
      <SettingsStack.Screen name="Backup" component={BackupScreen} options={{ title: 'Backups' }} />
      <SettingsStack.Screen name="Blog" component={BlogListScreen} options={{ title: 'Guides & blog' }} />
      <SettingsStack.Screen
        name="BlogPost"
        component={BlogPostScreen}
        options={({ route }) => ({ title: route.params.title })}
      />
    </SettingsStack.Navigator>
  );
}

// Icon per visible tab. Home and Settings are intentionally omitted — Home is
// reached from the header logo, Settings from the header gear; neither shows in
// the bottom bar.
const TAB_ICONS: Partial<Record<keyof MainTabParamList, IconName>> = {
  Invoices: 'invoices',
  Quotes: 'quotes',
  Customers: 'customers',
  Products: 'products',
  Finance: 'finance',
};

function MainNavigator() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.muted,
        tabBarIcon: ({ color, size }) => {
          const name = TAB_ICONS[route.name];
          return name ? <TabIcon name={name} color={color} size={size ?? 24} /> : null;
        },
      })}
    >
      {/* Hidden from the bar but kept navigable — reached via the header logo.
          display:'none' collapses the slot so visible tabs space evenly. */}
      <Tab.Screen
        name="Home"
        component={DashboardScreen}
        options={{ tabBarItemStyle: { display: 'none' } }}
      />
      <Tab.Screen name="Invoices" component={InvoicesNavigator} />
      <Tab.Screen name="Quotes" component={QuotationsNavigator} />
      <Tab.Screen name="Customers" component={CustomersNavigator} />
      <Tab.Screen name="Products" component={ProductsScreen} />
      <Tab.Screen name="Finance" component={FinanceNavigator} />
      {/* Hidden from the bar but kept navigable so the header gear still works. */}
      <Tab.Screen
        name="Settings"
        component={SettingsNavigator}
        options={{ tabBarItemStyle: { display: 'none' } }}
      />
    </Tab.Navigator>
  );
}

export default function RootNavigator() {
  const { initializing, user } = useAuth();

  if (initializing) {
    return (
      <Centered>
        <ActivityIndicator size="large" color={colors.primary} />
      </Centered>
    );
  }

  return (
    <NavigationContainer>
      {user ? <MainNavigator /> : <AuthNavigator />}
    </NavigationContainer>
  );
}
