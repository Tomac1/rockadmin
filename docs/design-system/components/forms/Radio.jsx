import React from 'react';

const CSS = `
.ra-radio-label { display: inline-flex; align-items: flex-start; gap: var(--space-2); cursor: pointer; user-select: none; font-size: var(--text-md); color: var(--text-primary); }
.ra-radio-label--disabled { cursor: not-allowed; opacity: 0.5; }
.ra-radio-input { position: absolute; opacity: 0; width: 0; height: 0; }
.ra-radio-dot {
  width: 16px; height: 16px; border-radius: 50%; border: 1.5px solid var(--border-color-strong);
  background: var(--bg-surface); flex-shrink: 0; display: flex; align-items: center; justify-content: center;
  transition: var(--transition-colors); margin-top: 1px;
}
.ra-radio-input:focus-visible + .ra-radio-dot { box-shadow: var(--shadow-focus); }
.ra-radio-input:checked + .ra-radio-dot { border-color: var(--color-primary-600); background: var(--color-primary-600); }
.ra-radio-dot::after { content: ''; width: 6px; height: 6px; border-radius: 50%; background: white; opacity: 0; transition: opacity 100ms; }
.ra-radio-input:checked + .ra-radio-dot::after { opacity: 1; }
.ra-radio-group { display: flex; flex-direction: column; gap: var(--space-2-5); }
.ra-radio-group--horizontal { flex-direction: row; flex-wrap: wrap; gap: var(--space-4); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-radio-css')) {
  const s = document.createElement('style'); s.id = 'ra-radio-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function Radio({ checked, onChange, disabled, label, description, id, name, value, className = '' }) {
  return (
    <label className={['ra-radio-label', disabled ? 'ra-radio-label--disabled' : '', className].filter(Boolean).join(' ')}>
      <input className="ra-radio-input" type="radio" checked={checked} onChange={onChange} disabled={disabled} id={id} name={name} value={value} />
      <span className="ra-radio-dot" />
      {(label || description) && (
        <span>
          {label && <span>{label}</span>}
          {description && <div style={{fontSize:'var(--text-sm)',color:'var(--text-muted)',marginTop:'1px'}}>{description}</div>}
        </span>
      )}
    </label>
  );
}

export function RadioGroup({ children, horizontal = false, className = '' }) {
  return <div className={['ra-radio-group', horizontal ? 'ra-radio-group--horizontal' : '', className].filter(Boolean).join(' ')}>{children}</div>;
}
