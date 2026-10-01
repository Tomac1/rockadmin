import React from 'react';
export interface ConfirmModalProps { open: boolean; onClose?: () => void; onConfirm?: () => void; title?: string; message?: string; confirmLabel?: string; cancelLabel?: string; variant?: 'danger'|'warning'; loading?: boolean; }
export function ConfirmModal(props: ConfirmModalProps): JSX.Element | null;
