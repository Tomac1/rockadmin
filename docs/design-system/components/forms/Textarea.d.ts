import React from 'react';
export interface TextareaPlaceholder { label: string; value: string; }
export interface TextareaProps {
  value?: string;
  defaultValue?: string;
  onChange?: (e: React.ChangeEvent<HTMLTextAreaElement>) => void;
  placeholder?: string;
  disabled?: boolean;
  error?: boolean;
  size?: 'sm' | 'md' | 'lg';
  rows?: number;
  /** Automatically grow height to content */
  autoResize?: boolean;
  /** Clickable placeholder chips inserted into textarea */
  placeholders?: Array<string | TextareaPlaceholder>;
  maxLength?: number;
  id?: string;
  name?: string;
  className?: string;
  style?: React.CSSProperties;
}
export function Textarea(props: TextareaProps): JSX.Element;
