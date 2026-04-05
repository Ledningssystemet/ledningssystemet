# CRUD Table Module - Documentation

En fullständig React-komponent för CRUD-operationer med flexibel visning och inbyggd API-integration.

## Features

✅ **Tre visningslägen:**
- 📋 Tabellvy (klassisk tabell med sortering)
- 📂 Accordion-vy (expanderbar lista)
- 🔀 Master/Detail-vy (tvåkolumsvy med detaljer)

✅ **Funktionalitet:**
- Sök och filtrera data
- Skapa nya poster (Create Modal)
- Redigera enskilda poster (Edit Modal)
- Massredigering av flera poster (Bulk Edit)
- Ta bort poster med bekräftelse
- Sortera efter kolumner
- Paginering (valfritt)

## Användning

### Basic Example

```tsx
import { CrudTable } from '@/Components/crud';

export function UsersPage() {
  const config = {
    resource: 'users', // API endpoint: /api/crud/users
    title: 'Users',
    description: 'Manage system users',
    creatable: true,
    editable: true,
    deletable: true,
    columns: [
      { key: 'id', label: 'ID', editable: false },
      { key: 'name', label: 'Name', sortable: true },
      { key: 'email', label: 'Email', sortable: true, editable: true },
      { key: 'active', label: 'Active', type: 'boolean', filterable: true },
      { key: 'created_at', label: 'Created', type: 'datetime', sortable: true, editable: false },
    ],
  };

  return <CrudTable config={config} />;
}
```

### Med View Mode Selector

```tsx
import { useState } from 'react';
import { CrudTable } from '@/Components/crud';
import type { ViewMode } from '@/types/crud';

export function ProductsPage() {
  const [viewMode, setViewMode] = useState<ViewMode>('table');

  const config = {
    resource: 'products',
    title: 'Products',
    columns: [
      { key: 'id', label: 'ID', editable: false },
      { key: 'name', label: 'Product Name', sortable: true },
      { key: 'price', label: 'Price', type: 'number', editable: true },
      { key: 'stock', label: 'Stock', type: 'number', editable: true },
      { key: 'active', label: 'Active', type: 'boolean', editable: true },
    ],
    creatable: true,
    editable: true,
    deletable: true,
  };

  return (
    <CrudTable 
      config={config} 
      viewMode={viewMode}
      onViewModeChange={setViewMode}
    />
  );
}
```

### Med Custom Rendering

```tsx
const config = {
  resource: 'orders',
  title: 'Orders',
  columns: [
    { 
      key: 'id', 
      label: 'Order ID',
      render: (record) => `#${record.id}`
    },
    { 
      key: 'total', 
      label: 'Total Amount',
      type: 'number',
      format: (value) => `$${value.toFixed(2)}`
    },
    { 
      key: 'status', 
      label: 'Status',
      render: (record) => (
        <span className={`px-2 py-1 rounded text-sm ${
          record.status === 'completed' ? 'bg-green-100 text-green-800' :
          record.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
          'bg-red-100 text-red-800'
        }`}>
          {record.status}
        </span>
      )
    },
    { 
      key: 'created_at', 
      label: 'Date',
      type: 'datetime',
      format: (value) => new Date(value).toLocaleDateString()
    },
  ],
  creatable: true,
  editable: true,
  deletable: true,
};

