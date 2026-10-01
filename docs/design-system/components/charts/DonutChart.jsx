import React from 'react';

const PALETTE = ['var(--color-primary-500)','var(--color-indigo-500)','var(--color-teal-500)','var(--color-orange-500)','var(--color-pink-500)','var(--color-success-500)'];

export function DonutChart({ title, subtitle, data = [], size = 180, className = '' }) {
  const [hovered, setHovered] = React.useState(null);
  const total = data.reduce((s, d) => s + d.value, 0) || 1;
  const r = size / 2 - 10; const ri = r * 0.58;
  const cx = size / 2; const cy = size / 2;

  let angle = -Math.PI / 2;
  const segments = data.map((d, i) => {
    const ratio = d.value / total;
    const sweep = ratio * 2 * Math.PI;
    const x1 = cx + r * Math.cos(angle); const y1 = cy + r * Math.sin(angle);
    angle += sweep;
    const x2 = cx + r * Math.cos(angle); const y2 = cy + r * Math.sin(angle);
    const ix1 = cx + ri * Math.cos(angle - sweep); const iy1 = cy + ri * Math.sin(angle - sweep);
    const ix2 = cx + ri * Math.cos(angle); const iy2 = cy + ri * Math.sin(angle);
    const large = sweep > Math.PI ? 1 : 0;
    return { ...d, path: `M${x1},${y1} A${r},${r} 0 ${large},1 ${x2},${y2} L${ix2},${iy2} A${ri},${ri} 0 ${large},0 ${ix1},${iy1} Z`, color: d.color || PALETTE[i % PALETTE.length], percent: Math.round(ratio * 100) };
  });

  const active = hovered !== null ? segments[hovered] : null;

  return (
    <div className={['ra-chart', className].join(' ')}>
      {(title || subtitle) && (
        <div className="ra-chart__header" style={{ marginBottom: 'var(--space-3)' }}>
          <div><div className="ra-chart__title">{title}</div>{subtitle && <div className="ra-chart__subtitle">{subtitle}</div>}</div>
        </div>
      )}
      <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-6)' }}>
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} style={{ flexShrink: 0 }}>
          {segments.map((seg, i) => (
            <path key={i} d={seg.path} fill={seg.color} opacity={hovered === null || hovered === i ? 1 : 0.45}
              style={{ cursor: 'pointer', transition: 'opacity 150ms', transform: hovered === i ? `translate(${(cx - (cx)) * 0}px)` : '' }}
              onMouseEnter={() => setHovered(i)} onMouseLeave={() => setHovered(null)} />
          ))}
          <circle cx={cx} cy={cy} r={ri - 2} fill="var(--bg-surface)" />
          <text x={cx} y={cy - 6} textAnchor="middle" fontSize="22" fontWeight="600" fill="var(--text-primary)" fontFamily="var(--font-sans)">
            {active ? active.percent + '%' : total >= 1000 ? (total/1000).toFixed(1)+'k' : total}
          </text>
          <text x={cx} y={cy + 14} textAnchor="middle" fontSize="11" fill="var(--text-muted)" fontFamily="var(--font-sans)">
            {active ? active.label : 'Total'}
          </text>
        </svg>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-2)', flex: 1 }}>
          {segments.map((seg, i) => (
            <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)', cursor: 'pointer', opacity: hovered === null || hovered === i ? 1 : 0.5, transition: 'opacity 150ms' }} onMouseEnter={() => setHovered(i)} onMouseLeave={() => setHovered(null)}>
              <span style={{ width: 10, height: 10, borderRadius: 2, background: seg.color, flexShrink: 0 }} />
              <span style={{ fontSize: 'var(--text-sm)', color: 'var(--text-secondary)', flex: 1 }}>{seg.label}</span>
              <span style={{ fontSize: 'var(--text-sm)', fontWeight: 'var(--font-semibold)', color: 'var(--text-primary)' }}>{seg.percent}%</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
