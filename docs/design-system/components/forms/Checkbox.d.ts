import React from 'react';
export interface CheckboxProps {
  checked?: boolean;
  defaultChecked?: boolean;
  indeterminate?: boolean;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  disabled?: boolean;
  label?: string;
  description?: string;
  id?: string;
  name?: string;
  value?: string;
  className?: string;
}
export function Checkbox(props: CheckboxProps): JSX.Element;
