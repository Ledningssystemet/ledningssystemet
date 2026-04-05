# CRUD Table Module - Implementation Summary

Du har nu en fullständig, återanvändbar CRUD-tabellmodul för ditt Laravel + React Inertia-projekt!

## 📁 Filstruktur

```
resources/js/
├── Components/crud/
│   ├── CrudTable.tsx                  ← Huvudkomponent
│   ├── CrudTableExamples.tsx          ← Exempel på användning
│   ├── index.ts                       ← Export-fil
│   ├── modals/
│   │   ├── Modal.tsx                  ← Base modal
│   │   ├── EditModal.tsx              ← Redigering
│   │   ├── CreateModal.tsx            ← Skapa ny
│   │   └── DeleteConfirm.tsx          ← Bekräftelse
│   ├── views/
│   │   ├── TableView.tsx              ← Klassisk tabell
│   │   ├── AccordionView.tsx          ← Expanderbar lista
│   │   └── MasterDetailView.tsx       ← Tvåkolumsvy
│   └── filters/
│       └── FilterBar.tsx              ← Sök & filter-bar
├── hooks/
│   └── useCrudTable.ts                ← CRUD-logik hook
└── types/
    └── crud.ts                        ← TypeScript-typer

📄 CRUD_TABLE_DOCUMENTATION.md         ← Fullständig dokumentation
```

## 🚀 Snabbstart

### 1. Importera och använd

```tsx
import { CrudTable } from '@/Components/crud';
import type { CrudTableConfig } from '@/types/crud';

export function MyDataPage() {
  const config: CrudTableConfig = {
    resource: 'my-resource',  // Motsvarar /api/crud/my-resource
    title: 'My Data',
    columns: [
      { key: 'id', label: 'ID', editable: false },
      { key: 'name', label: 'Name', sortable: true, editable: true },
      { key: 'email', label: 'Email', editable: true },
      { key: 'active', label: 'Active', type: 'boolean', filterable: true },
    ],
    creatable: true,
    editable: true,
    deletable: true,
  };

  return <CrudTable config={config} />;
}
```

