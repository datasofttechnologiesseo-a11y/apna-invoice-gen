import type { NavigatorScreenParams } from '@react-navigation/native';

export type AuthStackParamList = {
  Login: undefined;
  Register: undefined;
};

export type InvoicesStackParamList = {
  InvoicesList: undefined;
  InvoiceDetail: { id: number };
  InvoiceEdit: { id?: number };
  CreditNotes: { invoiceId: number; invoiceNumber: string };
};

export type QuotationsStackParamList = {
  QuotationsList: undefined;
  QuotationDetail: { id: number };
  QuotationEdit: { id?: number };
};

export type CustomersStackParamList = {
  CustomersList: undefined;
  CustomerLedger: { id: number; name: string };
};

export type SettingsStackParamList = {
  SettingsHome: undefined;
  Companies: undefined;
  Referrals: undefined;
  Profile: undefined;
  Activity: undefined;
  Backup: undefined;
  Blog: undefined;
  BlogPost: { slug: string; title: string };
};

export type FinanceStackParamList = {
  FinanceHub: undefined;
  CashMemos: undefined;
  CashMemoDetail: { id: number };
  CashMemoCreate: undefined;
  Expenses: undefined;
  ExpenseForm: { id?: number } | undefined;
  Pnl: undefined;
  Aging: undefined;
  Gstr3b: undefined;
  Gstr1: undefined;
};

export type MainTabParamList = {
  Home: undefined;
  Invoices: NavigatorScreenParams<InvoicesStackParamList> | undefined;
  Quotes: NavigatorScreenParams<QuotationsStackParamList> | undefined;
  Customers: NavigatorScreenParams<CustomersStackParamList> | undefined;
  Products: undefined;
  Finance: NavigatorScreenParams<FinanceStackParamList> | undefined;
  More: NavigatorScreenParams<SettingsStackParamList> | undefined;
};
