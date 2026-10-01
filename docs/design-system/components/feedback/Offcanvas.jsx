import React from 'react';

const CSS = `
.ra-offcanvas-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(1px); z-index: var(--z-modal-backdrop); animation: ra-fade-in 150ms ease; }
.ra-offcanvas { position: fixed; top: 0; height: 100%; background: var(--bg-surface); box-shadow: var(--shadow-2xl); z-index: var(--z-modal); display: flex; flex-direction: column; transition: transform var(--duration-slow) var(--ease-out); }
.ra-offcanvas--right { right: 0; border-left: 1px solid var(--border-color); width: 420px; }
.ra-offcanvas--left  { left: 0;  border-right: 1px solid var(--border-color); width: 320px; }
.ra-offcanvas--sm { width: 320px; }
.ra-offcanvas--lg { width: 600px; }
.ra-offcanvas__header { display: flex; align-items: center; justify-content: space-between; padding: var(--space-5) var(--space-6) var(--space-4); border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ra-offcanvas__title { font-size: var(--text-xl); font-weight: var(--font-semibold); }
.ra-offcanvas__close { cursor: pointer; color: var(--text-muted); padding: 4px; border-radius: var(--radius-sm); transition: var(--transition-colors); }
.ra-offcanvas__close:hover { background: var(--color-neutral-100); color: var(--text-primary); }
.ra-offcanvas__body { padding: var(--space-6); overflow-y: auto; flex: 1; }
.ra-offcanvas__footer { padding: var(--space-4) var(--space-6); border-top: 1px solid var(--border-color); display: flex; gap: var(--space-3); justify-content: flex-end; flex-shrink: 0; }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-offcanvas-css')) {
  const s = document.createElement('style'); s.id = 'ra-offcanvas-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function Offcanvas({ open, onClose, title, children, footer, side = 'right', size = 'md', className = '' }) {
  React.useEffect(() => { if (open) document.body.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; }; }, [open]);
  if (!open) return null;
  const sizeClass = size !== 'md' ? `ra-offcanvas--${size}` : '';
  return React.createPortal(
    <>
      <div className="ra-offcanvas-overlay" onClick={onClose} />
      <div className={['ra-offcanvas', `ra-offcanvas--${side}`, sizeClass, className].filter(Boolean).join(' ')}>
        <div className="ra-offcanvas__header">
          <h3 className="ra-offcanvas__title">{title}</h3>
          <span className="ra-offcanvas__close" onClick={onClose}>
            <svg width="16" height="16" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" fill="none"><line x1="3" y1="3" x2="13" y2="13"/><line x1="13" y1="3" x2="3" y2="13"/></svg>
          </span>
        </div>
        <div className="ra-offcanvas__body">{children}</div>
        {footer && <div className="ra-offcanvas__footer">{footer}</div>}
      </div>
    </>,
    document.body
  );
}
