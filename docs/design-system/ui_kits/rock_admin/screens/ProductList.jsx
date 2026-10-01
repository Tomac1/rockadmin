
const { Table, Badge, Button, PageHeader, Select, SearchSelect } = window.RockAdminDesignSystem_fced52;

const PlusIcon = () => <svg width="13" height="13" viewBox="0 0 14 14" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" fill="none"><line x1="7" y1="1" x2="7" y2="13"/><line x1="1" y1="7" x2="13" y2="7"/></svg>;
const DownloadIcon = () => <svg width="13" height="13" viewBox="0 0 14 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" fill="none"><line x1="7" y1="1" x2="7" y2="9"/><polyline points="3,5 7,9 11,5"/><line x1="1" y1="13" x2="13" y2="13"/></svg>;
const EditIcon = () => <svg width="12" height="12" viewBox="0 0 14 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" fill="none"><path d="M2,12l2-1 7-7-1-1-7,7-1,2z"/></svg>;
const TrashIcon = () => <svg width="12" height="12" viewBox="0 0 14 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" fill="none"><polyline points="2,3 12,3"/><path d="M5,3V2h4v1M3,3l1,9h6l1-9"/></svg>;
const EyeIcon = () => <svg width="12" height="12" viewBox="0 0 14 14" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><ellipse cx="7" cy="7" rx="6" ry="3.5"/><circle cx="7" cy="7" r="2"/></svg>;
const GridIcon = () => <svg width="14" height="14" viewBox="0 0 14 14" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><rect x="1" y="1" width="5" height="5" rx="1"/><rect x="8" y="1" width="5" height="5" rx="1"/><rect x="1" y="8" width="5" height="5" rx="1"/><rect x="8" y="8" width="5" height="5" rx="1"/></svg>;
const ListIcon = () => <svg width="14" height="14" viewBox="0 0 14 14" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none"><line x1="1" y1="3" x2="13" y2="3"/><line x1="1" y1="7" x2="13" y2="7"/><line x1="1" y1="11" x2="13" y2="11"/></svg>;

const ALL_PRODUCTS = [
  { id:1, name:'AirMax Pro Sneakers', sku:'SKU-001', category:'Footwear', price:'€149.00', stock:142, status:'active', visibility:'visible' },
  { id:2, name:'Leather Jacket Classic', sku:'SKU-002', category:'Clothing', price:'€389.00', stock:23, status:'active', visibility:'visible' },
  { id:3, name:'Wireless Headphones X9', sku:'SKU-003', category:'Electronics', price:'€229.00', stock:0, status:'draft', visibility:'hidden' },
  { id:4, name:'Running Shorts Elite', sku:'SKU-004', category:'Sports', price:'€59.00', stock:88, status:'active', visibility:'visible' },
  { id:5, name:'Sports Water Bottle 1L', sku:'SKU-005', category:'Sports', price:'€24.00', stock:204, status:'active', visibility:'visible' },
  { id:6, name:'Yoga Mat Premium', sku:'SKU-006', category:'Sports', price:'€79.00', stock:7, status:'active', visibility:'visible' },
  { id:7, name:'Winter Parka Jacket', sku:'SKU-007', category:'Clothing', price:'€449.00', stock:15, status:'active', visibility:'visible' },
  { id:8, name:'Bluetooth Speaker Mini', sku:'SKU-008', category:'Electronics', price:'€89.00', stock:0, status:'archived', visibility:'hidden' },
];

const COLS = [
  { key:'name', label:'Product', sortable:true, render:(v,row) => (
    <div style={{display:'flex',flexDirection:'column',gap:2}}>
      <span style={{fontWeight:'var(--font-medium)',color:'var(--text-primary)'}}>{v}</span>
      <span style={{fontSize:'var(--text-xs)',color:'var(--text-muted)',fontFamily:'var(--font-mono)'}}>{row.sku}</span>
    </div>
  )},
  { key:'category', label:'Category', render:(v) => <span style={{color:'var(--text-secondary)'}}>{v}</span> },
  { key:'price', label:'Price', sortable:true, align:'right', render:(v) => <span style={{fontWeight:'var(--font-semibold)'}}>{v}</span> },
  { key:'stock', label:'Stock', align:'right', render:(v) => (
    <span style={{fontWeight:'var(--font-medium)', color: v===0?'var(--color-error-600)':v<10?'var(--color-warning-600)':'var(--text-primary)'}}>
      {v===0 ? 'Out of stock' : v}
    </span>
  )},
  { key:'status', label:'Status', render:(v) => <Badge color={v==='active'?'green':v==='draft'?'gray':'red'} dot>{v}</Badge> },
  { key:'visibility', label:'Visibility', render:(v) => <Badge color={v==='visible'?'blue':'gray'} size="xs">{v}</Badge> },
  { key:'_actions', label:'', width:100, render:(_,row) => (
    <div className="ra-td-actions">
      <button title="Edit" style={{width:26,height:26,border:'1px solid var(--border-color)',borderRadius:4,background:'white',cursor:'pointer',display:'flex',alignItems:'center',justifyContent:'center',color:'var(--text-muted)'}}><EditIcon /></button>
      <button title="Preview" style={{width:26,height:26,border:'1px solid var(--border-color)',borderRadius:4,background:'white',cursor:'pointer',display:'flex',alignItems:'center',justifyContent:'center',color:'var(--text-muted)'}}><EyeIcon /></button>
      <button title="Delete" style={{width:26,height:26,border:'1px solid var(--border-color)',borderRadius:4,background:'white',cursor:'pointer',display:'flex',alignItems:'center',justifyContent:'center',color:'var(--color-error-400)'}}><TrashIcon /></button>
    </div>
  )},
];

