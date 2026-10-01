import React from 'react';

const CSS = `
.ra-inpage-layout { display: flex; gap: 0; flex: 1; min-height: 0; }
.ra-inpage-sidebar { width: var(--inpage-sidebar-width); flex-shrink: 0; border-right: 1px solid var(--border-color); background: var(--bg-surface); padding: var(--space-4) 0; }
.ra-inpage-sidebar__section { margin-bottom: var(--space-5); }
.ra-inpage-sidebar__section-label { font-size: var(--text-xs); font-weight: var(--font-semibold); color: var(--text-muted); text-transform: uppercase; letter-spacing: var(--tracking-wider); padding: 0 var(--space-4) var(--space-1-5); }
.ra-inpage-sidebar__item { display: flex; align-items: center; gap: var(--space-2-5); padding: var(--space-2) var(--space-4); font-size: var(--text-base); color: var(--text-secondary); cursor: pointer; transition: var(--transition-colors); border-left: 2px solid transparent; text-decoration: none; }
.ra-inpage-sidebar__item:hover { background: var(--bg-hover); color: var(--text-primary); }
.ra-inpage-sidebar__item--active { color: var(--color-primary-700); background: var(--color-primary-50); border-left-color: var(--color-primary-600); font-weight: var(--font-medium); }
.ra-inpage-sidebar__item-icon { width: 16px; height: 16px; flex-shrink: 0; opacity: 0.7; }
.ra-inpage-content { flex: 1; overflow-y: auto; padding: var(--space-6); min-width: 0; }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-inpage-css')) {
  const s = document.createElement('style'); s.id = 'ra-inpage-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function InpageSidebar({ sections = [], activeId, onSelect, className = '' }) {
  return (
    <nav className={['ra-inpage-sidebar', className].join(' ')}>
      {sections.map((section, si) => (
        <div key={si} className="ra-inpage-sidebar__section">
          {section.label && <div className="ra-inpage-sidebar__section-label">{section.label}</div>}
          {section.items.map(item => (
            <div key={item.id} className={['ra-inpage-sidebar__item', activeId === item.id ? 'ra-inpage-sidebar__item--active' : ''].join(' ')} onClick={() => onSelect && onSelect(item.id)}>
              {item.icon && <span className="ra-inpage-sidebar__item-icon">{item.icon}</span>}
              <span>{item.label}</span>
            </div>
          ))}
        </div>
      ))}
    </nav>
  );
}

export function InpageLayout({ sidebar, children, className = '' }) {
  return <div className={['ra-inpage-layout', className].join(' ')}>{sidebar}<div className="ra-inpage-content">{children}</div></div>;
}
