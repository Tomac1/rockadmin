import React from 'react';
export interface StatCardProps { label: string; value: string | number; subLabel?: string; trend?: number; trendLabel?: string; icon?: React.ReactNode; iconColor?: string; iconBg?: string; sparklineData?: number[]; className?: string; style?: React.CSSProperties; }
export function StatCard(props: StatCardProps): JSX.Element;
