import React from 'react';
export interface PageHeaderProps { title: string; description?: string; eyebrow?: string; meta?: React.ReactNode; actions?: React.ReactNode; compact?: boolean; className?: string; }
export function PageHeader(props: PageHeaderProps): JSX.Element;
