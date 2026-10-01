import React from 'react';

const CSS = `
.ra-alert { display: flex; gap: var(--space-3); padding: var(--space-3) var(--space-4); border-radius: var(--radius-lg); border: 1px solid; }
.ra-alert--info    { background: var(--color-info-50);    border-color: #bae6fd; color: var(--color-info-700); }
.ra-alert--success { background: var(--color-success-50); border-color: var(--color-success-200); color: var(--color-success-700); }
.ra-alert--warning { background: var(--color-warning-50); border-color: var(--color-warning-200); color: var(--color-warning-700); }
.ra-alert--error   { background: var(--color-error-50);   border-color: var(--color-error-200);   color: var(--color-error-700); }
.ra-alert__icon { flex-shrink: 0; margin-top: 1px; }
.ra-alert__body { flex: 1; min-width: 0; }
.ra-alert__title { font-weight: var(--font-semibold); font-size: var(--text-md); margin-bottom: 2px; }
.ra-alert__desc { font-size: var(--text-base); line-height: var(--leading-snug); opacity: 0.9; }
.ra-alert__close { flex-shrink: 0; cursor: pointer; opacity: 0.6; padding: 2px; margin-left: auto; }
.ra-alert__close:hover { opacity: 1; }
.ra-alert--sm { padding: var(--space-2) var(--space-3); gap: var(--space-2); }
.ra-alert--sm .ra-alert__title { font-size: var(--text-base); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-alert-css')) {
  const s = document.createElement('style'); s.id = 'ra-alert-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const ICONS = {
  info: <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8" opacity=".15"/><path d="M7.25 7h1.5v5h-1.5V7zm0-2.5h1.5v1.5h-1.5V4.5z"/></svg>,
  success: <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8" opacity=".15"/><path d="M5 8l2 2 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" fill="none"/></svg>,
  warning: <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1L1 14h14L8 1z" opacity=".15"/><path d="M7.25 6.5h1.5v4h-1.5V6.5zm0 5h1.5v1.5h-1.5V11.5z"/></svg>,
  error: <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8" opacity=".15"/><path d="M5.5 5.5l5 5M10.5 5.5l-5 5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"/></svg>,
};

export function Alert({ type = 'info', title, children, dismissible = false, onDismiss, size = 'md', className = '' }) {
  const [visible, setVisible] = React.useState(true);
  if (!visible) return null;
  const dismiss = () => { setVisible(false); onDismiss && onDismiss(); };
  return (
    <div className={['ra-alert', `ra-alert--${type}`, size === 'sm' ? 'ra-alert--sm' : '', className].filter(Boolean).join(' ')} role="alert">
      <span className="ra-alert__icon">{ICONS[type]}</span>
      <div className="ra-alert__body">
        {title && <div className="ra-alert__title">{title}</div>}
        {children && <div className="ra-alert__desc">{children}</div>}
      </div>
      {dismissible && <span className="ra-alert__close" onClick={dismiss} role="button" aria-label="Dismiss">
        <svg width="14" height="14" viewBox="0 0 14 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" fill="none"><line x1="2" y1="2" x2="12" y2="12"/><line x1="12" y1="2" x2="2" y2="12"/></svg>
      </span>}
    </div>
  );
}