function ProductList({ onNavigate }) {
  const [search, setSearch] = React.useState('');
  const [selected, setSelected] = React.useState([]);
  const [sort, setSort] = React.useState({ key:'name', dir:'asc' });
  const [page, setPage] = React.useState(1);
  const [statusFilter, setStatusFilter] = React.useState('');

  const filtered = ALL_PRODUCTS.filter(p => {
    if (search && !p.name.toLowerCase().includes(search.toLowerCase()) && !p.sku.toLowerCase().includes(search.toLowerCase())) return false;
    if (statusFilter && p.status !== statusFilter) return false;
    return true;
  });

  const sorted = [...filtered].sort((a,b) => {
    const av = a[sort.key]||''; const bv = b[sort.key]||'';
    return sort.dir==='asc' ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av));
  });

  const perPage = 5;
  const totalPages = Math.ceil(sorted.length / perPage);
  const rows = sorted.slice((page-1)*perPage, page*perPage);

  const toolbar = (
    <div style={{display:'flex',gap:8,alignItems:'center'}}>
      <Select options={[{value:'',label:'All statuses'},{value:'active',label:'Active'},{value:'draft',label:'Draft'},{value:'archived',label:'Archived'}]}
        value={statusFilter} onChange={e=>{setStatusFilter(e.target.value);setPage(1);}} style={{width:150}} />
      <Select options={['All categories','Footwear','Clothing','Electronics','Sports']} style={{width:150}} />
    </div>
  );

  const toolbarRight = (
    <div style={{display:'flex',gap:4,border:'1px solid var(--border-color)',borderRadius:'var(--radius-md)',overflow:'hidden'}}>
      <button style={{width:30,height:30,border:'none',background:'var(--color-primary-600)',cursor:'pointer',display:'flex',alignItems:'center',justifyContent:'center',color:'white'}}><ListIcon /></button>
      <button style={{width:30,height:30,border:'none',background:'transparent',cursor:'pointer',display:'flex',alignItems:'center',justifyContent:'center',color:'var(--text-muted)'}}><GridIcon /></button>
    </div>
  );

  return (
    <div>
      <PageHeader title="Products" description="Manage your product catalog."
        meta={<><Badge color="green">{ALL_PRODUCTS.filter(p=>p.status==='active').length} active</Badge><Badge color="gray">{ALL_PRODUCTS.filter(p=>p.status==='draft').length} draft</Badge></>}
        actions={<>
          <Button variant="secondary" leftIcon={<DownloadIcon />} size="sm">Export</Button>
          <Button leftIcon={<PlusIcon />} size="sm" onClick={()=>onNavigate&&onNavigate('form-add')}>New Product</Button>
        </>} />
      {selected.length > 0 && (
        <div style={{display:'flex',alignItems:'center',gap:12,padding:'10px 16px',background:'var(--color-primary-50)',border:'1px solid var(--color-primary-200)',borderRadius:'var(--radius-md)',marginBottom:12}}>
          <span style={{fontSize:'var(--text-sm)',color:'var(--color-primary-700)',fontWeight:500}}>{selected.length} selected</span>
          <div style={{width:1,height:16,background:'var(--color-primary-300)'}}/>
          <button style={{fontSize:'var(--text-sm)',color:'var(--text-link)',background:'none',border:'none',cursor:'pointer',padding:0}}>Set Active</button>
          <button style={{fontSize:'var(--text-sm)',color:'var(--text-link)',background:'none',border:'none',cursor:'pointer',padding:0}}>Archive</button>
          <button style={{fontSize:'var(--text-sm)',color:'var(--color-error-600)',background:'none',border:'none',cursor:'pointer',padding:0}}>Delete</button>
          <div style={{flex:1}}/>
          <button onClick={()=>setSelected([])} style={{fontSize:'var(--text-sm)',color:'var(--text-muted)',background:'none',border:'none',cursor:'pointer',padding:0}}>✕ Clear</button>
        </div>
      )}
      <Table columns={COLS} rows={rows} selectable selectedIds={selected} onSelectChange={setSelected}
        searchValue={search} onSearchChange={v=>{setSearch(v);setPage(1);}} searchPlaceholder="Search by name or SKU..."
        sortKey={sort.key} sortDir={sort.dir} onSortChange={(k,d)=>setSort({key:k,dir:d})}
        toolbar={toolbar} toolbarRight={toolbarRight}
        pagination={{page, pages:totalPages, total:sorted.length, info:`${(page-1)*perPage+1}–${Math.min(page*perPage,sorted.length)} of ${sorted.length} products`, onChange:setPage}} />
    </div>
  );
}
window.ProductListScreen = ProductList;
