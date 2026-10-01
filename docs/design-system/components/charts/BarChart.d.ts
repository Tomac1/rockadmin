import React from 'react';
import { ChartSeries } from './LineChart';
export interface BarChartProps { title?: string; subtitle?: string; labels: string[]; series: ChartSeries[]; height?: number; horizontal?: boolean; stacked?: boolean; className?: string; }
export function BarChart(props: BarChartProps): JSX.Element;
