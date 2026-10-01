import React from 'react';

const CSS = `
.ra-badge {
  display: inline-flex; align-items: center; gap: 4px;
  font-family: var(--font-sans); font-weight: var(--font-medium);
  border-radius: var(--radius-badge); white-space: nowrap; line-height: 1;
  border: 1px solid transparent;
}
.ra-badge--xs { font-size: 10px; padding: 1px 5px; }
.ra-badge--sm { font-size: var(--text-xs); padding: 2px 7px; }
.ra-badge--md { font-size: var(--text-sm); padding: 3px 9px; }
.ra-badge--lg { font-size: var(--text-base); padding: 4px 11px; }
.ra-badge--dot::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
/* Color variants */
.ra-badge--gray    { background: var(--color-neutral-100); color: var(--color-neutral-700); border-color: var(--color-neutral-200); }
.ra-badge--blue    { background: var(--color-primary-50);  color: var(--color-primary-700); border-color: var(--color-primary-200); }
.ra-badge--green   { background: var(--color-success-50);  color: var(--color-success-700); border-color: var(--color-success-200); }
.ra-badge--yellow  { background: var(--color-warning-50);  color: var(--color-warning-700); border-color: var(--color-warning-200); }
.ra-badge--red     { background: var(--color-error-50);    color: var(--color-error-700);   border-color: var(--color-error-200);   }
.ra-badge--indigo  { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
.ra-badge--purple  { background: #faf5ff; color: #7c3aed; border-color: #e9d5ff; }
.ra-badge--pink    { background: #fdf2f8; color: #be185d; border-color: #fbcfe8; }
.ra-badge--teal    { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
.ra-badge--orange  { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
/* Solid variants */
.ra-badge--solid-blue   { background: var(--color-primary-600); color: white; border-color: transparent; }
.ra-badge--solid-green  { background: var(--color-success-600); color: white; border-color: transparent; }
.ra-badge--solid-red    { background: var(--color-error-600);   color: white; border-color: transparent; }
.ra-badge--solid-gray   { background: var(--color-neutral-600); color: white; border-color: transparent; }
/* Pulse dot for nav badges */
.ra-badge--pulse { animation: ra-pulse 2s cubic-bezier(0.4,0,0.6,1) infinite; }
@keyframes ra-pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-badge-css')) {
  const s = document.createElement('style'); s.id = 'ra-badge-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

export function Badge({ children, color = 'gray', size = 'sm', solid = false, dot = false, pulse = false, className = '', style }) {
  const variant = solid ? `solid-${color}` : color;
  const cls = ['ra-badge', `ra-badge--${size}`, `ra-badge--${variant}`,
    dot ? 'ra-badge--dot' : '', pulse ? 'ra-badge--pulse' : '', className].filter(Boolean).join(' ');
  return <span className={cls} style={style}>{children}</span>;
}
