/**
 * Integration Guide - Steg-för-steg instruktioner
 * 
 * Den här filen visar praktiska exempel på hur man integrerar CrudTable
 * i en befintlig Laravel Inertia-applikation.
 */

// ============================================================================
// STEG 1: Skapa en ny React-sida
// ============================================================================
// Fil: resources/js/Pages/Admin/Users/Index.tsx

import { Head } from '@inertiajs/react';
import { CrudTable } from '@/Components/crud';
import type { CrudTableConfig } from '@/types/crud';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function UsersIndex() {
  const config: CrudTableConfig = {
    resource: 'users',
    title: 'Users',
    description: 'Manage system users',
    creatable: true,
    editable: true,
    deletable: true,
    columns: [
      { key: 'id', label: 'ID', editable: false, width: '80px' },
      { key: 'name', label: 'Name', sortable: true, editable: true },
      { key: 'email', label: 'Email', sortable: true, editable: true },
      { key: 'active', label: 'Active', type: 'boolean', filterable: true, editable: true },
      { key: 'created_at', label: 'Created', type: 'datetime', sortable: true, editable: false },
    ],
  };

  return (
    <AuthenticatedLayout
      header={<h2 className="font-semibold text-xl text-gray-800">Users</h2>}
    >
      <Head title="Users" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6">
              <CrudTable config={config} />
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}


// ============================================================================
// STEG 2: Konfigurera Laravel-modellen
// ============================================================================
// Fil: app/Models/User.php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class User extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean',
    ];

    /**
     * Validering för CRUD API
     * Krävs för att modalen ska kunna validera input
     */
    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'active' => ['boolean'],
        ];
    }

    /**
     * (Valfritt) Custom sökning
     * Definierar vilka fält som är sökbara
     */
    public static function crudSearch(): array
    {
        return [
            'direct' => ['name', 'email'],
        ];
    }
}


// ============================================================================
// STEG 3: Uppdatera Authorization Policies
// ============================================================================
// Fil: app/Policies/UserPolicy.php

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasRole('admin');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('admin') && $user->id !== $model->id;
    }
}


// ============================================================================
// STEG 4: Med Custom Rendering (Advanced)
// ============================================================================
// Fil: resources/js/Pages/Admin/Products/Index.tsx

import React, { useState } from 'react';
import { CrudTable } from '@/Components/crud';
import type { CrudTableConfig, ViewMode, CrudRecord } from '@/types/crud';

export default function ProductsIndex() {
  const [viewMode, setViewMode] = useState<ViewMode>('table');

  const config: CrudTableConfig = {
    resource: 'products',
    title: 'Products',
    creatable: true,
    editable: true,
    deletable: true,
    columns: [
      {
        key: 'id',
        label: 'SKU',
        editable: false,
        render: (record) => `SKU-${record.id}`,
      },
      {
        key: 'name',
        label: 'Product Name',
        sortable: true,
        editable: true,
      },
      {
        key: 'price',
        label: 'Price',
        type: 'number',
        sortable: true,
        editable: true,
        format: (value) => `$${parseFloat(value).toFixed(2)}`,
      },
      {
        key: 'stock',
        label: 'Stock',
        type: 'number',
        sortable: true,
        filterable: true,
        editable: true,
      },
      {
        key: 'active',
        label: 'Status',
        type: 'boolean',
        filterable: true,
        editable: true,
        render: (record) => (
          <span className={`px-3 py-1 rounded-full text-xs font-semibold ${
            record.active
              ? 'bg-green-100 text-green-800'
              : 'bg-red-100 text-red-800'
          }`}>
            {record.active ? 'Active' : 'Inactive'}
          </span>
        ),
      },
    ],
  };

  return (
    <div className="py-12">
      <div className="max-w-7xl mx-auto">
        <CrudTable
          config={config}
          viewMode={viewMode}
          onViewModeChange={setViewMode}
        />
      </div>
    </div>
  );
}


