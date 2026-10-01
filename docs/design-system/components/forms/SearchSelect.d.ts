import React from 'react';
export interface SearchSelectProps {
  options: Array<string | { value: string; label: string }>;
  value?: string | string[];
  onChange?: (value: string | string[]) => void;
  placeholder?: string;
  /** Allow selecting multiple values */
  multi?: boolean;
  disabled?: boolean;
  error?: boolean;
  className?: string;
  style?: React.CSSProperties;
}
export function SearchSelect(props: SearchSelectProps): JSX.Element;
