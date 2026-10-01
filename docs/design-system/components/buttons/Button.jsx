import React from 'react';

const CSS = `
.ra-btn {
  display: inline-flex; align-items: center; justify-content: center;
  gap: var(--space-2); font-family: var(--font-sans); font-weight: var(--font-medium);
  border-radius: var(--radius-btn); border: 1px solid transparent;
  cursor: pointer; transition: var(--transition-colors);
  text-decoration: none; white-space: nowrap; outline: none;
  position: relative; user-select: none; line-height: 1; flex-shrink: 0;
  letter-spacing: var(--tracking-tight);
}
.ra-btn:focus-visible { box-shadow: var(--shadow-focus); }
.ra-btn:disabled { cursor: not-allowed; opacity: 0.48; pointer-events: none; }
.ra-btn--sm  { height: 28px; padding: 0 10px; font-size: var(--text-xs); gap: var(--space-1-5); }
.ra-btn--md  { height: 32px; padding: 0 14px; font-size: var(--text-base); }
.ra-btn--lg  { height: 38px; padding: 0 18px; font-size: var(--text-md); }
.ra-btn--xl  { height: 46px; padding: 0 24px; font-size: var(--text-lg); }
.ra-btn--full { width: 100%; }
/* Primary */
.ra-btn--primary { background: var(--color-primary-600); color: #fff; border-color: var(--color-primary-700); }
.ra-btn--primary:hover:not(:disabled) { background: var(--color-primary-700); border-color: var(--color-primary-800); }
.ra-btn--primary:active:not(:disabled) { background: var(--color-primary-800); }
/* Secondary */
.ra-btn--secondary { background: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color-strong); box-shadow: var(--shadow-xs); }
.ra-btn--secondary:hover:not(:disabled) { background: var(--color-neutral-50); border-color: var(--color-neutral-300); }
/* Ghost */
.ra-btn--ghost { background: transparent; color: var(--text-secondary); }
.ra-btn--ghost:hover:not(:disabled) { background: var(--color-neutral-100); color: var(--text-primary); }
/* Danger */
.ra-btn--danger { background: var(--color-error-600); color: #fff; border-color: var(--color-error-700); }
.ra-btn--danger:hover:not(:disabled) { background: var(--color-error-700); }
/* Danger Ghost */
.ra-btn--danger-ghost { background: transparent; color: var(--color-error-600); }
.ra-btn--danger-ghost:hover:not(:disabled) { background: var(--color-error-50); color: var(--color-error-700); }
/* Outline */
.ra-btn--outline { background: transparent; color: var(--color-primary-600); border-color: var(--color-primary-400); }
.ra-btn--outline:hover:not(:disabled) { background: var(--color-primary-50); border-color: var(--color-primary-600); }
/* Success */
.ra-btn--success { background: var(--color-success-600); color: #fff; border-color: var(--color-success-700); }
.ra-btn--success:hover:not(:disabled) { background: var(--color-success-700); }
/* Warning */
.ra-btn--warning { background: var(--color-warning-500); color: #fff; border-color: var(--color-warning-600); }
.ra-btn--warning:hover:not(:disabled) { background: var(--color-warning-600); }
/* Nav variant (used inside dark navbars) */
.ra-btn--nav { background: transparent; color: var(--nav-primary-text); border-color: transparent; font-size: var(--text-sm); }
.ra-btn--nav:hover:not(:disabled) { background: var(--nav-primary-hover); color: var(--nav-primary-text-hover); }
.ra-btn--nav.ra-btn--active { background: var(--nav-primary-active); color: var(--nav-primary-text-active); }
/* Spinner */
.ra-btn__spinner {
  width: 13px; height: 13px; border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.25); border-top-color: currentColor;
  animation: ra-spin 0.6s linear infinite; flex-shrink: 0;
}
.ra-btn--secondary .ra-btn__spinner,
.ra-btn--ghost .ra-btn__spinner,
.ra-btn--outline .ra-btn__spinner { border-color: rgba(0,0,0,0.12); border-top-color: currentColor; }
@keyframes ra-spin { to { transform: rotate(360deg); } }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-btn-css')) {
  const s = document.createElement('style'); s.id = 'ra-btn-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function Button({
  children, variant = 'primary', size = 'md',
  disabled = false, loading = false,
  leftIcon = null, rightIcon = null,
  fullWidth = false, active = false,
  onClick, type = 'button', className = '', style, ...props
}) {
  const cls = ['ra-btn', `ra-btn--${size}`, `ra-btn--${variant}`,
    fullWidth ? 'ra-btn--full' : '', active ? 'ra-btn--active' : '', className
  ].filter(Boolean).join(' ');

  return (
    <button type={type} className={cls} disabled={disabled || loading}
      onClick={onClick} style={style} {...props}>
      {loading
        ? <span className="ra-btn__spinner" />
        : leftIcon}
      <span>{children}</span>
      {!loading && rightIcon}
    </button>
  );
}
