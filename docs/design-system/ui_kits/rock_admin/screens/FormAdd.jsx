
const { PageHeader, FormField, Input, Select, Textarea, Switch, Button, Breadcrumb, Badge, Checkbox, SearchSelect } = window.RockAdminDesignSystem_fced52;

function FormAdd({ onNavigate }) {
  // Narrow container for form pages
  const narrowWrap = { maxWidth: 'var(--content-medium-width)', margin: '0 auto' };
  const [status, setStatus] = React.useState('draft');
  const [featured, setFeatured] = React.useState(false);
  const [saleEnabled, setSaleEnabled] = React.useState(false);
  const [saved, setSaved] = React.useState(false);

  const save = () => { setSaved(true); setTimeout(()=>setSaved(false),2500); };

  const ArrowLeftIcon = () => <svg width="14" height="14" viewBox="0 0 14 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" fill="none"><polyline points="9,2 4,7 9,12"/></svg>;

  return (
    <div style={narrowWrap}>
      <div style={{marginBottom:4}}><Breadcrumb items={[{label:'Products',onClick:()=>onNavigate&&onNavigate('products')},{label:'New Product'}]} /></div>
      <PageHeader title="Add Product" description="Create a new product in your catalog."
        actions={<>
          <Button variant="secondary" leftIcon={<ArrowLeftIcon />} size="sm" onClick={()=>onNavigate&&onNavigate('products')}>Back</Button>
          <Button variant="secondary" size="sm">Save as Draft</Button>
          <Button size="sm" loading={false} onClick={save}>{saved?'✓ Saved':'Publish'}</Button>
        </>} />

      <div className="ra-form-layout">
        {/* Main column */}
        <div style={{display:'flex',flexDirection:'column',gap:16}}>
          {/* Basic Info Card */}
          <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',padding:'var(--space-5)',boxShadow:'var(--shadow-xs)'}}>
            <div style={{fontWeight:'var(--font-semibold)',fontSize:'var(--text-md)',marginBottom:16,paddingBottom:12,borderBottom:'1px solid var(--border-color)'}}>Basic Information</div>
            <div style={{display:'flex',flexDirection:'column',gap:14}}>
              <FormField label="Product Name" required><Input placeholder="e.g. AirMax Pro Sneakers" /></FormField>
              <FormField label="Slug" hint="Used in URL: /products/your-slug"><Input placeholder="airmax-pro-sneakers" /></FormField>
              <FormField label="Short Description"><Textarea placeholder="Brief product description shown in listings..." rows={2} /></FormField>
              <FormField label="Full Description" hint="Supports placeholders"
                hintLinkLabel="?" onHintLinkClick={()=>alert('Use {name}, {sku} as dynamic placeholders')}>
                <Textarea placeholder="Detailed product description..." rows={4}
                  placeholders={[{label:'{name}',value:'{name}'},{label:'{sku}',value:'{sku}'},{label:'{category}',value:'{category}'}]} />
              </FormField>
            </div>
          </div>
          {/* Pricing Card */}
          <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',padding:'var(--space-5)',boxShadow:'var(--shadow-xs)'}}>
            <div style={{fontWeight:'var(--font-semibold)',fontSize:'var(--text-md)',marginBottom:16,paddingBottom:12,borderBottom:'1px solid var(--border-color)'}}>Pricing</div>
            <div className="ra-cols-2" style={{gap:14}}>
              <FormField label="Price (CZK)" required><Input type="number" placeholder="0.00" /></FormField>
              <FormField label="Price (EUR)"><Input type="number" placeholder="0.00" /></FormField>
              <FormField label="Sale Price" hint={saleEnabled?'Shown as discounted':'Enable sale to set'}>
                <div style={{display:'flex',gap:8,alignItems:'center'}}>
                  <Switch checked={saleEnabled} onChange={e=>setSaleEnabled(e.target.checked)} />
                  <Input type="number" placeholder="0.00" disabled={!saleEnabled} style={{flex:1}} />
                </div>
              </FormField>
              <FormField label="Tax Class"><Select options={['Standard 21%','Reduced 12%','Zero 0%']} /></FormField>
            </div>
          </div>
          {/* Inventory Card */}
          <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',padding:'var(--space-5)',boxShadow:'var(--shadow-xs)'}}>
            <div style={{fontWeight:'var(--font-semibold)',fontSize:'var(--text-md)',marginBottom:16,paddingBottom:12,borderBottom:'1px solid var(--border-color)'}}>Inventory & Shipping</div>
            <div className="ra-cols-2" style={{gap:14}}>
              <FormField label="SKU" hint="Unique identifier"><Input placeholder="SKU-000" /></FormField>
              <FormField label="Barcode"><Input placeholder="EAN / UPC" /></FormField>
              <FormField label="Stock Quantity"><Input type="number" placeholder="0" /></FormField>
              <FormField label="Low Stock Threshold"><Input type="number" placeholder="5" /></FormField>
              <FormField label="Weight (g)"><Input type="number" placeholder="0" /></FormField>
              <FormField label="Dimensions (cm)"><Input placeholder="L × W × H" /></FormField>
            </div>
          </div>
        </div>

        {/* Right sidebar */}
        <div style={{display:'flex',flexDirection:'column',gap:16}}>
          {/* Status */}
          <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',padding:'var(--space-5)',boxShadow:'var(--shadow-xs)'}}>
            <div style={{fontWeight:'var(--font-semibold)',marginBottom:14,paddingBottom:10,borderBottom:'1px solid var(--border-color)'}}>Status & Visibility</div>
            <div style={{display:'flex',flexDirection:'column',gap:12}}>
              <FormField label="Status"><Select value={status} onChange={e=>setStatus(e.target.value)} options={[{value:'draft',label:'Draft'},{value:'active',label:'Active'},{value:'archived',label:'Archived'}]} /></FormField>
              <FormField label="Visibility"><Select options={['Visible','Hidden','Featured Only']} /></FormField>
              <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',paddingTop:4}}>
                <span style={{fontSize:'var(--text-md)'}}>Featured product</span>
                <Switch checked={featured} onChange={e=>setFeatured(e.target.checked)} />
              </div>
              <div style={{display:'flex',alignItems:'center',justifyContent:'space-between'}}>
                <span style={{fontSize:'var(--text-md)'}}>Allow reviews</span>
                <Switch defaultChecked />
              </div>
            </div>
          </div>
          {/* Categories & Tags */}
          <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',padding:'var(--space-5)',boxShadow:'var(--shadow-xs)'}}>
            <div style={{fontWeight:'var(--font-semibold)',marginBottom:14,paddingBottom:10,borderBottom:'1px solid var(--border-color)'}}>Classification</div>
            <div style={{display:'flex',flexDirection:'column',gap:12}}>
              <FormField label="Category">
                <SearchSelect options={['Electronics','Clothing','Footwear','Sports','Books','Toys','Home']} placeholder="Select category..." />
              </FormField>
              <FormField label="Tags">
                <SearchSelect options={['featured','sale','new','bestseller','limited','bundle']} placeholder="Add tags..." multi />
              </FormField>
              <FormField label="Brand"><Input placeholder="Brand name" /></FormField>
            </div>
          </div>
          {/* SEO */}
          <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',padding:'var(--space-5)',boxShadow:'var(--shadow-xs)'}}>
            <div style={{fontWeight:'var(--font-semibold)',marginBottom:14,paddingBottom:10,borderBottom:'1px solid var(--border-color)'}}>SEO</div>
            <div style={{display:'flex',flexDirection:'column',gap:12}}>
              <FormField label="Meta Title" hint="60 chars max"><Input placeholder="Product page title..." /></FormField>
              <FormField label="Meta Description" hint="160 chars max"><Textarea placeholder="Description for search engines..." rows={3} maxLength={160} /></FormField>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
window.FormAddScreen = FormAdd;
