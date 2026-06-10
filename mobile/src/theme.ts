// Central design tokens. Kept tiny and dependency-free.
export const colors = {
  primary: '#2563eb',
  primaryDark: '#1d4ed8',
  bg: '#f8fafc',
  card: '#ffffff',
  border: '#e2e8f0',
  text: '#0f172a',
  muted: '#64748b',
  success: '#16a34a',
  warning: '#d97706',
  danger: '#dc2626',
  draft: '#64748b',
  final: '#2563eb',
  paid: '#16a34a',
  partially_paid: '#d97706',
  cancelled: '#dc2626',
  // quotation statuses
  sent: '#2563eb',
  accepted: '#16a34a',
  declined: '#dc2626',
  converted: '#7c3aed',
  expired: '#b45309',
};

export const spacing = (n: number) => n * 4;

export function formatINR(amount: number): string {
  // Indian grouping (lakh/crore) via Intl.
  try {
    return '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 }).format(amount);
  } catch {
    return '₹' + amount.toFixed(2);
  }
}

export function statusColor(status: string): string {
  return (colors as Record<string, string>)[status] ?? colors.muted;
}

export function statusLabel(status: string): string {
  return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
