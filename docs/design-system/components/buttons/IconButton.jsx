import React from 'react';

const CSS = `
.ra-icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  border: 1px solid transparent; border-radius: var(--radius-md);
  cursor: pointer; transition: var(--transition-colors);
  outline: none; flex-shrink: 0; color: var(--text-secondary);
  background: transparent; padding: 0;
}
.ra-icon-btn:focus-visible { box-shadow: var(--shadow-focus); }
.ra-icon-btn:disabled { cursor: not-allowed; opacity: 0.45; pointer-events: none; }
.ra-icon-btn--xs { width: 22px; height: 22px; border-radius: var(--radius-sm); }
.ra-icon-btn--sm { width: 28px; height: 28px; }
.ra-icon-btn--md { width: 32px; height: 32px; }
.ra-icon-btn--lg { width: 38px; height: 38px; }
.ra-icon-btn--default:hover:not(:disabled) { background: var(--color-neutral-100); color: var(--text-primary); }
.ra-icon-btn--bordered { border-color: var(--border-color-strong); background: var(--bg-surface); box-shadow: var(--shadow-xs); }
.ra-icon-btn--bordered:hover:not(:disabled) { background: var(--color-neutral-50); }
.ra-icon-btn--danger:hover:not(:disabled) { background: var(--color-error-50); color: var(--color-error-600); }
.ra-icon-btn--nav { color: var(--nav-primary-text); border-radius: var(--radius-md); }
.ra-icon-btn--nav:hover:not(:disabled) { background: var(--nav-primary-hover); color: var(--nav-primary-text-hover); }
.ra-icon-btn--active { background: var(--nav-primary-active); color: var(--nav-primary-text-active); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-icon-btn-css')) {
  const s = document.createElement('style'); s.id = 'ra-icon-btn-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function IconButton({ icon, size = 'md', variant = 'default', disabled = false, active = false, onClick, title, className = '', ...props }) {
  const cls = ['ra-icon-btn', `ra-icon-btn--${size}`, `ra-icon-btn--${variant}`,
    active ? 'ra-icon-btn--active' : '', className].filter(Boolean).join(' ');
  return (
    <button type="button" className={cls} disabled={disabled} onClick={onClick} title={title} {...props}>
      {icon}
    </button>
  );
}
