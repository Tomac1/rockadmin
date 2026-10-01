import React from 'react';
export interface SidebarItem { id: string; label: string; icon?: React.ReactNode; }
export interface SidebarSection { label?: string; items: SidebarItem[]; }
export interface InpageSidebarProps { sections: SidebarSection[]; activeId?: string; onSelect?: (id: string) => void; className?: string; }
export interface InpageLayoutProps { sidebar: React.ReactNode; children: React.ReactNode; className?: string; }
export function InpageSidebar(props: InpageSidebarProps): JSX.Element;
export function InpageLayout(props: InpageLayoutProps): JSX.Element;
