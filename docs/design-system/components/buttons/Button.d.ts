import React from 'react';

export interface ButtonProps {
  /** Button label */
  children: React.ReactNode;
  /** Visual style */
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'danger-ghost' | 'outline' | 'success' | 'warning' | 'nav';
  /** Size */
  size?: 'sm' | 'md' | 'lg' | 'xl';
  disabled?: boolean;
  loading?: boolean;
  /** Icon before label */
  leftIcon?: React.ReactNode;
  /** Icon after label */
  rightIcon?: React.ReactNode;
  fullWidth?: boolean;
  /** Active state (e.g. selected nav button) */
  active?: boolean;
  onClick?: (e: React.MouseEvent<HTMLButtonElement>) => void;
  type?: 'button' | 'submit' | 'reset';
  className?: string;
  style?: React.CSSProperties;
}

/** @startingPoint section="Components" subtitle="Primary action button" viewport="700x160" */
export function Button(props: ButtonProps): JSX.Element;
