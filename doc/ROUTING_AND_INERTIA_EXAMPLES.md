/**
 * Example: Inertia Route Integration
 * 
 * Denna fil visar hur man kan sätta upp routing för CrudTable-sidor
 * i ett Laravel Inertia-projekt.
 */

// ============================================================================
// EXAMPLE: routes/web.php
// ============================================================================

use Inertia\Inertia;
use App\Http\Controllers\Admin\UserController;

Route::middleware(['auth', 'admin'])->group(function () {
    // Users Management
    Route::get('/admin/users', UserController::class . '@index')->name('admin.users.index');
    Route::get('/admin/users/create', UserController::class . '@create')->name('admin.users.create');
    
    // Products Management
    Route::resource('/admin/products', ProductController::class);
    
    // Orders Management  
    Route::resource('/admin/orders', OrderController::class);
});


// ============================================================================
// EXAMPLE: app/Http/Controllers/Admin/UserController.php
// ============================================================================

<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Show users management page with CrudTable
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index');
    }
}


// ============================================================================
// EXAMPLE: resources/js/Pages/Admin/Users/Index.tsx
// ============================================================================

<?php declare(strict_types=1);
import { Head } from '@inertiajs/react';
import { CrudTable } from '@/Components/crud';
import type { CrudTableConfig } from '@/types/crud';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function UsersIndex() {
  const config: CrudTableConfig = {
    resource: 'users',
    title: 'Users',
    description: 'Manage system users and permissions',
    creatable: true,
    editable: true,
    deletable: true,
    paginate: true,
    perPage: 25,
    columns: [
      {
        key: 'id',
        label: 'ID',
        editable: false,
        width: '80px',
      },
      {
        key: 'name',
        label: 'Name',
        type: 'string',
        sortable: true,
        editable: true,
      },
      {
        key: 'email',
        label: 'Email',
        type: 'string',
        sortable: true,
        editable: true,
      },
      {
        key: 'email_verified_at',
        label: 'Email Verified',
        type: 'datetime',
        editable: false,
        format: (value) => value ? new Date(value).toLocaleDateString() : 'Not verified',
      },
      {
        key: 'active',
        label: 'Active',
        type: 'boolean',
        filterable: true,
        editable: true,
        render: (record) => (
          <span className={`px-2 py-1 rounded text-sm font-semibold ${
            record.active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'
          }`}>
            {record.active ? 'Active' : 'Inactive'}
          </span>
        ),
      },
      {
        key: 'created_at',
        label: 'Created',
        type: 'datetime',
        sortable: true,
        editable: false,
        format: (value) => new Date(value).toLocaleDateString(),
      },
    ],
  };

  return (
    <AuthenticatedLayout
      header={<h2 className="font-semibold text-xl text-gray-800">Users Management</h2>}
    >
      <Head title="Users" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <CrudTable config={config} />
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}


// ============================================================================
// EXAMPLE: Multiple Resources on Same Page
// ============================================================================

import { useState } from 'react';
import { CrudTable } from '@/Components/crud';
import type { CrudTableConfig } from '@/types/crud';

export default function AdminDashboard() {
  const [activeTab, setActiveTab] = useState('users');

  const userConfig: CrudTableConfig = {
    resource: 'users',
    title: 'Users',
    columns: [
      { key: 'id', label: 'ID', editable: false },
      { key: 'name', label: 'Name', sortable: true, editable: true },
      { key: 'email', label: 'Email', sortable: true, editable: true },
    ],
    creatable: true,
    editable: true,
    deletable: true,
  };

  const productsConfig: CrudTableConfig = {
    resource: 'products',
    title: 'Products',
    columns: [
      { key: 'id', label: 'SKU', editable: false, render: (r) => `SKU-${r.id}` },
      { key: 'name', label: 'Name', sortable: true, editable: true },
      { key: 'price', label: 'Price', type: 'number', editable: true, format: (v) => `$${v}` },
    ],
    creatable: true,
    editable: true,
    deletable: true,
  };

  return (
    <div className="space-y-6">
      <div className="border-b border-gray-200">
        <nav className="flex gap-4">
          <button
            onClick={() => setActiveTab('users')}
            className={`px-4 py-3 font-medium border-b-2 transition ${
              activeTab === 'users'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-600'
            }`}
          >
            Users
          </button>
          <button
            onClick={() => setActiveTab('products')}
            className={`px-4 py-3 font-medium border-b-2 transition ${
              activeTab === 'products'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-600'
            }`}
          >
            Products
          </button>
        </nav>
      </div>

      {activeTab === 'users' && <CrudTable config={userConfig} />}
      {activeTab === 'products' && <CrudTable config={productsConfig} />}
    </div>
  );
}


// ============================================================================
// EXAMPLE: Nested Resource with Relationships
// ============================================================================

export default function CompaniesIndex() {
  const config: CrudTableConfig = {
    resource: 'companies',
    title: 'Companies',
    columns: [
      { key: 'id', label: 'ID', editable: false },
      { key: 'name', label: 'Company Name', sortable: true, editable: true },
      { key: 'industry', label: 'Industry', editable: true },
      {
        key: 'employees_count',
        label: 'Employees',
        type: 'number',
        editable: false,
        render: (record) => record.employees?.length || 0,
      },
      { key: 'active', label: 'Active', type: 'boolean', editable: true },
    ],
    creatable: true,
    editable: true,
    deletable: true,
  };

  return <CrudTable config={config} />;
}


// ============================================================================
// EXAMPLE: With Search Customization
// ============================================================================

/**
 * App\Models\Product.php
 */
class Product extends Model
{
    // ...
    
    /**
     * Custom search configuration
     * Denna metod talar om för API:et vilka fält som är sökbara
     */
    public static function crudSearch(): array
    {
        return [
            'direct' => ['name', 'sku', 'description'],
            'relations' => [
                'category' => ['name'],        // Sök i relaterad kategori
                'brand' => ['name'],           // Sök i relaterat märke
            ],
        ];
    }
}


// ============================================================================
// EXAMPLE: Layout Component
// ============================================================================

import { ReactNode } from 'react';
import { Head } from '@inertiajs/react';

interface AdminLayoutProps {
  title: string;
  children: ReactNode;
}

export default function AdminLayout({ title, children }: AdminLayoutProps) {
  return (
    <div className="min-h-screen bg-gray-100">
      <Head title={title} />

      {/* Header */}
      <div className="bg-white shadow">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <h1 className="text-3xl font-bold text-gray-900">{title}</h1>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="bg-white rounded-lg shadow">
          <div className="p-6">
            {children}
          </div>
        </div>
      </div>
    </div>
  );
}


// ============================================================================
// SETUP CHECKLIST
// ============================================================================

/**
 * ROUTING SETUP CHECKLIST
 * 
 * [ ] 1. Skapa Controller för modellen
 *     php artisan make:controller Admin/UserController
 *
 * [ ] 2. Skapa React-sida
 *     resources/js/Pages/Admin/Users/Index.tsx
 *
 * [ ] 3. Lägg till route i routes/web.php
 *     Route::get('/admin/users', [UserController::class, 'index']);
 *
 * [ ] 4. Lägg till Authorization Policy
 *     php artisan make:policy UserPolicy --model=User
 *
 * [ ] 5. Registrera Policy i AuthServiceProvider
 *     Gate::policy(User::class, UserPolicy::class);
 *
 * [ ] 6. Implementera Policy-metoder
 *     viewAny, view, create, update, delete
 *
 * [ ] 7. Skapa CrudTable config med korrekta kolumner
 *
 * [ ] 8. Testa API-anropet i DevTools
 *     GET /api/crud/users
 *
 * [ ] 9. Testa sida i webbläsaren
 *
 * [ ] 10. Testa CRUD-operationer
 *      - Skapa ny
 *      - Redigera
 *      - Ta bort
 */

export {};

