import React from 'react';
export interface CardProps { children?: React.ReactNode; title?: string; subtitle?: string; actions?: React.ReactNode; footer?: React.ReactNode; flat?: boolean; hoverable?: boolean; flush?: boolean; noBorder?: boolean; className?: string; style?: React.CSSProperties; onClick?: () => void; }
export function Card(props: CardProps): JSX.Element;
