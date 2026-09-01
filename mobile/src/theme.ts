// Central design tokens. Kept tiny and dependency-free.
//
// Aligned with the web palette (tailwind.config.js): brand teal, amber
// accent, green money, red danger. The semantic values below already
// matched the web exactly; only the primary blues moved.
export const colors = {
  primary: '#0f766e',
  primaryDark: '#115e59',
  bg: '#f8fafc',
  card: '#ffffff',
  border: '#e2e8f0',
  text: '#0f172a',
  muted: '#64748b',
  success: '#16a34a',
  warning: '#d97706',
  danger: '#dc2626',
  draft: '#64748b',
  final: '#0f766e',
  paid: '#16a34a',
  partially_paid: '#d97706',
  cancelled: '#dc2626',
  // quotation statuses
  sent: '#0f766e',
  accepted: '#16a34a',
  declined: '#dc2626',
  converted: '#134e4a',
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
