import React from 'react';

const CSS = `
.ra-dropdown { position: absolute; top: calc(100% + 4px); z-index: var(--z-dropdown); background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-dropdown); box-shadow: var(--shadow-dropdown); min-width: 180px; overflow: hidden; animation: ra-dd-in 120ms var(--ease-out); }
.ra-dropdown--left  { left: 0; }
.ra-dropdown--right { right: 0; }
.ra-dropdown__section { padding: var(--space-1) 0; }
.ra-dropdown__section + .ra-dropdown__section { border-top: 1px solid var(--border-color); }
.ra-dropdown__label { font-size: 10px; font-weight: var(--font-semibold); color: var(--text-muted); text-transform: uppercase; letter-spacing: var(--tracking-wider); padding: var(--space-2) var(--space-4) var(--space-1); }
.ra-dropdown__item { display: flex; align-items: center; gap: var(--space-2-5); padding: var(--space-2) var(--space-4); font-size: var(--text-base); color: var(--text-primary); cursor: pointer; transition: background var(--duration-fast) ease; text-decoration: none; }
.ra-dropdown__item:hover { background: var(--bg-hover); }
.ra-dropdown__item--active { color: var(--color-primary-700); font-weight: var(--font-medium); background: var(--color-primary-50); }
.ra-dropdown__item--danger { color: var(--color-error-600); }
.ra-dropdown__item--danger:hover { background: var(--color-error-50); }
.ra-dropdown__item-icon { width: 15px; height: 15px; color: var(--text-muted); flex-shrink: 0; display: flex; align-items: center; }
.ra-dropdown__divider { height: 1px; background: var(--border-color); margin: var(--space-1) 0; }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-dropdown-css')) {
  const s = document.createElement('style'); s.id = 'ra-dropdown-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function Dropdown({ trigger, items = [], sections, align = 'left', open: controlledOpen, onOpenChange, className = '' }) {
  const [internalOpen, setInternalOpen] = React.useState(false);
  const open = controlledOpen !== undefined ? controlledOpen : internalOpen;
  const setOpen = (v) => { setInternalOpen(v); onOpenChange && onOpenChange(v); };
  const ref = React.useRef(null);

  React.useEffect(() => {
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);

  const renderItems = (list) => list.map((item, i) => {
    if (item.divider) return <div key={i} className="ra-dropdown__divider" />;
    const cls = ['ra-dropdown__item', item.active ? 'ra-dropdown__item--active' : '', item.danger ? 'ra-dropdown__item--danger' : ''].filter(Boolean).join(' ');
    return (
      <div key={i} className={cls} onClick={() => { item.onClick && item.onClick(); setOpen(false); }}>
        {item.icon && <span className="ra-dropdown__item-icon">{item.icon}</span>}
        <span>{item.label}</span>
      </div>
    );
  });

  return (
    <div ref={ref} style={{ position: 'relative', display: 'inline-flex' }} className={className}>
      <div onClick={() => setOpen(v => !v)} style={{ cursor: 'pointer' }}>{trigger}</div>
      {open && (
        <div className={`ra-dropdown ra-dropdown--${align}`}>
          {sections
            ? sections.map((sec, i) => (
                <div key={i} className="ra-dropdown__section">
                  {sec.label && <div className="ra-dropdown__label">{sec.label}</div>}
                  {renderItems(sec.items)}
                </div>
              ))
            : <div className="ra-dropdown__section">{renderItems(items)}</div>
          }
        </div>
      )}
    </div>
  );
}
