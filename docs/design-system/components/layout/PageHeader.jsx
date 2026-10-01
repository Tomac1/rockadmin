import React from 'react';

const CSS = `
.ra-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-4); padding: var(--space-6) 0 var(--space-5); }
.ra-page-header__left { flex: 1; min-width: 0; }
.ra-page-header__eyebrow { font-size: var(--text-sm); color: var(--text-muted); text-transform: uppercase; letter-spacing: var(--tracking-wider); font-weight: var(--font-semibold); margin-bottom: var(--space-1); }
.ra-page-header__title { font-size: var(--text-3xl); font-weight: var(--font-semibold); color: var(--text-primary); letter-spacing: var(--tracking-tight); line-height: var(--leading-tight); }
.ra-page-header__desc { font-size: var(--text-md); color: var(--text-secondary); margin-top: var(--space-1-5); line-height: var(--leading-snug); }
.ra-page-header__meta { display: flex; align-items: center; gap: var(--space-3); margin-top: var(--space-2-5); flex-wrap: wrap; }
.ra-page-header__right { display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0; padding-top: 4px; }
.ra-page-header--compact .ra-page-header__title { font-size: var(--text-2xl); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-ph-css')) {
  const s = document.createElement('style'); s.id = 'ra-ph-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function PageHeader({ title, description, eyebrow, meta, actions, compact = false, className = '' }) {
  return (
    <div className={['ra-page-header', compact ? 'ra-page-header--compact' : '', className].filter(Boolean).join(' ')}>
      <div className="ra-page-header__left">
        {eyebrow && <div className="ra-page-header__eyebrow">{eyebrow}</div>}
        <h1 className="ra-page-header__title">{title}</h1>
        {description && <p className="ra-page-header__desc">{description}</p>}
        {meta && <div className="ra-page-header__meta">{meta}</div>}
      </div>
      {actions && <div className="ra-page-header__right">{actions}</div>}
    </div>
  );
}
