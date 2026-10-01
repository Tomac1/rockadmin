import React from 'react';
export interface InputProps {
  type?: 'text' | 'password' | 'email' | 'number' | 'url' | 'tel' | 'search' | 'date';
  placeholder?: string;
  value?: string | number;
  defaultValue?: string | number;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  onBlur?: (e: React.FocusEvent<HTMLInputElement>) => void;
  onFocus?: (e: React.FocusEvent<HTMLInputElement>) => void;
  disabled?: boolean;
  readOnly?: boolean;
  error?: boolean;
  success?: boolean;
  size?: 'sm' | 'md' | 'lg';
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
  onRightIconClick?: () => void;
  id?: string;
  name?: string;
  autoComplete?: string;
  autoFocus?: boolean;
  className?: string;
  style?: React.CSSProperties;
}
export function Input(props: InputProps): JSX.Element;
