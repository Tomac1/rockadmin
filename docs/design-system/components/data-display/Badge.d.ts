import React from 'react';
export type BadgeColor = 'gray'|'blue'|'green'|'yellow'|'red'|'indigo'|'purple'|'pink'|'teal'|'orange';
export interface BadgeProps {
  children?: React.ReactNode;
  color?: BadgeColor;
  size?: 'xs'|'sm'|'md'|'lg';
  solid?: boolean;
  /** Show a colored dot before the label */
  dot?: boolean;
  /** Animate opacity (for live status) */
  pulse?: boolean;
  className?: string;
  style?: React.CSSProperties;
}
export function Badge(props: BadgeProps): JSX.Element;
