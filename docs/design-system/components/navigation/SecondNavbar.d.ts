import React from 'react';
export interface NavSubItem { id: string; label: string; icon?: React.ReactNode; badge?: number; }
export interface NavSection { label?: string; items: NavSubItem[]; }
export interface NavItem {
  id: string; label: string; icon?: React.ReactNode; badge?: number;
  /** Direct children (simple dropdown) */
  children?: NavSubItem[];
  /** Sectioned dropdown */
  sections?: NavSection[];
}
export interface SecondNavbarProps {
  items: NavItem[];
  activeId?: string;
  onNavigate?: (id: string) => void;
  rightContent?: React.ReactNode;
  className?: string;
}
/** @startingPoint section="Navigation" subtitle="Project-specific secondary nav with dropdowns and live badges" viewport="1280x44" */
export function SecondNavbar(props: SecondNavbarProps): JSX.Element;
