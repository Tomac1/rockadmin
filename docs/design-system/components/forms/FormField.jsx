import React from 'react';

const CSS = `
.ra-field { display: flex; flex-direction: column; gap: var(--space-1-5); }
.ra-field__label {
  font-size: var(--text-base); font-weight: var(--font-medium);
  color: var(--text-primary); display: flex; align-items: center; gap: var(--space-1-5);
}
.ra-field__required { color: var(--color-error-500); font-size: var(--text-md); line-height: 1; }
.ra-field__hint { font-size: var(--text-sm); color: var(--text-muted); line-height: var(--leading-snug); }
.ra-field__error { font-size: var(--text-sm); color: var(--color-error-600); display: flex; align-items: center; gap: var(--space-1); }
.ra-field__help-link { color: var(--text-link); cursor: pointer; text-decoration: underline; font-size: var(--text-sm); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-field-css')) {
  const s = document.createElement('style'); s.id = 'ra-field-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function FormField({ label, required, hint, error, onHintLinkClick, hintLinkLabel, children, className = '' }) {
  return (
    <div className={`ra-field ${className}`}>
      {label && (
        <label className="ra-field__label">
          <span>{label}</span>
          {required && <span className="ra-field__required">*</span>}
          {hintLinkLabel && (
            <span className="ra-field__help-link" onClick={onHintLinkClick} role="button" tabIndex={0}>
              {hintLinkLabel}
            </span>
          )}
        </label>
      )}
      {children}
      {hint && !error && <span className="ra-field__hint">{hint}</span>}
      {error && <span className="ra-field__error">
        <svg width="12" height="12" viewBox="0 0 16 16" fill="var(--color-error-500)"><circle cx="8" cy="8" r="8" opacity=".15"/><path d="M7.25 4.5h1.5v5h-1.5V4.5zm0 6h1.5v1.5h-1.5V10.5z" fill="var(--color-error-600)"/></svg>
        {error}
      </span>}
    </div>
  );
}
