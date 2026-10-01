import React from 'react';
export interface FormFieldProps {
  label?: string;
  required?: boolean;
  hint?: string;
  error?: string;
  /** Text of the link shown in the label area */
  hintLinkLabel?: string;
  onHintLinkClick?: () => void;
  children: React.ReactNode;
  className?: string;
}
export function FormField(props: FormFieldProps): JSX.Element;
