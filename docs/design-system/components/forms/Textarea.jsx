import React from 'react';

const CSS = `
.ra-textarea {
  width: 100%; font-family: var(--font-sans); font-size: var(--text-md);
  color: var(--text-primary); background: var(--bg-surface);
  border: 1px solid var(--border-color-strong); border-radius: var(--radius-input);
  transition: var(--transition-colors); outline: none; resize: vertical;
  padding: var(--space-2-5) var(--space-3); line-height: var(--leading-normal);
  min-height: 80px;
}
.ra-textarea::placeholder { color: var(--text-muted); }
.ra-textarea:hover:not(:disabled) { border-color: var(--color-neutral-400); }
.ra-textarea:focus { border-color: var(--border-focus); box-shadow: var(--shadow-focus); }
.ra-textarea:disabled { background: var(--color-neutral-50); color: var(--text-muted); cursor: not-allowed; resize: none; }
.ra-textarea--error { border-color: var(--color-error-500) !important; }
.ra-textarea--error:focus { box-shadow: var(--shadow-focus-error) !important; }
.ra-textarea--sm { font-size: var(--text-base); min-height: 60px; }
.ra-textarea--lg { min-height: 140px; }
.ra-textarea--auto { resize: none; overflow: hidden; }
.ra-textarea-footer { display: flex; align-items: center; justify-content: space-between; margin-top: var(--space-1-5); }
.ra-textarea-placeholders { display: flex; flex-wrap: wrap; gap: var(--space-1); }
.ra-textarea-chip { font-size: var(--text-xs); padding: 2px 8px; border-radius: var(--radius-full); background: var(--color-neutral-100); color: var(--text-secondary); cursor: pointer; border: 1px solid var(--border-color); transition: var(--transition-colors); }
.ra-textarea-chip:hover { background: var(--color-primary-50); border-color: var(--color-primary-300); color: var(--color-primary-700); }
.ra-textarea-count { font-size: var(--text-xs); color: var(--text-muted); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-textarea-css')) {
  const s = document.createElement('style'); s.id = 'ra-textarea-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function Textarea({ value, defaultValue, onChange, placeholder, disabled, error, size = 'md', rows, autoResize = false, placeholders = [], maxLength, className = '', style, id, name }) {
  const ref = React.useRef(null);
  const handleChange = (e) => {
    if (autoResize && ref.current) {
      ref.current.style.height = 'auto';
      ref.current.style.height = ref.current.scrollHeight + 'px';
    }
    onChange && onChange(e);
  };
  const insertPlaceholder = (ph) => {
    if (!ref.current) return;
    const start = ref.current.selectionStart;
    const end = ref.current.selectionEnd;
    const val = ref.current.value;
    const newVal = val.slice(0, start) + ph + val.slice(end);
    const nativeInput = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value');
    nativeInput.set.call(ref.current, newVal);
    ref.current.dispatchEvent(new Event('input', { bubbles: true }));
    onChange && onChange({ target: ref.current });
  };
  const cls = ['ra-textarea', size !== 'md' ? `ra-textarea--${size}` : '', error ? 'ra-textarea--error' : '', autoResize ? 'ra-textarea--auto' : '', className].filter(Boolean).join(' ');
  return (
    <div>
      <textarea ref={ref} className={cls} value={value} defaultValue={defaultValue} onChange={handleChange}
        placeholder={placeholder} disabled={disabled} rows={rows} maxLength={maxLength}
        id={id} name={name} style={style} />
      {(placeholders.length > 0 || maxLength) && (
        <div className="ra-textarea-footer">
          <div className="ra-textarea-placeholders">
            {placeholders.map(p => (
              <span key={p.value || p} className="ra-textarea-chip" onClick={() => insertPlaceholder(p.value || p)}>
                {p.label || p}
              </span>
            ))}
          </div>
          {maxLength && <span className="ra-textarea-count">{(value || '').length}/{maxLength}</span>}
        </div>
      )}
    </div>
  );
}
