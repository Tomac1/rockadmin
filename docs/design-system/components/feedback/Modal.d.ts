import React from 'react';
export interface ModalProps { open: boolean; onClose?: () => void; title?: string; children: React.ReactNode; footer?: React.ReactNode; size?: 'sm'|'md'|'lg'|'xl'; className?: string; }
export function Modal(props: ModalProps): JSX.Element | null;
