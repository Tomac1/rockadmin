import React from 'react';

const CSS = `
.ra-chart { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-card); padding: var(--space-5); box-shadow: var(--shadow-card); }
.ra-chart__header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-4); }
.ra-chart__title { font-weight: var(--font-semibold); font-size: var(--text-lg); color: var(--text-primary); }
.ra-chart__subtitle { font-size: var(--text-sm); color: var(--text-muted); margin-top: 2px; }
.ra-chart__legend { display: flex; gap: var(--space-4); flex-wrap: wrap; }
.ra-chart__legend-item { display: flex; align-items: center; gap: var(--space-1-5); font-size: var(--text-sm); color: var(--text-secondary); }
.ra-chart__legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ra-chart-tooltip { position: absolute; background: var(--color-neutral-900); color: white; font-size: var(--text-xs); padding: 4px 8px; border-radius: var(--radius-md); pointer-events: none; white-space: nowrap; box-shadow: var(--shadow-lg); transform: translate(-50%, -110%); z-index: 10; }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-chart-css')) {
  const s = document.createElement('style'); s.id = 'ra-chart-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const PALETTE = ['var(--color-primary-500)','var(--color-indigo-500)','var(--color-teal-500)','var(--color-orange-500)','var(--color-pink-500)'];

export function LineChart({ title, subtitle, labels = [], series = [], height = 220, className = '' }) {
  const [tooltip, setTooltip] = React.useState(null);
  const padL = 40, padR = 16, padT = 8, padB = 36;
  const svgRef = React.useRef(null);
  const allVals = series.flatMap(s => s.data);
  const minV = Math.min(0, ...allVals); const maxV = Math.max(...allVals) * 1.1 || 10;
  const range = maxV - minV || 1;
  const n = labels.length;
  const [w, setW] = React.useState(600);
  React.useEffect(() => {
    const ob = new ResizeObserver(e => setW(e[0].contentRect.width));
    if (svgRef.current) ob.observe(svgRef.current);
    return () => ob.disconnect();
  }, []);
  const cw = w - padL - padR; const ch = height - padT - padB;
  const xp = i => padL + (i / (n - 1 || 1)) * cw;
  const yp = v => padT + ch - ((v - minV) / range) * ch;

  const ticks = 5;
  const yTicks = Array.from({ length: ticks }, (_, i) => minV + (range * i) / (ticks - 1));
  const fmt = v => v >= 1000 ? (v/1000).toFixed(1)+'k' : Math.round(v);

  return (
    <div className={['ra-chart', className].join(' ')}>
      <div className="ra-chart__header">
        <div><div className="ra-chart__title">{title}</div>{subtitle && <div className="ra-chart__subtitle">{subtitle}</div>}</div>
        <div className="ra-chart__legend">
          {series.map((s, i) => <div key={i} className="ra-chart__legend-item"><span className="ra-chart__legend-dot" style={{ background: s.color || PALETTE[i % PALETTE.length] }}/>{s.name}</div>)}
        </div>
      </div>
      <div ref={svgRef} style={{ position: 'relative' }}>
        <svg width="100%" height={height} viewBox={`0 0 ${w} ${height}`}>
          {/* Y-axis grid + labels */}
          {yTicks.map((v, i) => (
            <g key={i}>
              <line x1={padL} y1={yp(v)} x2={w - padR} y2={yp(v)} stroke="var(--color-neutral-200)" strokeWidth="1" strokeDasharray={i===0?'':'3,3'} />
              <text x={padL - 6} y={yp(v) + 4} textAnchor="end" fontSize="11" fill="var(--color-neutral-400)">{fmt(v)}</text>
            </g>
          ))}
          {/* X-axis labels */}
          {labels.map((l, i) => (
            <text key={i} x={xp(i)} y={height - 8} textAnchor="middle" fontSize="11" fill="var(--color-neutral-400)">{l}</text>
          ))}
          {/* Series */}
          {series.map((s, si) => {
            const color = s.color || PALETTE[si % PALETTE.length];
            const pts = s.data.map((v, i) => `${xp(i)},${yp(v)}`).join(' ');
            const area = `${padL},${yp(0)} ${pts} ${xp(s.data.length-1)},${yp(0)}`;
            return (
              <g key={si}>
                <defs><linearGradient id={`lg${si}`} x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor={color} stopOpacity="0.18"/><stop offset="100%" stopColor={color} stopOpacity="0"/></linearGradient></defs>
                <polygon points={area} fill={`url(#lg${si})`} />
                <polyline points={pts} fill="none" stroke={color} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
                {s.data.map((v, i) => (
                  <circle key={i} cx={xp(i)} cy={yp(v)} r="3" fill={color} stroke="white" strokeWidth="1.5"
                    style={{ cursor: 'pointer' }}
                    onMouseEnter={() => setTooltip({ x: xp(i), y: yp(v), label: labels[i], value: v, name: s.name, color })}
                    onMouseLeave={() => setTooltip(null)} />
                ))}
              </g>
            );
          })}
        </svg>
        {tooltip && (
          <div className="ra-chart-tooltip" style={{ left: tooltip.x, top: tooltip.y }}>
            <strong>{tooltip.label}</strong> · {tooltip.name}: {fmt(tooltip.value)}
          </div>
        )}
      </div>
    </div>
  );
}
