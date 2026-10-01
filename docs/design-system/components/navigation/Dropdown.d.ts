import React from 'react';
export interface DropdownItem {
  label: string;
  onClick?: () => void;
  icon?: React.ReactNode;
  active?: boolean;
  danger?: boolean;
  divider?: boolean;
}
export interface DropdownSection { label?: string; items: DropdownItem[]; }
export interface DropdownProps {
  trigger: React.ReactNode;
  items?: DropdownItem[];
  sections?: DropdownSection[];
  align?: 'left' | 'right';
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  className?: string;
}
export function Dropdown(props: DropdownProps): JSX.Element;
