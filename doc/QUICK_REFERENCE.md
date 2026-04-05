# ⚡ CRUD Table - Quick Reference Card

## 🚀 30-Second Setup

```tsx
import { CrudTable } from '@/Components/crud';

export function MyPage() {
  return (
    <CrudTable config={{
      resource: 'users',              // API endpoint: /api/crud/users
      title: 'Users',
      columns: [
        { key: 'id', label: 'ID', editable: false },
        { key: 'name', label: 'Name', editable: true },
        { key: 'email', label: 'Email', editable: true },
      ],
      creatable: true,
      editable: true,
      deletable: true,
    }} />
  );
}
```

## 📋 Column Types

```ts
type ColumnType = 'string' | 'number' | 'boolean' | 'date' | 'datetime' | 'custom';
```

**Examples:**
```ts
{ key: 'name', type: 'string' }                    // Text input
{ key: 'price', type: 'number' }                   // Number input
{ key: 'active', type: 'boolean' }                 // Checkbox
{ key: 'birthday', type: 'date' }                  // Date picker
{ key: 'created_at', type: 'datetime' }            // DateTime picker
```

## 🎛️ Configuration Options

```typescript
interface CrudTableConfig {
  resource: string;              ✓ Required - API resource name
  columns: ColumnDef[];          ✓ Required - Column definitions
  title?: string;                Optional - Page title
  description?: string;          Optional - Page subtitle
  creatable?: boolean;           Optional - Show "New" button (default: false)
  editable?: boolean;            Optional - Show edit buttons (default: false)
  deletable?: boolean;           Optional - Show delete buttons (default: false)
  paginate?: boolean;            Optional - Enable pagination (default: false)
  perPage?: number;              Optional - Records per page (default: 25)
}
```

## 🏗️ Column Configuration

```typescript
interface ColumnDef {
  key: string;                                  ✓ Required - Data key
  label: string;                                ✓ Required - Display name
  type?: ColumnType;                            Optional - Data type
  sortable?: boolean;                           Optional - Can sort (default: true)
  filterable?: boolean;                         Optional - Can filter (default: true)
  editable?: boolean;                           Optional - Can edit (default: true)
  hidden?: boolean;                             Optional - Hidden column (default: false)
  width?: string;                               Optional - CSS width
  format?: (value) => string | React.ReactNode; Optional - Format display
  render?: (record) => React.ReactNode;         Optional - Custom rendering
  headerRender?: () => React.ReactNode;         Optional - Custom header
}
```

## 🎨 View Modes

```tsx
import { useState } from 'react';
import { CrudTable } from '@/Components/crud';
import type { ViewMode } from '@/types/crud';

const [viewMode, setViewMode] = useState<ViewMode>('table');

<CrudTable 
  config={config}
  viewMode={viewMode}
  onViewModeChange={setViewMode}
/>
```

**Modes:**
- `'table'` - Klassisk tabell med sortering
- `'accordion'` - Expanderbar lista
- `'master-detail'` - Tvåkolumsvy

## 🔄 Custom Rendering

```tsx
// Format a value
{
  key: 'price',
  label: 'Price',
  format: (value) => `$${value.toFixed(2)}`
}

// Custom JSX
{
  key: 'status',
  label: 'Status',
  render: (record) => (
    <span className={record.active ? 'text-green-600' : 'text-red-600'}>
      {record.active ? 'Active' : 'Inactive'}
    </span>
  )
}

// Custom header
{
  key: 'id',
  label: 'ID',
  headerRender: () => <span className="font-bold">Custom Header</span>
}
```

## 🔍 Search & Filter

- **Search:** Realtidssökning i textfält
- **Filter:** Filtrering på numeriska/booleska fält
- **Sort:** Sortering A-Z / Z-A
- **Debounce:** Search väntar 300ms innan API-anrop

## 🔌 API Integration

**Automatic Endpoints:**
```
GET    /api/crud/{resource}           - Fetch records
POST   /api/crud/{resource}           - Create record
PATCH  /api/crud/{resource}/{id}      - Update record
DELETE /api/crud/{resource}/{id}      - Delete record
```

