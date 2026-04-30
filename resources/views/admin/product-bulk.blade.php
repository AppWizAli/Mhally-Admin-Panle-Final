@extends('layouts.admin')

@php
    $summary = $summary ?? null;
@endphp

@section('page_action', 'create')
@section('title', 'Bulk Upload Products')

@section('content')
    <section class="module-screen narrow">
        <div class="screen-head">
            <a class="back-link" href="{{ route('admin.module.index', 'products') }}">&larr; Back to Products</a>
            <a class="ghost-button" href="{{ route('admin.products.bulk.template') }}">Download CSV Template</a>
        </div>

        <section class="panel form-panel">
            <div class="page-block__header">
                <div>
                    <h3>Bulk Upload Products</h3>
                    <p>Upload CSV or Excel inventory for one supplier. Imported products become active immediately.</p>
                </div>
            </div>

            @if($errors->any())
                <div class="validation-summary" role="alert">
                    <strong>Please review the upload.</strong>
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="post" action="{{ route('admin.products.bulk.upload') }}" class="stack-form" enctype="multipart/form-data">
                @csrf
                <div class="two-field">
                    <div class="form-field {{ $errors->has('supplier_id') ? 'has-error' : '' }}" style="--stagger: 1;">
                        <label for="bulk_supplier_id"><span>Supplier <em>*</em></span></label>
                        <select id="bulk_supplier_id" name="supplier_id" class="{{ $errors->has('supplier_id') ? 'is-invalid' : '' }}">
                            <option value="">Select supplier</option>
                            @foreach($suppliers as $supplierId => $supplierName)
                                <option value="{{ $supplierId }}" {{ (string) old('supplier_id') === (string) $supplierId ? 'selected' : '' }}>{{ $supplierName }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <small class="field-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-field {{ $errors->has('inventory_file') ? 'has-error' : '' }}" style="--stagger: 2;">
                        <label for="inventory_file"><span>Inventory File <em>*</em></span></label>
                        <input id="inventory_file" type="file" name="inventory_file" accept=".csv,.txt,.xlsx" class="{{ $errors->has('inventory_file') ? 'is-invalid' : '' }}">
                        <small class="file-meta">Columns: Product Name, Category, Original Price, Offer Price, Max Offer Quantity, Stock Quantity.</small>
                        @error('inventory_file')
                            <small class="field-error">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="form-actions" style="--stagger: 3;">
                    <a class="ghost-button" href="{{ route('admin.module.index', 'products') }}">Cancel</a>
                    <button class="primary-button" type="submit">Upload Products</button>
                </div>
            </form>
        </section>

        @if($summary)
            <section class="panel">
                <div class="page-block__header">
                    <div>
                        <h3>Upload Report</h3>
                        <p>{{ $summary['imported_count'] }} imported, {{ $summary['created_count'] }} created, {{ $summary['updated_count'] }} updated, {{ $summary['error_count'] }} issues.</p>
                    </div>
                </div>

                @if(!empty($summary['errors']))
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Row</th>
                                <th>Field</th>
                                <th>Issue</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($summary['errors'] as $error)
                                <tr>
                                    <td>{{ $error['row'] }}</td>
                                    <td>{{ $error['field'] }}</td>
                                    <td>{{ $error['issue'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="subtle">All rows imported successfully.</p>
                @endif
            </section>
        @endif
    </section>
@endsection
