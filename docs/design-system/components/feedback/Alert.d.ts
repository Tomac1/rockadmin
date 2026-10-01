import React from 'react';
export interface AlertProps { type?: 'info'|'success'|'warning'|'error'; title?: string; children?: React.ReactNode; dismissible?: boolean; onDismiss?: () => void; size?: 'sm'|'md'; className?: string; }
export function Alert(props: AlertProps): JSX.Element;
