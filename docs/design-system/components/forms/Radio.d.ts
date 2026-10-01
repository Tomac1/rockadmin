import React from 'react';
export interface RadioProps { checked?: boolean; onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void; disabled?: boolean; label?: string; description?: string; id?: string; name?: string; value?: string; className?: string; }
export interface RadioGroupProps { children: React.ReactNode; horizontal?: boolean; className?: string; }
export function Radio(props: RadioProps): JSX.Element;
export function RadioGroup(props: RadioGroupProps): JSX.Element;
