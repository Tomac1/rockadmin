import React from 'react';

const CSS = `
.ra-card { background: var(--bg-surface); border-radius: var(--radius-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-card); overflow: hidden; }
.ra-card--flat { box-shadow: none; }
.ra-card--hoverable { transition: box-shadow var(--duration-normal) var(--ease-default), border-color var(--duration-normal) var(--ease-default); cursor: pointer; }
.ra-card--hoverable:hover { box-shadow: var(--shadow-md); border-color: var(--color-neutral-300); }
.ra-card__header { display: flex; align-items: flex-start; justify-content: space-between; padding: var(--space-4) var(--card-padding); border-bottom: 1px solid var(--border-color); gap: var(--space-4); }
.ra-card__header--no-border { border-bottom: none; padding-bottom: 0; }
.ra-card__title { font-size: var(--text-lg); font-weight: var(--font-semibold); color: var(--text-primary); }
.ra-card__subtitle { font-size: var(--text-sm); color: var(--text-muted); margin-top: 2px; }
.ra-card__actions { display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0; }
.ra-card__body { padding: var(--card-padding); }
.ra-card__body--flush { padding: 0; }
.ra-card__footer { padding: var(--space-3) var(--card-padding); border-top: 1px solid var(--border-color); background: var(--color-neutral-50); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-card-css')) {
  const s = document.createElement('style'); s.id = 'ra-card-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function Card({ children, title, subtitle, actions, footer, flat = false, hoverable = false, flush = false, noBorder = false, className = '', style, onClick }) {
  const cls = ['ra-card', flat ? 'ra-card--flat' : '', hoverable ? 'ra-card--hoverable' : '', className].filter(Boolean).join(' ');
  return (
    <div className={cls} style={style} onClick={onClick}>
      {(title || actions) && (
        <div className={['ra-card__header', noBorder ? 'ra-card__header--no-border' : ''].join(' ')}>
          <div>
            {title && <div className="ra-card__title">{title}</div>}
            {subtitle && <div className="ra-card__subtitle">{subtitle}</div>}
          </div>
          {actions && <div className="ra-card__actions">{actions}</div>}
        </div>
      )}
      <div className={['ra-card__body', flush ? 'ra-card__body--flush' : ''].join(' ')}>{children}</div>
      {footer && <div className="ra-card__footer">{footer}</div>}
    </div>
  );
}
