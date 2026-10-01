import React from 'react';
export interface OffcanvasProps { open: boolean; onClose?: () => void; title: string; children: React.ReactNode; footer?: React.ReactNode; side?: 'right'|'left'; size?: 'sm'|'md'|'lg'; className?: string; }
export function Offcanvas(props: OffcanvasProps): JSX.Element | null;
