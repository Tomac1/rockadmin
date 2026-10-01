import React from 'react';

const CSS = `
.ra-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45); backdrop-filter: blur(2px);
  display: flex; align-items: center; justify-content: center; padding: var(--space-4);
  z-index: var(--z-modal-backdrop); animation: ra-fade-in var(--duration-normal) var(--ease-out);
}
@keyframes ra-fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes ra-slide-up { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
.ra-modal {
  background: var(--bg-surface); border-radius: var(--radius-modal);
  box-shadow: var(--shadow-modal); width: 100%; position: relative;
  animation: ra-slide-up var(--duration-slow) var(--ease-out);
  display: flex; flex-direction: column; max-height: calc(100vh - 80px);
}
.ra-modal--sm  { max-width: 420px; }
.ra-modal--md  { max-width: 580px; }
.ra-modal--lg  { max-width: 780px; }
.ra-modal--xl  { max-width: 1000px; }
.ra-modal__header { display: flex; align-items: center; justify-content: space-between; padding: var(--space-5) var(--space-6) var(--space-4); border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ra-modal__title { font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--text-primary); }
.ra-modal__close { cursor: pointer; color: var(--text-muted); padding: 4px; border-radius: var(--radius-sm); transition: var(--transition-colors); }
.ra-modal__close:hover { background: var(--color-neutral-100); color: var(--text-primary); }
.ra-modal__body { padding: var(--space-5) var(--space-6); overflow-y: auto; flex: 1; }
.ra-modal__footer { display: flex; align-items: center; justify-content: flex-end; gap: var(--space-3); padding: var(--space-4) var(--space-6) var(--space-5); border-top: 1px solid var(--border-color); flex-shrink: 0; }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-modal-css')) {
  const s = document.createElement('style'); s.id = 'ra-modal-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function Modal({ open, onClose, title, children, footer, size = 'md', className = '' }) {
  React.useEffect(() => {
    if (open) { document.body.style.overflow = 'hidden'; }
    return () => { document.body.style.overflow = ''; };
  }, [open]);
  if (!open) return null;
  return React.createPortal(
    <div className="ra-modal-overlay" onClick={e => e.target === e.currentTarget && onClose && onClose()}>
      <div className={['ra-modal', `ra-modal--${size}`, className].filter(Boolean).join(' ')} role="dialog" aria-modal="true">
        {title !== undefined && (
          <div className="ra-modal__header">
            <h2 className="ra-modal__title">{title}</h2>
            {onClose && <span className="ra-modal__close" onClick={onClose} role="button">
              <svg width="16" height="16" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" fill="none"><line x1="3" y1="3" x2="13" y2="13"/><line x1="13" y1="3" x2="3" y2="13"/></svg>
            </span>}
          </div>
        )}
        <div className="ra-modal__body">{children}</div>
        {footer && <div className="ra-modal__footer">{footer}</div>}
      </div>
    </div>,
    document.body
  );
}
