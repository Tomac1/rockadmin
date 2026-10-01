import React from 'react';

const CSS = `
.ra-breadcrumb { display: flex; align-items: center; gap: var(--space-1); flex-wrap: wrap; }
.ra-breadcrumb__item { display: flex; align-items: center; gap: var(--space-1); font-size: var(--text-sm); color: var(--text-muted); }
.ra-breadcrumb__link { color: var(--text-muted); text-decoration: none; cursor: pointer; transition: color var(--duration-fast) ease; }
.ra-breadcrumb__link:hover { color: var(--text-primary); }
.ra-breadcrumb__current { color: var(--text-primary); font-weight: var(--font-medium); }
.ra-breadcrumb__sep { color: var(--text-disabled); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-bc-css')) {
  const s = document.createElement('style'); s.id = 'ra-bc-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const Sep = () => <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"><polyline points="4,2 8,6 4,10"/></svg>;

export function Breadcrumb({ items = [], className = '' }) {
  return (
    <nav aria-label="Breadcrumb" className={['ra-breadcrumb', className].join(' ')}>
      {items.map((item, i) => (
        <span key={i} className="ra-breadcrumb__item">
          {i > 0 && <span className="ra-breadcrumb__sep"><Sep /></span>}
          {i === items.length - 1
            ? <span className="ra-breadcrumb__current">{item.label}</span>
            : <span className="ra-breadcrumb__link" onClick={item.onClick}>{item.label}</span>
          }
        </span>
      ))}
    </nav>
  );
}