**Query Parameters:**
```
?search=term              - Search
?field=value              - Filter
?sort=field               - Sort ascending
?sort=-field              - Sort descending
?paginate=true            - Enable pagination
?per_page=25&page=1       - Pagination
```

## ✏️ Validation

**In your Laravel Model:**
```php
public static function validationRules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users'],
        'age' => ['integer', 'min:0', 'max:120'],
    ];
}
```

## 🔒 Authorization

**In your Laravel Policy:**
```php
public function viewAny(User $user) { return true; }
public function view(User $user, Model $model) { return true; }
public function create(User $user) { return true; }
public function update(User $user, Model $model) { return true; }
public function delete(User $user, Model $model) { return true; }
```

## 📦 Imports

```tsx
// Main component
import { CrudTable } from '@/Components/crud';

// Types
import type { 
  CrudTableConfig, 
  ColumnDef, 
  CrudRecord,
  ViewMode 
} from '@/types/crud';

// Individual components (if needed)
import { TableView } from '@/Components/crud';
import { AccordionView } from '@/Components/crud';
import { MasterDetailView } from '@/Components/crud';
import { EditModal, CreateModal, DeleteConfirm } from '@/Components/crud';
import { FilterBar } from '@/Components/crud';
```

## 🎯 Common Patterns

### Simple CRUD
```tsx
<CrudTable config={{
  resource: 'users',
  columns: [
    { key: 'id', label: 'ID', editable: false },
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
  ],
  creatable: true,
  editable: true,
  deletable: true,
}} />
```

### With View Mode Selector
```tsx
const [viewMode, setViewMode] = useState('table');
<CrudTable 
  config={config}
  viewMode={viewMode}
  onViewModeChange={setViewMode}
/>
```

### With Paginering
```tsx
<CrudTable config={{
  ...config,
  paginate: true,
  perPage: 15,
}} />
```

### With Custom Formatting
```tsx
{
  key: 'created_at',
  label: 'Created',
  type: 'datetime',
  format: (value) => new Date(value).toLocaleDateString('sv-SE')
}
```

## 📍 File Locations

```
Core:
  resources/js/Components/crud/CrudTable.tsx
  resources/js/hooks/useCrudTable.ts
  resources/js/types/crud.ts

Views:
  resources/js/Components/crud/views/TableView.tsx
  resources/js/Components/crud/views/AccordionView.tsx
  resources/js/Components/crud/views/MasterDetailView.tsx

Modals:
  resources/js/Components/crud/modals/EditModal.tsx
  resources/js/Components/crud/modals/CreateModal.tsx
  resources/js/Components/crud/modals/DeleteConfirm.tsx

Other:
  resources/js/Components/crud/filters/FilterBar.tsx
  resources/js/Components/crud/CrudTableExamples.tsx
  resources/js/Components/crud/QuickIntegrationTemplate.tsx
```

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| No data shows | Check `/api/crud/{resource}` in DevTools |
| 404 error | Verify model name: `users` → `App\Models\User` |
| 403 Unauthorized | Check Authorization Policy |
| Validation fails | Add `validationRules()` to model |
| Search doesn't work | Only text fields are searchable |
| Filter doesn't work | Only number/boolean fields filterable |

## 📚 Documentation

- **CRUD_TABLE_README.md** - Overview & features
- **INTEGRATION_GUIDE.md** - Step-by-step setup
- **CRUD_TABLE_DOCUMENTATION.md** - Full API reference
- **CrudTableExamples.tsx** - Code examples
- **ROUTING_AND_INERTIA_EXAMPLES.md** - Inertia setup

## 🎨 Styling

Change appearance by modifying Tailwind classes:

```tsx
// Colors
bg-blue-600        // Button background
text-gray-900      // Text color
border-gray-300    // Border color

// Spacing
px-4 py-2          // Padding
gap-2              // Gaps
m-4                // Margin

// Sizing
w-full             // Width
text-sm            // Font size
rounded-lg         // Border radius
```

## ✨ Tips

- Use `type: 'boolean'` for toggles
- Use `format` for dates and numbers
- Use `render` for complex HTML
- Add validationRules() to models
- Use Authorization Policies
- Start simple, add features gradually
- Check DevTools Network tab for API issues

---

**Need help?** Read the full docs! 📖

