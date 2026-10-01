import React from 'react';
export interface ChartSeries { name: string; data: number[]; color?: string; }
export interface LineChartProps { title?: string; subtitle?: string; labels: string[]; series: ChartSeries[]; height?: number; className?: string; }
export function LineChart(props: LineChartProps): JSX.Element;
