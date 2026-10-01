import React from 'react';

const CSS = `
.ra-switch-label { display: inline-flex; align-items: center; gap: var(--space-2-5); cursor: pointer; user-select: none; }
.ra-switch-label--disabled { cursor: not-allowed; opacity: 0.5; }
.ra-switch-input { position: absolute; opacity: 0; width: 0; height: 0; }
.ra-switch-track {
  width: 36px; height: 20px; border-radius: var(--radius-full);
  background: var(--color-neutral-300); transition: background 150ms ease;
  flex-shrink: 0; position: relative;
}
.ra-switch-input:focus-visible ~ .ra-switch-track { box-shadow: var(--shadow-focus); }
.ra-switch-input:checked ~ .ra-switch-track { background: var(--color-primary-600); }
.ra-switch-thumb {
  position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%;
  background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: transform 150ms ease;
}
.ra-switch-input:checked ~ .ra-switch-track .ra-switch-thumb { transform: translateX(16px); }
.ra-switch-text { font-size: var(--text-md); color: var(--text-primary); }
.ra-switch--sm .ra-switch-track { width: 28px; height: 16px; }
.ra-switch--sm .ra-switch-thumb { width: 12px; height: 12px; }
.ra-switch--sm .ra-switch-input:checked ~ .ra-switch-track .ra-switch-thumb { transform: translateX(12px); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-switch-css')) {
  const s = document.createElement('style'); s.id = 'ra-switch-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function Switch({ checked, defaultChecked, onChange, disabled, label, size = 'md', id, name, className = '' }) {
  return (
    <label className={['ra-switch-label', disabled ? 'ra-switch-label--disabled' : '', size !== 'md' ? `ra-switch--${size}` : '', className].filter(Boolean).join(' ')}>
      <input className="ra-switch-input" type="checkbox" role="switch" checked={checked} defaultChecked={defaultChecked} onChange={onChange} disabled={disabled} id={id} name={name} />
      <span className="ra-switch-track"><span className="ra-switch-thumb" /></span>
      {label && <span className="ra-switch-text">{label}</span>}
    </label>
  );
}
