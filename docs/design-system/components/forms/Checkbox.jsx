import React from 'react';

const CSS = `
.ra-checkbox-label { display: inline-flex; align-items: flex-start; gap: var(--space-2); cursor: pointer; user-select: none; font-size: var(--text-md); color: var(--text-primary); }
.ra-checkbox-label--disabled { cursor: not-allowed; opacity: 0.5; }
.ra-checkbox-input { position: absolute; opacity: 0; width: 0; height: 0; }
.ra-checkbox-box {
  width: 16px; height: 16px; border: 1.5px solid var(--border-color-strong);
  border-radius: var(--radius-sm); background: var(--bg-surface); flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: var(--transition-colors); margin-top: 1px;
}
.ra-checkbox-input:focus-visible + .ra-checkbox-box { box-shadow: var(--shadow-focus); }
.ra-checkbox-input:checked + .ra-checkbox-box { background: var(--color-primary-600); border-color: var(--color-primary-600); }
.ra-checkbox-input:indeterminate + .ra-checkbox-box { background: var(--color-primary-600); border-color: var(--color-primary-600); }
.ra-checkbox-text { line-height: var(--leading-snug); }
.ra-checkbox-desc { font-size: var(--text-sm); color: var(--text-muted); margin-top: 1px; }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-checkbox-css')) {
  const s = document.createElement('style'); s.id = 'ra-checkbox-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const CheckIcon = () => (
  <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
    <polyline points="2,6 5,9 10,3"/>
  </svg>
);
const IndeterminateIcon = () => (
  <svg width="8" height="2" viewBox="0 0 8 2"><rect width="8" height="2" rx="1" fill="white"/></svg>
);

export function Checkbox({ checked, defaultChecked, indeterminate = false, onChange, disabled, label, description, id, name, value, className = '' }) {
  const inputRef = React.useRef(null);
  React.useEffect(() => { if (inputRef.current) inputRef.current.indeterminate = indeterminate; }, [indeterminate]);
  return (
    <label className={['ra-checkbox-label', disabled ? 'ra-checkbox-label--disabled' : '', className].filter(Boolean).join(' ')}>
      <input ref={inputRef} className="ra-checkbox-input" type="checkbox" checked={checked} defaultChecked={defaultChecked}
        onChange={onChange} disabled={disabled} id={id} name={name} value={value} />
      <span className="ra-checkbox-box">
        {indeterminate ? <IndeterminateIcon /> : checked ? <CheckIcon /> : null}
      </span>
      {(label || description) && (
        <span>
          {label && <span className="ra-checkbox-text">{label}</span>}
          {description && <div className="ra-checkbox-desc">{description}</div>}
        </span>
      )}
    </label>
  );
}
