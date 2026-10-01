Shows the current page path. Use at the top of page content, below SecondNavbar.

```jsx
<Breadcrumb items={[
  { label: 'Products', onClick: () => navigate('/products') },
  { label: 'Edit Product' }
]} />
```

Props: items (array of {label, onClick?}), className