return <CrudTable config={config} />;
```

## Konfiguration

### CrudTableConfig Interface

```typescript
interface CrudTableConfig {
  resource: string;                    // API resource name (used in /api/crud/{resource})
  columns: ColumnDef[];                // Kolumndefinitioner
  title?: string;                      // Rubrik
  description?: string;                // Beskrivning
  creatable?: boolean;                 // Visa "Ny" knapp
  editable?: boolean;                  // Visa redigera-knappar
  deletable?: boolean;                 // Visa ta bort-knappar
  paginate?: boolean;                  // Aktivera paginering
  perPage?: number;                    // Poster per sida (default: 25)
  actions?: {
    edit?: boolean;
    delete?: boolean;
    custom?: Array<{
      label: string;
      onClick: (record: CrudRecord) => void;
      icon?: React.ReactNode;
    }>;
  };
}
```

### ColumnDef Interface

```typescript
interface ColumnDef {
  key: string;                                    // Nyckel i data-objektet
  label: string;                                  // Visningsnamn
  type?: 'string' | 'number' | 'boolean' | 'date' | 'datetime' | 'custom';
  sortable?: boolean;                             // Kan sorteras (default: true)
  filterable?: boolean;                           // Kan filtreras (default: true)
  editable?: boolean;                             // Kan redigeras (default: true)
  hidden?: boolean;                               // Gömd (default: false)
  width?: string;                                 // CSS-bredd
  format?: (value: any) => string | React.ReactNode;  // Formatera vid visning
  render?: (record: CrudRecord) => React.ReactNode;   // Custom rendering
  headerRender?: () => React.ReactNode;           // Custom header
}
```

## API Integration

Modulen använder sig av ditt befintliga CRUD API (`/api/crud/{resource}`).

### Förväntad API-struktur

**GET /api/crud/{resource}** - Hämta poster
```json
{
  "data": [
    { "id": 1, "name": "John", "email": "john@example.com" }
  ],
  "meta": {
    "total": 100,
    "per_page": 25,
    "current_page": 1,
    "last_page": 4
  }
}
```

**POST /api/crud/{resource}** - Skapa post
```json
{
  "id": 2,
  "name": "Jane",
  "email": "jane@example.com"
}
```

**PATCH /api/crud/{resource}/{id}** - Uppdatera post
```json
{
  "id": 1,
  "name": "John Updated",
  "email": "john@example.com"
}
```

**DELETE /api/crud/{resource}/{id}** - Ta bort post
```json
{}
```

## Search & Filter

### Sökning
- Sökfältet i FilterBar skapar en `search` query-parameter
- GenericCrudController söker i alla "searchable" textfält

### Filtrering
- Filterbara fält visas i Filter-dropdown
- Endast numeriska och booleska fält kan filtreras
- Filtervärden skickas som query-parametrar

### Sortering
- Sortering efter kolumner som har `sortable: true`
- Skickas som `sort` parameter (ex: `sort=name` eller `sort=-created_at`)

## Visningsmoder

### Tabellvy (`table`)
- Klassisk HTML-tabell
- Checkbox för rad-urval
- Massredigering av valda rader
- Sortering via kolumnhuvuden
- Inline-actionsknappar per rad

### Accordion-vy (`accordion`)
- Expanderbar lista
- Visa sammanfattning i huvudet
- Detaljerad vy när expanderad
- Actions (Edit, Delete) i expanded state
- Enkelt navigerbar på mobil

### Master/Detail-vy (`master-detail`)
- Tvåkolumsupplayout
- Vänster: Lista med poster
- Höger: Detaljer för vald post
- Responsive (staplas på mobil)
- Actions i detail-panelen

## Modaler

### Create Modal
- Öppnas när användaren klickar "New"
- Visar endast redigerbara fält
- Validering från backend
- Visar errormeldingar

### Edit Modal
- Öppnas när användaren klickar "Edit"
- Vid bulk-edit: tom form för att ange nya värden
- Pre-fylld med post-data vid enskild redigering
- Validering från backend

### Delete Confirm
- Bekräftelse innan borttagning
- Möjlighet att avbryta

## Hooks

### useCrudTable

Hämtar och hanterar CRUD-data:

```typescript
const {
  state,              // { data, loading, error, selectedRows, filters, search, sort, pagination }
  setFilters,         // Uppdatera filtrer
  setSearch,          // Uppdatera sökning
  setSort,            // Uppdatera sortering
  setPage,            // Byt sida
  toggleSelectRow,    // Byt urval för rad
  selectAll,          // Välj/avvälja alla
  clearSelection,     // Rensa alla urval
  createRecord,       // Skapa ny post
  updateRecord,       // Uppdatera post
  deleteRecord,       // Ta bort post
  bulkUpdate,         // Uppdatera flera poster
  refresh,            // Uppdatera data från API
} = useCrudTable({ resource: 'users' });
```

## Styling

Komponenten använder Tailwind CSS. Anpassa styling genom att:
1. Ändra klassnamnen direkt i komponenterna
2. Eller skapa CSS-variabler för färger/spacing
3. Eller använda Tailwind theme-konfiguration

## Best Practices

1. **Definierade modeller**: Se till att dina Laravel-modeller har `validationRules()` metod för validering
2. **Visibilities**: Använd `$visible` eller `$hidden` i dina modeller för att kontrollera vilka fält som exponeras
3. **Authorization**: GenericCrudController använder Gate för auktorisering
4. **Searchable**: Definiera `crudSearch()` metod i modeller för custom söklogik

## TypeScript Support

All kod är fullständigt typat:

```typescript
import { CrudTable, type ColumnDef, type CrudTableConfig } from '@/Components/crud';

const columns: ColumnDef[] = [...];
const config: CrudTableConfig = {...};
```

## Troubleshooting

**Inget data visas:**
- Kontrollera att API-resourcen är korrekt
- Öppna DevTools Network tab för att se API-anrop
- Verifiera att modellen finns på `/App/Models/{ModelName}.php`

**Validering fungerar inte:**
- Lägg till `validationRules()` statisk metod i din Laravel-modell
- Returnera Laravel validation-regler som array

**Sök fungerar inte:**
- Verifiera att kolumnerna är text-typ
- Lägg till `crudSearch()` metod för custom söklogik

## Framtida Förbättringar

- [ ] Optimistic updates (visa ändringar innan API-respons)
- [ ] Batch delete
- [ ] Import/Export data
- [ ] Kolumn-konfigurering (visa/dölj kolumner)
- [ ] Spara filter-presets
- [ ] Inlina-redigering (utan modal)
- [ ] Drag-and-drop reordering

