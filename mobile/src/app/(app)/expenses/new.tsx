import { useLocalSearchParams } from 'expo-router';

import { ExpenseForm } from '@/screens/expenses/expense-form';

/** New expense. ?category= preselects the category (from a filtered list or the dashboard). */
export default function NewExpense() {
  const { category } = useLocalSearchParams<{ category?: string }>();
  return <ExpenseForm defaultCategory={category || undefined} />;
}
