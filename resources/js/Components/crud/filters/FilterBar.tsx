import React, { useState } from 'react';
import { Search, Filter, X, ChevronDown } from 'lucide-react';
import { CrudFilter, CrudSort, ColumnDef } from '@/types/crud';

interface FilterBarProps {
  columns: ColumnDef[];
  onSearchChange: (search: string) => void;
  onFiltersChange: (filters: CrudFilter[]) => void;
  onSortChange: (sort: CrudSort | null) => void;
  search?: string;
  filters?: CrudFilter[];
  sort?: CrudSort | null;
}

export function FilterBar({
  columns,
  onSearchChange,
  onFiltersChange,
  onSortChange,
  search = '',
  filters = [],
  sort = null,
}: FilterBarProps) {
  const [showFilters, setShowFilters] = useState(false);
  const [showSort, setShowSort] = useState(false);
  const [activeFilterField, setActiveFilterField] = useState<string | null>(null);

  const filterableColumns = columns.filter(col => col.filterable !== false && col.key !== 'id');
  const sortableColumns = columns.filter(col => col.sortable !== false && col.key !== 'id');

  const handleAddFilter = (field: string) => {
    if (!filters.some(f => f.field === field)) {
      onFiltersChange([...filters, { field, value: '' }]);
      setActiveFilterField(field);
    }
  };

  const handleRemoveFilter = (field: string) => {
    onFiltersChange(filters.filter(f => f.field !== field));
    setActiveFilterField(null);
  };

  const handleFilterChange = (field: string, value: any) => {
    onFiltersChange(
      filters.map(f => f.field === field ? { ...f, value } : f)
    );
  };

  const handleClearAll = () => {
    onSearchChange('');
    onFiltersChange([]);
    onSortChange(null);
  };

  const activeFiltersCount = filters.filter(f => f.value !== '').length;
  const hasActiveFilters = search !== '' || activeFiltersCount > 0 || sort !== null;

  return (
    <div className="space-y-3">
      {/* Search Bar */}
      <div className="relative">
        <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input
          type="text"
          placeholder="Search..."
          value={search}
          onChange={e => onSearchChange(e.target.value)}
          className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      {/* Filter Controls */}
      <div className="flex gap-2 flex-wrap items-center">
        {/* Filter Button */}
        <div className="relative">
          <button
            onClick={() => setShowFilters(!showFilters)}
            className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg border transition ${
              showFilters || activeFiltersCount > 0
                ? 'border-blue-300 bg-blue-50 text-blue-700'
                : 'border-gray-300 text-gray-700 hover:border-gray-400'
            }`}
          >
            <Filter size={16} />
            <span>Filter</span>
            {activeFiltersCount > 0 && (
              <span className="ml-1 px-2 py-0.5 bg-blue-600 text-white text-xs rounded-full">
                {activeFiltersCount}
              </span>
            )}
          </button>

          {/* Filter Dropdown */}
          {showFilters && filterableColumns.length > 0 && (
            <div className="absolute top-full left-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg z-10 min-w-48">
              {filterableColumns.map(col => (
                <div
                  key={col.key}
                  className={`border-b last:border-b-0 ${
                    filters.some(f => f.field === col.key) ? 'bg-blue-50' : ''
                  }`}
                >
                  {filters.some(f => f.field === col.key) ? (
                    <div className="p-3 space-y-2">
                      <div className="flex items-center justify-between">
                        <label className="text-sm font-medium text-gray-700">
                          {col.label}
                        </label>
                        <button
                          onClick={() => handleRemoveFilter(col.key)}
                          className="text-gray-400 hover:text-gray-600"
                        >
                          <X size={16} />
                        </button>
                      </div>
                      <input
                        type={col.type === 'number' ? 'number' : col.type === 'boolean' ? 'checkbox' : 'text'}
                        value={col.type === 'boolean' ? '' : filters.find(f => f.field === col.key)?.value || ''}
                        onChange={e =>
                          handleFilterChange(
                            col.key,
                            col.type === 'boolean' ? e.target.checked : e.target.value
                          )
                        }
                        checked={
                          col.type === 'boolean'
                            ? (filters.find(f => f.field === col.key)?.value || false)
                            : undefined
                        }
                        className="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                        placeholder={`Enter ${col.label.toLowerCase()}`}
                      />
                    </div>
                  ) : (
                    <button
                      onClick={() => handleAddFilter(col.key)}
                      className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 transition"
                    >
                      {col.label}
                    </button>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Sort Button */}
        <div className="relative">
          <button
            onClick={() => setShowSort(!showSort)}
            className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg border transition ${
              showSort || sort
                ? 'border-blue-300 bg-blue-50 text-blue-700'
                : 'border-gray-300 text-gray-700 hover:border-gray-400'
            }`}
          >
            <ChevronDown size={16} />
            <span>Sort</span>
          </button>

          {/* Sort Dropdown */}
          {showSort && sortableColumns.length > 0 && (
            <div className="absolute top-full left-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg z-10 min-w-48">
              <button
                onClick={() => onSortChange(null)}
                className={`w-full text-left px-3 py-2 text-sm ${
                  !sort ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100'
                }`}
              >
                None
              </button>
              {sortableColumns.map(col => (
                <div key={col.key} className="border-t">
                  <button
                    onClick={() => {
                      onSortChange({ field: col.key, direction: 'asc' });
                      setShowSort(false);
                    }}
                    className={`w-full text-left px-3 py-2 text-sm ${
                      sort?.field === col.key && sort.direction === 'asc'
                        ? 'bg-blue-50 text-blue-700'
                        : 'text-gray-700 hover:bg-gray-100'
                    }`}
                  >
                    {col.label} (A-Z)
                  </button>
                  <button
                    onClick={() => {
                      onSortChange({ field: col.key, direction: 'desc' });
                      setShowSort(false);
                    }}
                    className={`w-full text-left px-3 py-2 text-sm border-t ${
                      sort?.field === col.key && sort.direction === 'desc'
                        ? 'bg-blue-50 text-blue-700'
                        : 'text-gray-700 hover:bg-gray-100'
                    }`}
                  >
                    {col.label} (Z-A)
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Clear Button */}
        {hasActiveFilters && (
          <button
            onClick={handleClearAll}
            className="px-3 py-2 text-sm text-gray-700 hover:text-gray-900 transition"
          >
            Clear All
          </button>
        )}
      </div>

      {/* Active Filters Display */}
      {filters.filter(f => f.value !== '').length > 0 && (
        <div className="flex flex-wrap gap-2">
          {filters
            .filter(f => f.value !== '')
            .map(filter => {
              const column = columns.find(c => c.key === filter.field);
              return (
                <div
                  key={filter.field}
                  className="inline-flex items-center gap-2 px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-sm"
                >
                  <span>{column?.label}: {filter.value}</span>
                  <button
                    onClick={() => handleRemoveFilter(filter.field)}
                    className="hover:text-blue-900"
                  >
                    <X size={14} />
                  </button>
                </div>
              );
            })}
        </div>
      )}
    </div>
  );
}

