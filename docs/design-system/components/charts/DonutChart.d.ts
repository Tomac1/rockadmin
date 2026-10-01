import React from 'react';
export interface DonutSegment { label: string; value: number; color?: string; }
export interface DonutChartProps { title?: string; subtitle?: string; data: DonutSegment[]; size?: number; className?: string; }
export function DonutChart(props: DonutChartProps): JSX.Element;
