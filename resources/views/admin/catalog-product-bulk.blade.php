@extends('layouts.admin')

@php
    $summary = $summary ?? null;
@endphp

@section('page_action', 'create')
@section('title', 'Bulk Upload Catalog Products')

@section('content')
    <section class="module-screen narrow">
        <div class="screen-head">
            <a class="back-link" href="{{ route('admin.module.index', 'catalog_products') }}">&larr; Back to Catalog Products</a>
            <a class="ghost-button" href="{{ route('admin.catalog-products.bulk.template') }}">Download CSV Template</a>
        </div>

        <section class="panel form-panel">
            <div class="page-block__header">
                <div>
                    <h3>Bulk Upload Catalog Products</h3>
                    <p>Upload master catalog products by CSV or Excel. These become active immediately and suppliers can list them.</p>
                </div>
            </div>

            @if($errors->any())
                <div class="validation-summary" role="alert">
                    <strong>Please review the upload.</strong>
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="post" action="{{ route('admin.catalog-products.bulk.upload') }}" class="stack-form" enctype="multipart/form-data">
                @csrf
                <div class="form-field {{ $errors->has('catalog_file') ? 'has-error' : '' }}" style="--stagger: 1;">
                    <label for="catalog_file"><span>Catalog File <em>*</em></span></label>
                    <input id="catalog_file" type="file" name="catalog_file" accept=".csv,.txt,.xlsx" class="{{ $errors->has('catalog_file') ? 'is-invalid' : '' }}">
                    <small class="file-meta">Columns: Product Name, Category, Emoji, Description, Packaging, Unit Type, Image URL, Status.</small>
                    @error('catalog_file')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-actions" style="--stagger: 2;">
                    <a class="ghost-button" href="{{ route('admin.module.index', 'catalog_products') }}">Cancel</a>
                    <button class="primary-button" type="submit">Upload Catalog Products</button>
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
                    <p class="subtle">All catalog rows imported successfully.</p>
                @endif
            </section>
        @endif
    </section>
@endsection