// ============================================================================
// STEG 5: Med Paginering
// ============================================================================

const config: CrudTableConfig = {
  resource: 'orders',
  title: 'Orders',
  paginate: true,        // Aktivera paginering
  perPage: 15,           // 15 poster per sida
  // ... resten av config
};


// ============================================================================
// STEG 6: Checklista för Integration
// ============================================================================

/**
 * INTEGRATION CHECKLIST
 * 
 * För varje modell du vill hantera med CrudTable:
 * 
 * [ ] 1. Modell finns på App\Models\{ModelName}
 * [ ] 2. Modellen har $fillable definierad
 * [ ] 3. Modellen har validationRules() statisk metod
 * [ ] 4. Authorization Policy finns och är konfigurerad
 * [ ] 5. React-sida skapas med CrudTable-komponent
 * [ ] 6. Kolumner definieras enligt databasen
 * [ ] 7. creatable/editable/deletable inställningar är korrekta
 * [ ] 8. Custom rendering läggs till där det behövs
 * [ ] 9. Testning - skapa, redigera, ta bort
 * [ ] 10. Authorization testas - kan endast admin göra CRUD?
 */


// ============================================================================
// STEG 7: Troubleshooting - Common Issues
// ============================================================================

/**
 * PROBLEM: "Unknown resource" error
 * LÖSNING: Kontrollera att modellnamnet stämmer
 *          Resource 'users' → App\Models\User
 *          Resource 'my-products' → App\Models\MyProduct
 * 
 * PROBLEM: 403 Unauthorized
 * LÖSNING: Kontrollera Authorization Policy
 *          Se till att user har rätt roll
 * 
 * PROBLEM: Validering fungerar inte
 * LÖSNING: Lägg till validationRules() i modellen
 *          Kontrollera att fältnamnen stämmer
 * 
 * PROBLEM: Sök fungerar inte
 * LÖSNING: Endast textfält är sökbara
 *          Lägg till crudSearch() för custom konfiguration
 * 
 * PROBLEM: Modalen visar fel inputtyp
 * LÖSNING: Kontrollera `type` i ColumnDef
 *          'string' | 'number' | 'boolean' | 'date' | 'datetime'
 */


// ============================================================================
// STEG 8: Advanced Tips
// ============================================================================

/**
 * TIP 1: Relationship Display
 * Om du vill visa data från en relaterad modell:
 * 
 * const config: CrudTableConfig = {
 *   resource: 'orders',
 *   columns: [
 *     // Direkt fält
 *     { key: 'order_number', label: 'Order #' },
 *     // Relationship - måste returneras från API
 *     { 
 *       key: 'customer_name',
 *       label: 'Customer',
 *       render: (record) => record.customer?.name || 'N/A'
 *     },
 *   ]
 * };
 * 
 * Se till att din API returnerar relationen!
 */

/**
 * TIP 2: Computed Columns
 * Om du vill visa beräknade värden:
 * 
 * {
 *   key: 'total_price',
 *   label: 'Total',
 *   editable: false,
 *   render: (record) => {
 *     const total = record.quantity * record.unit_price;
 *     return `$${total.toFixed(2)}`;
 *   }
 * }
 */

/**
 * TIP 3: Conditional Editing
 * Gör bara vissa fält redigerbara under vissa förutsättningar:
 * 
 * {
 *   key: 'price',
 *   label: 'Price',
 *   editable: record => record.status !== 'completed'
 * }
 * 
 * OBS: Detta är inte implementerat än - redigera CrudTable.tsx
 */

/**
 * TIP 4: Date Formatting
 * Formatera datum automatiskt:
 * 
 * {
 *   key: 'created_at',
 *   label: 'Created',
 *   type: 'datetime',
 *   format: (value) => new Date(value).toLocaleDateString('sv-SE')
 * }
 */

export {};