### 2. Se till att din Laravel-modell är konfigurerad

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MyResource extends Model
{
    protected $fillable = ['name', 'email', 'active'];
    
    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Validering för CRUD API
     */
    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'active' => ['boolean'],
        ];
    }

    /**
     * (Valfritt) Custom sök-konfiguration
     */
    public static function crudSearch(): array
    {
        return [
            'direct' => ['name', 'email'],
            'relations' => [
                // 'user' => ['name', 'email'],
            ],
        ];
    }
}
```

## ✨ Features

### Tre Visningslägen
- **Tabell** (`table`) - Klassisk HTML-tabell med sortering och bulk-edit
- **Accordion** (`accordion`) - Expanderbar lista, bra på mobil
- **Master/Detail** (`master-detail`) - Tvåkolumsvy med detaljer på höger sida

### Filtrering & Sökning
- 🔍 Realtidssökning i textfält
- 🎯 Filtrering på numeriska och booleska fält
- ↕️ Sortering efter kolumner
- 🔄 Debounced API-anrop för search

### CRUD-Operationer
- ➕ Skapa nya poster (Create Modal)
- ✏️ Redigera enskilda eller flera poster samtidigt (Edit Modal)
- 🗑️ Ta bort poster med bekräftelse (Delete Modal)
- 🔄 Refresh-knapp för att uppdatera data

### Bulk-Redigering
- Massredigering av valda rader (endast i tabelläget)
- Checkbox-urval för alla/några/inga rader
- Visuell indikering av valda rader

### Responsiv Design
- Optimerad för desktop och mobil
- Tailwind CSS + Lucide React-ikoner
- Accordion och Master/Detail passar bättre på små skärmar

## 🔧 Konfiguration

### Basic Config
```typescript
const config: CrudTableConfig = {
  resource: 'users',                // API-resurs
  title: 'Users',                   // Sidotitel
  description: '...',               // Sidodescription
  columns: [...],                   // Kolumndefinitioner
  creatable: true,                  // Visa "New" knapp
  editable: true,                   // Visa redigera-knappar
  deletable: true,                  // Visa ta bort-knappar
  paginate: false,                  // Aktivera paginering
  perPage: 25,                      // Poster per sida
};
```

### Column Definition
```typescript
const columns: ColumnDef[] = [
  {
    key: 'name',                    // Fältnyckel i data
    label: 'Full Name',             // Visningsnamn
    type: 'string',                 // Datatyp
    sortable: true,                 // Kan sorteras
    filterable: false,              // Kan filtreras
    editable: true,                 // Kan redigeras
    hidden: false,                  // Gömd
    width: '200px',                 // CSS-bredd
    format: (val) => val.toUpperCase(),  // Formatera vid visning
    render: (record) => <span>Custom</span>,  // Custom rendering
    headerRender: () => <span>Custom Header</span>,
  },
];
```

## 🎨 Styling

All styling använder Tailwind CSS. För att ändra utseendet:

1. **Direkta ändringar** - Redigera klassnamnen i komponenterna
2. **Theme-variabler** - Anpassa via `tailwind.config.js`
3. **CSS Layers** - Skapa layer-specifika CSS-regler

Komponenten använder dessa Tailwind-klassnamn:
- `bg-blue-*`, `bg-red-*`, `bg-green-*` för färger
- `px-4 py-2` för spacing
- `rounded-lg` för border-radius
- `transition` för animationer

## 🔌 API Integration

Modulen integreras automatiskt med ditt befintliga CRUD API:

```
GET    /api/crud/{resource}           - Hämta poster
POST   /api/crud/{resource}           - Skapa post
PATCH  /api/crud/{resource}/{id}      - Uppdatera post
DELETE /api/crud/{resource}/{id}      - Ta bort post
```

### Query Parameters

```
?search=term                    - Sök i textfält
?field=value                    - Filtrera på fält
?sort=field eller sort=-field   - Sortera
?paginate=true                  - Aktivera paginering
&per_page=25&page=1            - Paginering-inställningar
```

### Validation

Validering hanteras via `validationRules()` statisk metod i din Laravel-modell:

```php
public static function validationRules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users'],
        'price' => ['required', 'numeric', 'min:0'],
    ];
}
```

## 📚 Användningsexempel

### Enkelt exempel (Users)
Se `UsersExample()` i `CrudTableExamples.tsx`

### Med View Mode Selector
Se `ProductsExample()` i `CrudTableExamples.tsx`

### Med Custom Rendering
Se `OrdersExample()` i `CrudTableExamples.tsx`

## 🧪 Testing

För att testa modulen:

1. Gå till någon sida där du importerar `CrudTable`
2. Skicka in en `config` med en befintlig API-resurs
3. Testa:
   - Sökning och filtrering
   - Skapa ny post
   - Redigera post
   - Ta bort post
   - Wechla mellan visningslägen
   - Massredigering (i tabelläget)

## 🐛 Felsökning

**Inget data visas:**
- Öppna DevTools → Network tab
- Kolla om `/api/crud/{resource}` returnerar data
- Verifiera att modellen finns

**Validering fungerar inte:**
- Lägg till `validationRules()` i modellen
- Kontrollera att fältnamnen stämmer

**Sök fungerar inte:**
- Verifiera att kolumner av typ `string` kan sökas
- Lägg till `crudSearch()` för custom sökning

**Styling ser fel ut:**
- Kontrollera Tailwind CSS är konfigurerat korrekt
- Verifiera att Tailwind är compilat

## 📖 Fullständig Dokumentation

Se `CRUD_TABLE_DOCUMENTATION.md` för:
- Detaljerad API-dokumentation
- Alla konfigurationsalternativ
- Custom rendering-exempel
- Best practices
- Framtida förbättringar

## 🎯 Nästa Steg

1. **Integrera i dina sidor** - Importera `CrudTable` och skapa config
2. **Anpassa styling** - Ändra Tailwind-klassnamn efter behov
3. **Lägg till validering** - Implementera `validationRules()` i modeller
4. **Custom search** - Lägg till `crudSearch()` för avancerad sökning
5. **Testing** - Testa alla CRUD-operationer

## 💡 Tips

- Börja med en enkel config och lägg till features stegvis
- Använd `type: 'boolean'` för booleska fält - de får bättre UI
- Använd `filterable: true` endast på numeriska/booleska fält
- Lägg till `format` för att formatera värden (dates, currency osv)
- Använd `render` för avancerad HTML-rendering

## 🤝 Support

Om du behöver anpassa något:
1. Läs dokumentationen
2. Kolla exempel-koden
3. Anpassa komponenterna efter dina behov

Lycka till! 🚀

