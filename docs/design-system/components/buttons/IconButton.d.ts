import React from 'react';
export interface IconButtonProps {
  icon: React.ReactNode;
  size?: 'xs' | 'sm' | 'md' | 'lg';
  variant?: 'default' | 'bordered' | 'danger' | 'nav';
  disabled?: boolean;
  active?: boolean;
  onClick?: (e: React.MouseEvent<HTMLButtonElement>) => void;
  title?: string;
  className?: string;
}
export function IconButton(props: IconButtonProps): JSX.Element;
