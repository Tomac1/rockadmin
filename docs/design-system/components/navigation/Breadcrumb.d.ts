import React from 'react';
export interface BreadcrumbItem { label: string; href?: string; onClick?: () => void; }
export interface BreadcrumbProps { items: BreadcrumbItem[]; className?: string; }
export function Breadcrumb(props: BreadcrumbProps): JSX.Element;
