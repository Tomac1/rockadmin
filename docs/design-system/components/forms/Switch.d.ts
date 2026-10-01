import React from 'react';
export interface SwitchProps { checked?: boolean; defaultChecked?: boolean; onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void; disabled?: boolean; label?: string; size?: 'sm' | 'md'; id?: string; name?: string; className?: string; }
export function Switch(props: SwitchProps): JSX.Element;
