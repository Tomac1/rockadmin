import React from 'react';

const CSS = `
.ra-input-wrap { position: relative; display: flex; align-items: center; }
.ra-input {
  width: 100%; font-family: var(--font-sans); font-size: var(--text-md);
  color: var(--text-primary); background: var(--bg-surface);
  border: 1px solid var(--border-color-strong); border-radius: var(--radius-input);
  transition: var(--transition-colors); outline: none;
  height: 34px; padding: 0 var(--space-3);
}
.ra-input::placeholder { color: var(--text-muted); }
.ra-input:hover:not(:disabled):not(:read-only) { border-color: var(--color-neutral-400); }
.ra-input:focus { border-color: var(--border-focus); box-shadow: var(--shadow-focus); }
.ra-input:disabled { background: var(--color-neutral-50); color: var(--text-muted); cursor: not-allowed; }
.ra-input:read-only { background: var(--color-neutral-50); }
.ra-input--error { border-color: var(--color-error-500) !important; }
.ra-input--error:focus { box-shadow: var(--shadow-focus-error) !important; }
.ra-input--success { border-color: var(--color-success-500) !important; }
.ra-input--lg { height: 40px; font-size: var(--text-lg); padding: 0 var(--space-4); }
.ra-input--sm { height: 28px; font-size: var(--text-sm); padding: 0 var(--space-2-5); }
.ra-input--has-left  { padding-left: 34px; }
.ra-input--has-right { padding-right: 34px; }
.ra-input-icon { position: absolute; display: flex; align-items: center; color: var(--text-muted); pointer-events: none; }
.ra-input-icon--left  { left: 10px; }
.ra-input-icon--right { right: 10px; }
.ra-input-icon--clickable { pointer-events: auto; cursor: pointer; }
.ra-input-icon--clickable:hover { color: var(--text-primary); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-input-css')) {
  const s = document.createElement('style'); s.id = 'ra-input-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function Input({
  type = 'text', placeholder, value, defaultValue, onChange, onBlur, onFocus,
  disabled = false, readOnly = false, error = false, success = false, size = 'md',
  leftIcon, rightIcon, onRightIconClick, className = '', style, id, name, autoComplete, autoFocus, ...props
}) {
  const cls = ['ra-input',
    size !== 'md' ? `ra-input--${size}` : '',
    error ? 'ra-input--error' : '',
    success ? 'ra-input--success' : '',
    leftIcon ? 'ra-input--has-left' : '',
    (rightIcon) ? 'ra-input--has-right' : '',
    className
  ].filter(Boolean).join(' ');

  return (
    <div className="ra-input-wrap" style={style}>
      {leftIcon && <span className="ra-input-icon ra-input-icon--left">{leftIcon}</span>}
      <input className={cls} type={type} placeholder={placeholder} value={value}
        defaultValue={defaultValue} onChange={onChange} onBlur={onBlur} onFocus={onFocus}
        disabled={disabled} readOnly={readOnly} id={id} name={name}
        autoComplete={autoComplete} autoFocus={autoFocus} {...props} />
      {rightIcon && (
        <span className={`ra-input-icon ra-input-icon--right ${onRightIconClick ? 'ra-input-icon--clickable' : ''}`}
          onClick={onRightIconClick}>
          {rightIcon}
        </span>
      )}
    </div>
  );
}
