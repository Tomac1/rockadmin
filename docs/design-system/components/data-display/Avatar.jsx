import React from 'react';

const CSS = `
.ra-avatar { border-radius: 50%; object-fit: cover; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: var(--font-semibold); color: white; background: var(--color-primary-600); overflow: hidden; }
.ra-avatar--xs  { width: 20px;  height: 20px;  font-size: 9px; }
.ra-avatar--sm  { width: 28px;  height: 28px;  font-size: 11px; }
.ra-avatar--md  { width: 36px;  height: 36px;  font-size: 13px; }
.ra-avatar--lg  { width: 48px;  height: 48px;  font-size: 16px; }
.ra-avatar--xl  { width: 64px;  height: 64px;  font-size: 20px; }
.ra-avatar--2xl { width: 80px;  height: 80px;  font-size: 24px; }
.ra-avatar--sq  { border-radius: var(--radius-md); }
.ra-avatar-group { display: flex; }
.ra-avatar-group .ra-avatar { border: 2px solid var(--bg-surface); margin-left: -8px; }
.ra-avatar-group .ra-avatar:first-child { margin-left: 0; }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-avatar-css')) {
  const s = document.createElement('style'); s.id = 'ra-avatar-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const COLORS = ['#2563eb','#7c3aed','#db2777','#059669','#d97706','#dc2626','#0284c7','#4f46e5'];
function getColor(name) {
  let h = 0; for (let i = 0; i < (name||'').length; i++) h = (h * 31 + name.charCodeAt(i)) & 0xffffffff;
  return COLORS[Math.abs(h) % COLORS.length];
}
function initials(name) {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/);
  return parts.length === 1 ? parts[0][0].toUpperCase() : (parts[0][0] + parts[parts.length-1][0]).toUpperCase();
}

export function Avatar({ src, name, size = 'md', square = false, className = '', style }) {
  const cls = ['ra-avatar', `ra-avatar--${size}`, square ? 'ra-avatar--sq' : '', className].filter(Boolean).join(' ');
  if (src) return <img className={cls} src={src} alt={name || ''} style={style} />;
  return <div className={cls} style={{ background: getColor(name), ...style }}>{initials(name)}</div>;
}

export function AvatarGroup({ avatars = [], max = 4, size = 'sm' }) {
  const visible = avatars.slice(0, max);
  const remaining = avatars.length - max;
  return (
    <div className="ra-avatar-group">
      {visible.map((a, i) => <Avatar key={i} {...a} size={size} />)}
      {remaining > 0 && <Avatar name={`+${remaining}`} size={size} style={{ background: 'var(--color-neutral-400)' }} />}
    </div>
  );
}
