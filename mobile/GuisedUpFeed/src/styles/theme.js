export const COLORS = {
  primary: '#E8613C',
  background: '#FDF8F4',
  card: '#FFFFFF',
  textPrimary: '#2D2D2D',
  textSecondary: '#8C8C8C',
  border: '#F0E8E2',
  accent: '#D4A574',
};

export const SPACING = {
  xs: 4,
  sm: 8,
  md: 12,
  base: 16,
  lg: 24,
  xl: 32,
};

export const TYPOGRAPHY = {
  heading: {
    fontSize: 24,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  body: {
    fontSize: 15,
    fontWeight: '400',
    color: COLORS.textPrimary,
    lineHeight: 22,
  },
  caption: {
    fontSize: 13,
    fontWeight: '400',
    color: COLORS.textSecondary,
  },
  username: {
    fontSize: 15,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
};

export const CARD = {
  borderRadius: 16,
  shadow: {
    boxShadow: '0px 2px 8px rgba(0, 0, 0, 0.06)',
    elevation: 3,
  },
};
