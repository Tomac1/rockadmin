import React from 'react';
export interface SelectOption { value: string | number; label: string; disabled?: boolean; }
export interface SelectProps {
  options: Array<string | SelectOption>;
  value?: string | number;
  defaultValue?: string | number;
  onChange?: (e: React.ChangeEvent<HTMLSelectElement>) => void;
  disabled?: boolean;
  error?: boolean;
  size?: 'sm' | 'md' | 'lg';
  placeholder?: string;
  id?: string;
  name?: string;
  className?: string;
  style?: React.CSSProperties;
}
export function Select(props: SelectProps): JSX.Element;
