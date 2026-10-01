
const { InpageSidebar, InpageLayout, FormField, Input, Select, Switch, Button, Alert } = window.RockAdminDesignSystem_fced52;

const GearIcon = () => <svg width="14" height="14" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><circle cx="8" cy="8" r="2.5"/><path d="M8 1v2M8 13v2M1 8h2M13 8h2M3.2 3.2l1.4 1.4M11.4 11.4l1.4 1.4M3.2 12.8l1.4-1.4M11.4 4.6l1.4-1.4"/></svg>;
const BellIcon = () => <svg width="14" height="14" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><path d="M8 2a5 5 0 00-5 5v3l-1.5 2h13L13 10V7a5 5 0 00-5-5z"/><path d="M6.5 14a1.5 1.5 0 003 0"/></svg>;
const ShieldIcon = () => <svg width="14" height="14" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><path d="M8 2L3 4v4c0 3 2.5 5.5 5 6 2.5-.5 5-3 5-6V4L8 2z"/></svg>;
const ApiIcon = () => <svg width="14" height="14" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><path d="M4 8h8M8 4v8"/><rect x="1" y="1" width="14" height="14" rx="2"/></svg>;
const PayIcon = () => <svg width="14" height="14" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><rect x="1" y="4" width="14" height="9" rx="1"/><line x1="1" y1="8" x2="15" y2="8"/></svg>;

const SECTIONS = [
  { label:'General', items:[
    { id:'general', label:'General', icon:<GearIcon /> },
    { id:'store', label:'Store Settings', icon:<GearIcon /> },
  ]},
  { label:'Notifications', items:[
    { id:'notifications', label:'Email Alerts', icon:<BellIcon /> },
    { id:'webhooks', label:'Webhooks', icon:<ApiIcon /> },
  ]},
  { label:'Security', items:[
    { id:'security', label:'Security', icon:<ShieldIcon /> },
    { id:'api', label:'API Keys', icon:<ApiIcon /> },
  ]},
  { label:'Integrations', items:[
    { id:'payment', label:'Payments', icon:<PayIcon /> },
    { id:'shipping', label:'Shipping', icon:<ApiIcon /> },
  ]},
];

const CONTENT = {
  general: () => (
    <div style={{display:'flex',flexDirection:'column',gap:20,maxWidth:540}}>
      <div>
        <h2 style={{fontSize:'var(--text-xl)',fontWeight:'var(--font-semibold)',marginBottom:4}}>General Settings</h2>
        <p style={{fontSize:'var(--text-sm)',color:'var(--text-muted)'}}>Basic configuration for your store.</p>
      </div>
      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:16}}>
        <FormField label="Store Name"><Input defaultValue="My E-shop" /></FormField>
        <FormField label="Store URL"><Input defaultValue="https://myeshop.cz" /></FormField>
        <FormField label="Default Language"><Select options={['Czech','English','German','Slovak']} defaultValue="Czech" /></FormField>
        <FormField label="Timezone"><Select options={['Europe/Prague','Europe/London','America/New_York']} defaultValue="Europe/Prague" /></FormField>
        <FormField label="Currency"><Select options={['CZK — Czech Koruna','EUR — Euro','USD — US Dollar']} defaultValue="CZK — Czech Koruna" /></FormField>
        <FormField label="Tax Mode"><Select options={['Prices include tax','Prices exclude tax']} /></FormField>
      </div>
      <FormField label="Admin Email" hint="Receives order notifications and system alerts"><Input type="email" defaultValue="admin@myeshop.cz" /></FormField>
      <div style={{display:'flex',gap:8,paddingTop:4}}>
        <Button>Save Changes</Button>
        <Button variant="secondary">Cancel</Button>
      </div>
    </div>
  ),
  notifications: () => (
    <div style={{display:'flex',flexDirection:'column',gap:20,maxWidth:540}}>
      <div>
        <h2 style={{fontSize:'var(--text-xl)',fontWeight:'var(--font-semibold)',marginBottom:4}}>Email Notifications</h2>
        <p style={{fontSize:'var(--text-sm)',color:'var(--text-muted)'}}>Choose which events trigger email alerts.</p>
      </div>
      {[
        ['New order received','Send an email when a new order is placed',true],
        ['Order shipped','Notify when an order status changes to Shipped',true],
        ['Low stock alert','Alert when product stock falls below threshold',true],
        ['New customer registration','Notify on new account creation',false],
        ['Failed payment','Alert on failed payment attempts',true],
        ['Export completed','Notify when a data export is ready',false],
      ].map(([label,desc,def]) => (
        <div key={label} style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'var(--space-3) var(--space-4)',background:'var(--color-neutral-50)',borderRadius:'var(--radius-md)',border:'1px solid var(--border-color)'}}>
          <div><div style={{fontSize:'var(--text-md)',fontWeight:'var(--font-medium)'}}>{label}</div><div style={{fontSize:'var(--text-sm)',color:'var(--text-muted)'}}>{desc}</div></div>
          <Switch defaultChecked={def} />
        </div>
      ))}
      <Button style={{alignSelf:'flex-start'}}>Save Changes</Button>
    </div>
  ),
  security: () => (
    <div style={{display:'flex',flexDirection:'column',gap:20,maxWidth:540}}>
      <div>
        <h2 style={{fontSize:'var(--text-xl)',fontWeight:'var(--font-semibold)',marginBottom:4}}>Security</h2>
        <p style={{fontSize:'var(--text-sm)',color:'var(--text-muted)'}}>Manage authentication and access settings.</p>
      </div>
      <Alert type="info" title="2FA is enabled">Your account is protected with two-factor authentication.</Alert>
      <div style={{display:'flex',flexDirection:'column',gap:16}}>
        <FormField label="Current Password"><Input type="password" placeholder="••••••••" /></FormField>
        <FormField label="New Password"><Input type="password" placeholder="Minimum 8 characters" /></FormField>
        <FormField label="Confirm Password"><Input type="password" placeholder="Repeat new password" /></FormField>
      </div>
      <div>
        <div style={{fontWeight:'var(--font-semibold)',marginBottom:12}}>Session Settings</div>
        <div style={{display:'flex',flexDirection:'column',gap:10}}>
          <Switch defaultChecked label="Auto-logout after 30 minutes of inactivity" />
          <Switch defaultChecked={false} label="Allow concurrent sessions" />
          <Switch defaultChecked label="Log all admin actions to audit trail" />
        </div>
      </div>
      <Button variant="primary" style={{alignSelf:'flex-start'}}>Update Password</Button>
    </div>
  ),
};

function Settings() {
  const [active, setActive] = React.useState('general');
  const wrap = { maxWidth: 'var(--content-medium-width)', margin: '0 auto' };
  const Content = CONTENT[active] || CONTENT['general'];
  return (
    <div style={wrap}><div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',overflow:'hidden',boxShadow:'var(--shadow-sm)',minHeight:480}}>
      <InpageLayout sidebar={<InpageSidebar sections={SECTIONS} activeId={active} onSelect={setActive} />}>
        <Content />
      </InpageLayout>
    </div></div>
  );
}
window.SettingsScreen = Settings;
