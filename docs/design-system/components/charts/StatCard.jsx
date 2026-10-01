import React from 'react';

const CSS = `
.ra-stat { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-card); padding: var(--space-5); box-shadow: var(--shadow-card); position: relative; overflow: hidden; }
.ra-stat__eyebrow { font-size: var(--text-xs); font-weight: var(--font-semibold); color: var(--text-muted); text-transform: uppercase; letter-spacing: var(--tracking-wider); margin-bottom: var(--space-2); display: flex; align-items: center; gap: var(--space-2); }
.ra-stat__value { font-size: var(--text-4xl); font-weight: var(--font-semibold); color: var(--text-primary); letter-spacing: var(--tracking-tight); line-height: 1; }
.ra-stat__sub { font-size: var(--text-sm); color: var(--text-muted); margin-top: var(--space-1); }
.ra-stat__trend { display: inline-flex; align-items: center; gap: 3px; font-size: var(--text-sm); font-weight: var(--font-medium); padding: 1px 7px; border-radius: var(--radius-full); margin-top: var(--space-2-5); }
.ra-stat__trend--up   { background: var(--color-success-50); color: var(--color-success-700); }
.ra-stat__trend--down { background: var(--color-error-50);   color: var(--color-error-700); }
.ra-stat__trend--flat { background: var(--color-neutral-100); color: var(--text-muted); }
.ra-stat__icon { position: absolute; right: var(--space-5); top: var(--space-5); width: 42px; height: 42px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; opacity: 0.15; }
.ra-stat__sparkline { margin-top: var(--space-3); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-stat-css')) {
  const s = document.createElement('style'); s.id = 'ra-stat-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

function Sparkline({ data, color = 'var(--color-primary-500)', height = 40 }) {
  if (!data || data.length < 2) return null;
  const min = Math.min(...data); const max = Math.max(...data);
  const range = max - min || 1;
  const w = 120; const h = height;
  const pts = data.map((v, i) => `${(i / (data.length - 1)) * w},${h - ((v - min) / range) * (h - 4) - 2}`).join(' ');
  const area = `0,${h} ${pts} ${w},${h}`;
  return (
    <svg width={w} height={h} viewBox={`0 0 ${w} ${h}`} style={{ overflow: 'visible' }}>
      <defs><linearGradient id="sg" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor={color} stopOpacity="0.2"/><stop offset="100%" stopColor={color} stopOpacity="0"/></linearGradient></defs>
      <polygon points={area} fill="url(#sg)" />
      <polyline points={pts} fill="none" stroke={color} strokeWidth="1.5" strokeLinejoin="round" strokeLinecap="round" />
    </svg>
  );
}

export function StatCard({ label, value, subLabel, trend, trendLabel, icon, iconColor = 'var(--color-primary-600)', sparklineData, iconBg, className = '', style }) {
  const trendDir = trend > 0 ? 'up' : trend < 0 ? 'down' : 'flat';
  return (
    <div className={['ra-stat', className].join(' ')} style={style}>
      {icon && <div className="ra-stat__icon" style={{ background: iconBg || iconColor, opacity: 1 }}><span style={{ opacity: 0.8, color: 'white' }}>{icon}</span></div>}
      <div className="ra-stat__eyebrow">{label}</div>
      <div className="ra-stat__value">{value}</div>
      {subLabel && <div className="ra-stat__sub">{subLabel}</div>}
      {trend !== undefined && (
        <div className={`ra-stat__trend ra-stat__trend--${trendDir}`}>
          {trendDir === 'up' && '↑'}
          {trendDir === 'down' && '↓'}
          {Math.abs(trend)}% {trendLabel}
        </div>
      )}
      {sparklineData && <div className="ra-stat__sparkline"><Sparkline data={sparklineData} color={iconColor} /></div>}
    </div>
  );
}
