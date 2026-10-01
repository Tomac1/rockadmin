import React from 'react';

const CSS = `
.ra-toast-container { position: fixed; bottom: var(--space-6); right: var(--space-6); display: flex; flex-direction: column; gap: var(--space-2); z-index: var(--z-toast); pointer-events: none; }
.ra-toast { display: flex; align-items: flex-start; gap: var(--space-3); padding: var(--space-3) var(--space-4); background: var(--color-neutral-900); color: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); pointer-events: all; min-width: 280px; max-width: 420px; animation: ra-toast-in var(--duration-slow) var(--ease-bounce); font-size: var(--text-md); }
@keyframes ra-toast-in { from { opacity:0; transform: translateX(24px); } to { opacity:1; transform: none; } }
.ra-toast--success { background: var(--color-success-700); }
.ra-toast--error   { background: var(--color-error-700); }
.ra-toast--warning { background: #92400e; }
.ra-toast--info    { background: var(--color-info-700); }
.ra-toast__icon { flex-shrink: 0; margin-top: 1px; opacity: 0.9; }
.ra-toast__body { flex: 1; }
.ra-toast__title { font-weight: var(--font-semibold); line-height: var(--leading-tight); }
.ra-toast__desc { font-size: var(--text-base); opacity: 0.8; margin-top: 2px; }
.ra-toast__close { cursor: pointer; opacity: 0.6; flex-shrink: 0; padding: 2px; }
.ra-toast__close:hover { opacity: 1; }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-toast-css')) {
  const s = document.createElement('style'); s.id = 'ra-toast-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const CTX = React.createContext(null);
export function ToastProvider({ children }) {
  const [toasts, setToasts] = React.useState([]);
  const add = React.useCallback((opts) => {
    const id = Date.now() + Math.random();
    setToasts(t => [...t, { id, ...opts }]);
    setTimeout(() => setToasts(t => t.filter(x => x.id !== id)), opts.duration || 4000);
  }, []);
  return (
    <CTX.Provider value={add}>
      {children}
      <div className="ra-toast-container">
        {toasts.map(t => (
          <div key={t.id} className={['ra-toast', t.type ? `ra-toast--${t.type}` : ''].join(' ')}>
            <span className="ra-toast__icon">
              {t.type === 'success' && <svg width="16" height="16" viewBox="0 0 16 16" stroke="white" strokeWidth="2" strokeLinecap="round" fill="none"><circle cx="8" cy="8" r="7"/><path d="M5 8l2 2 4-4"/></svg>}
              {t.type === 'error'   && <svg width="16" height="16" viewBox="0 0 16 16" stroke="white" strokeWidth="2" strokeLinecap="round" fill="none"><circle cx="8" cy="8" r="7"/><line x1="5.5" y1="5.5" x2="10.5" y2="10.5"/><line x1="10.5" y1="5.5" x2="5.5" y2="10.5"/></svg>}
              {(!t.type || t.type === 'info') && <svg width="16" height="16" viewBox="0 0 16 16" stroke="white" strokeWidth="2" strokeLinecap="round" fill="none"><circle cx="8" cy="8" r="7"/><line x1="8" y1="5" x2="8" y2="9"/><circle cx="8" cy="11.5" r=".5" fill="white"/></svg>}
            </span>
            <div className="ra-toast__body">
              {t.title && <div className="ra-toast__title">{t.title}</div>}
              {t.message && <div className="ra-toast__desc">{t.message}</div>}
              {!t.title && !t.message && <div className="ra-toast__title">{t}</div>}
            </div>
            <span className="ra-toast__close" onClick={() => setToasts(ts => ts.filter(x => x.id !== t.id))}>
              <svg width="14" height="14" viewBox="0 0 14 14" stroke="white" strokeWidth="2" strokeLinecap="round" fill="none"><line x1="2" y1="2" x2="12" y2="12"/><line x1="12" y1="2" x2="2" y2="12"/></svg>
            </span>
          </div>
        ))}
      </div>
    </CTX.Provider>
  );
}
export function useToast() { return React.useContext(CTX); }

/** Individual toast item — rendered internally by ToastProvider. Export satisfies bundler. */
export function Toast({ title, message, type }) {
  const cls = ['ra-toast', type ? `ra-toast--${type}` : ''].filter(Boolean).join(' ');
  return (
    <div className={cls}>
      <div className="ra-toast__body">
        {title && <div className="ra-toast__title">{title}</div>}
        {message && <div className="ra-toast__desc">{message}</div>}
      </div>
    </div>
  );
}
