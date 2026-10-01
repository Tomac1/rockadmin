import React from 'react';

const CSS = `
.ra-select-wrap { position: relative; display: flex; }
.ra-select {
  width: 100%; font-family: var(--font-sans); font-size: var(--text-md);
  color: var(--text-primary); background: var(--bg-surface);
  border: 1px solid var(--border-color-strong); border-radius: var(--radius-input);
  transition: var(--transition-colors); outline: none; cursor: pointer;
  height: 34px; padding: 0 var(--space-8) 0 var(--space-3);
  appearance: none; -webkit-appearance: none;
}
.ra-select:hover:not(:disabled) { border-color: var(--color-neutral-400); }
.ra-select:focus { border-color: var(--border-focus); box-shadow: var(--shadow-focus); }
.ra-select:disabled { background: var(--color-neutral-50); color: var(--text-muted); cursor: not-allowed; }
.ra-select--error { border-color: var(--color-error-500) !important; }
.ra-select--sm { height: 28px; font-size: var(--text-sm); }
.ra-select--lg { height: 40px; font-size: var(--text-lg); }
.ra-select-caret {
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  pointer-events: none; color: var(--text-muted);
}
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-select-css')) {
  const s = document.createElement('style'); s.id = 'ra-select-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const Caret = () => (
  <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <polyline points="4,6 8,10 12,6" />
  </svg>
);

export function Select({ options = [], value, defaultValue, onChange, disabled, error, size = 'md', placeholder, className = '', style, id, name }) {
  const cls = ['ra-select', size !== 'md' ? `ra-select--${size}` : '', error ? 'ra-select--error' : '', className].filter(Boolean).join(' ');
  return (
    <div className="ra-select-wrap" style={style}>
      <select className={cls} value={value} defaultValue={defaultValue} onChange={onChange} disabled={disabled} id={id} name={name}>
        {placeholder && <option value="" disabled>{placeholder}</option>}
        {options.map(opt => {
          const v = typeof opt === 'object' ? opt.value : opt;
          const l = typeof opt === 'object' ? opt.label : opt;
          return <option key={v} value={v}>{l}</option>;
        })}
      </select>
      <span className="ra-select-caret"><Caret /></span>
    </div>
  );
}
