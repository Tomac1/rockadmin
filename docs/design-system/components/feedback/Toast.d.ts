import React from 'react';
export interface ToastOptions { title?: string; message?: string; type?: 'success'|'error'|'warning'|'info'; duration?: number; }
export interface ToastProviderProps { children: React.ReactNode; }
export function ToastProvider(props: ToastProviderProps): JSX.Element;
export function useToast(): (opts: ToastOptions) => void;
