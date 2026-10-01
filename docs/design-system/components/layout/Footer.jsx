import React from 'react';

const CSS = `
.ra-footer {
  width: 100%; background: var(--bg-surface);
  border-top: 1px solid var(--border-color);
  flex-shrink: 0;
}
.ra-footer__inner {
  max-width: var(--content-max-width); margin: 0 auto;
  padding: 0 var(--page-padding-x);
  height: 44px; display: flex; align-items: center;
  justify-content: space-between; gap: var(--space-4);
}
.ra-footer__copy { font-size: var(--text-sm); color: var(--text-muted); }
.ra-footer__links-btn {
  display: inline-flex; align-items: center; gap: var(--space-1-5);
  height: 28px; padding: 0 var(--space-3);
  font-family: var(--font-sans); font-size: var(--text-sm);
  color: var(--text-secondary); background: transparent;
  border: 1px solid var(--border-color); border-radius: var(--radius-md);
  cursor: pointer; transition: var(--transition-colors);
  font-weight: var(--font-medium);
}
.ra-footer__links-btn:hover { background: var(--bg-hover); color: var(--text-primary); border-color: var(--border-color-strong); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-footer-css')) {
  const s = document.createElement('style'); s.id = 'ra-footer-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const ChevronDown = () => (
  <svg width="11" height="11" viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
    <polyline points="2,4 6,8 10,4"/>
  </svg>
);

export function Footer({ copyright = 'Rock Admin', usefulLinks = [], year }) {
  const y = year || new Date().getFullYear();
  const { Dropdown } = (typeof window !== 'undefined' && window.RockAdminDesignSystem_fced52) || {};

  const defaultLinks = usefulLinks.length ? usefulLinks : [
    { label: 'Documentation' },
    { label: 'Changelog' },
    { divider: true },
    { label: 'Support' },
    { label: 'Privacy Policy' },
  ];

  const trigger = (
    <button className="ra-footer__links-btn" type="button">
      Useful Links <ChevronDown />
    </button>
  );

  return (
    <footer className="ra-footer">
      <div className="ra-footer__inner">
        <span className="ra-footer__copy">© {y} {copyright}</span>
        {Dropdown
          ? <Dropdown trigger={trigger} items={defaultLinks} align="right" />
          : trigger
        }
      </div>
    </footer>
  );
}
