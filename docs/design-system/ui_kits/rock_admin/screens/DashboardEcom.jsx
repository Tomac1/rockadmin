
const { StatCard, LineChart, BarChart, DonutChart, Badge, Avatar } = window.RockAdminDesignSystem_fced52;

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun'];
const LINE_SERIES = [
  { name:'Revenue (€)', data:[28400,35100,31800,47200,42600,58900], color:'var(--color-primary-500)' },
  { name:'Orders', data:[820,1050,940,1380,1220,1710], color:'var(--color-teal-500)' },
];
const BAR_SERIES = [
  { name:'This year', data:[3200,4100,3800,5200,4900,6800], color:'var(--color-primary-400)' },
  { name:'Last year', data:[2100,2900,2600,3800,3500,4900], color:'var(--color-neutral-200)' },
];
const TRAFFIC = [
  { label:'Direct', value:4200, color:'var(--color-primary-500)' },
  { label:'Organic', value:3100, color:'var(--color-teal-500)' },
  { label:'Paid', value:1800, color:'var(--color-orange-500)' },
  { label:'Social', value:900, color:'var(--color-pink-500)' },
];
const ORDERS = [
  { id:'#4821', customer:'Jan Novák', product:'AirMax Pro × 2', total:'€298', status:'processing', time:'2m ago' },
  { id:'#4820', customer:'Marie Dvořák', product:'Leather Jacket', total:'€389', status:'paid', time:'14m ago' },
  { id:'#4819', customer:'Petr Svoboda', product:'Wireless Headphones', total:'€229', status:'shipped', time:'1h ago' },
  { id:'#4818', customer:'Anna Procházková', product:'Running Shorts', total:'€59', status:'delivered', time:'2h ago' },
  { id:'#4817', customer:'Karel Novotný', product:'Sports Bag + Shorts', total:'€142', status:'refund', time:'5h ago' },
];
const STATUS_COLOR = { processing:'blue', paid:'green', shipped:'indigo', delivered:'teal', refund:'yellow' };

const EuroIcon = () => <svg width="18" height="18" viewBox="0 0 20 20" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><path d="M15 5a7 7 0 100 10"/><line x1="3" y1="8" x2="12" y2="8"/><line x1="3" y1="12" x2="11" y2="12"/></svg>;
const CartIcon = () => <svg width="18" height="18" viewBox="0 0 20 20" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><circle cx="8" cy="17" r="1.5" fill="currentColor"/><circle cx="15" cy="17" r="1.5" fill="currentColor"/><path d="M1 1h3l2 10h9l2-7H6"/></svg>;
const UsersIcon = () => <svg width="18" height="18" viewBox="0 0 20 20" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><circle cx="8" cy="7" r="4"/><path d="M2 18c0-3.86 2.69-7 6-7"/><circle cx="15" cy="9" r="3"/><path d="M13 18c0-2.5.9-4.5 2-5"/></svg>;
const TrendIcon = () => <svg width="18" height="18" viewBox="0 0 20 20" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><polyline points="2,14 7,9 11,13 18,5"/><polyline points="14,5 18,5 18,9"/></svg>;

function DashboardEcom() {
  return (
    <div>
      {/* Stat cards */}
      <div className="ra-cols-4" style={{marginBottom:20}}>
        <StatCard label="Revenue" value="€48.2k" trend={12.4} trendLabel="vs last mo" sparklineData={[28400,35100,31800,47200,42600,58900]}
          icon={<EuroIcon />} iconColor="var(--color-primary-500)" iconBg="var(--color-primary-600)" />
        <StatCard label="Orders" value="1,284" trend={8.1} trendLabel="vs last mo" sparklineData={[820,1050,940,1380,1220,1710]}
          icon={<CartIcon />} iconColor="var(--color-teal-500)" iconBg="var(--color-teal-600)" />
        <StatCard label="Customers" value="3,421" trend={-2.3} trendLabel="vs last mo"
          icon={<UsersIcon />} iconColor="var(--color-indigo-500)" iconBg="var(--color-indigo-600)" />
        <StatCard label="Conv. Rate" value="3.6%" trend={0.4} trendLabel="vs last mo" sparklineData={[3.1,3.4,3.0,3.8,3.5,3.6]}
          icon={<TrendIcon />} iconColor="var(--color-orange-500)" iconBg="var(--color-orange-500)" />
      </div>
      {/* Charts row */}
      <div className="ra-cols-2-1" style={{marginBottom:20}}>
        <LineChart title="Revenue & Orders" subtitle="Last 6 months" labels={MONTHS} series={LINE_SERIES} height={220} />
        <DonutChart title="Traffic Sources" data={TRAFFIC} size={175} />
      </div>
      {/* Orders + Bar */}
      <div className="ra-cols-3-2">
        {/* Recent orders */}
        <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',overflow:'hidden',boxShadow:'var(--shadow-xs)'}}>
          <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'var(--space-4) var(--space-5)',borderBottom:'1px solid var(--border-color)'}}>
            <span style={{fontWeight:'var(--font-semibold)',fontSize:'var(--text-md)'}}>Recent Orders</span>
            <a href="#" style={{fontSize:'var(--text-sm)',color:'var(--text-link)'}}>View all →</a>
          </div>
          <table style={{width:'100%',borderCollapse:'collapse',fontSize:'var(--text-base)'}}>
            <thead><tr style={{borderBottom:'1px solid var(--border-color)'}}>
              {['Order','Customer','Product','Total','Status','Time'].map(h=><th key={h} style={{textAlign:'left',padding:'8px 20px',fontSize:11,fontWeight:600,color:'var(--text-muted)',textTransform:'uppercase',letterSpacing:'.05em',background:'var(--color-neutral-50)'}}>{h}</th>)}
            </tr></thead>
            <tbody>
              {ORDERS.map((o,i)=>(
                <tr key={o.id} style={{borderBottom:i<ORDERS.length-1?'1px solid var(--border-color)':'none'}}>
                  <td style={{padding:'10px 20px',fontFamily:'var(--font-mono)',fontSize:'var(--text-base)',color:'var(--color-primary-600)',fontWeight:500}}>{o.id}</td>
                  <td style={{padding:'10px 20px'}}><div style={{display:'flex',alignItems:'center',gap:8}}><Avatar name={o.customer} size="xs"/><span>{o.customer}</span></div></td>
                  <td style={{padding:'10px 20px',color:'var(--text-secondary)',maxWidth:160,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>{o.product}</td>
                  <td style={{padding:'10px 20px',fontWeight:'var(--font-semibold)'}}>{o.total}</td>
                  <td style={{padding:'10px 20px'}}><Badge color={STATUS_COLOR[o.status]} dot>{o.status}</Badge></td>
                  <td style={{padding:'10px 20px',color:'var(--text-muted)',whiteSpace:'nowrap'}}>{o.time}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <BarChart title="Monthly Comparison" subtitle="This year vs last" labels={MONTHS} series={BAR_SERIES} height={300} />
      </div>
    </div>
  );
}
window.DashboardEcomScreen = DashboardEcom;
