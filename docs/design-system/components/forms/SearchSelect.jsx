import React from 'react';

const CSS = `
.ra-ss { position: relative; width: 100%; }
.ra-ss__trigger {
  width: 100%; min-height: 34px; padding: 4px var(--space-8) 4px var(--space-3);
  font-family: var(--font-sans); font-size: var(--text-md); background: var(--bg-surface);
  border: 1px solid var(--border-color-strong); border-radius: var(--radius-input);
  cursor: pointer; text-align: left; transition: var(--transition-colors); outline: none;
  display: flex; align-items: center; flex-wrap: wrap; gap: 4px; color: var(--text-primary);
}
.ra-ss__trigger:hover { border-color: var(--color-neutral-400); }
.ra-ss__trigger:focus, .ra-ss--open .ra-ss__trigger { border-color: var(--border-focus); box-shadow: var(--shadow-focus); }
.ra-ss__placeholder { color: var(--text-muted); }
.ra-ss__caret { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.ra-ss__dropdown { position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: var(--z-dropdown); background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-dropdown); box-shadow: var(--shadow-dropdown); overflow: hidden; }
.ra-ss__search { padding: var(--space-2); border-bottom: 1px solid var(--border-color); }
.ra-ss__search input { width: 100%; height: 30px; padding: 0 var(--space-3); border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: var(--text-sm); outline: none; }
.ra-ss__search input:focus { border-color: var(--border-focus); }
.ra-ss__list { max-height: 220px; overflow-y: auto; }
.ra-ss__option { padding: var(--space-2) var(--space-3); font-size: var(--text-md); cursor: pointer; display: flex; align-items: center; gap: var(--space-2); color: var(--text-primary); }
.ra-ss__option:hover { background: var(--bg-hover); }
.ra-ss__option--selected { color: var(--color-primary-600); font-weight: var(--font-medium); }
.ra-ss__option--selected::after { content: '✓'; margin-left: auto; font-size: var(--text-sm); }
.ra-ss__empty { padding: var(--space-4); text-align: center; color: var(--text-muted); font-size: var(--text-sm); }
.ra-ss__tag { display: inline-flex; align-items: center; gap: 3px; padding: 1px 6px 1px 8px; background: var(--color-primary-100); color: var(--color-primary-800); border-radius: var(--radius-full); font-size: var(--text-xs); white-space: nowrap; }
.ra-ss__tag-remove { cursor: pointer; color: var(--color-primary-600); line-height: 1; padding: 0 1px; font-size: 12px; }
.ra-ss__tag-remove:hover { color: var(--color-primary-900); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-ss-css')) {
  const s = document.createElement('style'); s.id = 'ra-ss-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const Caret = () => <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><polyline points="4,6 8,10 12,6"/></svg>;

export function SearchSelect({ options = [], value, onChange, placeholder = 'Select...', multi = false, disabled, error, className = '', style }) {
  const [open, setOpen] = React.useState(false);
  const [search, setSearch] = React.useState('');
  const ref = React.useRef(null);
  const selected = multi ? (value || []) : (value ? [value] : []);
  const filtered = options.filter(o => {
    const l = typeof o === 'object' ? o.label : o;
    return l.toLowerCase().includes(search.toLowerCase());
  });
  React.useEffect(() => {
    const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);
  const toggle = (val) => {
    if (multi) {
      const next = selected.includes(val) ? selected.filter(v => v !== val) : [...selected, val];
      onChange && onChange(next);
    } else {
      onChange && onChange(val);
      setOpen(false);
    }
  };
  const getLabel = (val) => { const o = options.find(o => (typeof o === 'object' ? o.value : o) === val); return o ? (typeof o === 'object' ? o.label : o) : val; };
  return (
    <div ref={ref} className={['ra-ss', open ? 'ra-ss--open' : '', className].filter(Boolean).join(' ')} style={style}>
      <button type="button" className="ra-ss__trigger" onClick={() => !disabled && setOpen(v => !v)} disabled={disabled}>
        {selected.length === 0 && <span className="ra-ss__placeholder">{placeholder}</span>}
        {multi ? selected.map(v => (
          <span key={v} className="ra-ss__tag">{getLabel(v)}<span className="ra-ss__tag-remove" onClick={e => { e.stopPropagation(); toggle(v); }}>×</span></span>
        )) : selected.length > 0 && <span>{getLabel(selected[0])}</span>}
        <span className="ra-ss__caret"><Caret /></span>
      </button>
      {open && (
        <div className="ra-ss__dropdown">
          <div className="ra-ss__search"><input autoFocus placeholder="Search..." value={search} onChange={e => setSearch(e.target.value)} /></div>
          <div className="ra-ss__list">
            {filtered.length === 0 ? <div className="ra-ss__empty">No options</div> :
              filtered.map(o => {
                const v = typeof o === 'object' ? o.value : o;
                const l = typeof o === 'object' ? o.label : o;
                return <div key={v} className={['ra-ss__option', selected.includes(v) ? 'ra-ss__option--selected' : ''].join(' ')} onClick={() => toggle(v)}>{l}</div>;
              })}
          </div>
        </div>
      )}
    </div>
  );
}
