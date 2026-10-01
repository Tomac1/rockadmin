import React from 'react';
export interface NavProject { id: string; name: string; color?: string; }
export interface NavNotification { title: string; description?: string; time: string; unread?: boolean; }
export interface NavUser { name?: string; email?: string; avatar?: string; }
export interface NavSettingsItem { icon?: React.ReactNode; label: string; onClick?: () => void; }
export interface TopNavbarProps {
  brandName?: string;
  projects?: NavProject[];
  activeProject?: string;
  onProjectChange?: (id: string) => void;
  notifications?: NavNotification[];
  onMarkAllRead?: () => void;
  user?: NavUser;
  onSettingsClick?: () => void;
  onProfileClick?: () => void;
  onLogout?: () => void;
  settingsItems?: NavSettingsItem[];
  className?: string;
}
/** @startingPoint section="Navigation" subtitle="Primary top navigation bar with project switcher, notifications, and user menu" viewport="1280x52" */
export function TopNavbar(props: TopNavbarProps): JSX.Element;
