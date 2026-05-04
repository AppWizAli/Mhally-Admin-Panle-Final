@extends('layouts.admin')

@php
    $summary = $summary ?? null;
@endphp

@section('page_action', 'create')
@section('title', __('panel.bulk.products_title'))

@section('content')
    <section class="module-screen narrow">
        <div class="screen-head">
            <a class="back-link" href="{{ route('admin.module.index', 'products') }}">&larr; {{ __('panel.common.back_to', ['title' => \App\Support\AdminUi::moduleTitle('products')]) }}</a>
            <a class="ghost-button" href="{{ route('admin.products.bulk.template') }}">{{ __('panel.bulk.download_template') }}</a>
        </div>

        <section class="panel form-panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ __('panel.bulk.products_title') }}</h3>
                    <p>{{ __('panel.bulk.products_subtitle') }}</p>
                </div>
            </div>

            @if($errors->any())
                <div class="validation-summary" role="alert">
                    <strong>{{ __('panel.bulk.review_upload') }}</strong>
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="post" action="{{ route('admin.products.bulk.upload') }}" class="stack-form" enctype="multipart/form-data">
                @csrf
                <div class="two-field">
                    <div class="form-field {{ $errors->has('supplier_id') ? 'has-error' : '' }}" style="--stagger: 1;">
                        <label for="bulk_supplier_id"><span>{{ __('panel.bulk.supplier') }} <em>*</em></span></label>
                        <select id="bulk_supplier_id" name="supplier_id" class="{{ $errors->has('supplier_id') ? 'is-invalid' : '' }}">
                            <option value="">{{ __('panel.common.select', ['field' => __('panel.bulk.supplier')]) }}</option>
                            @foreach($suppliers as $supplierId => $supplierName)
                                <option value="{{ $supplierId }}" {{ (string) old('supplier_id') === (string) $supplierId ? 'selected' : '' }}>{{ $supplierName }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <small class="field-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-field {{ $errors->has('inventory_file') ? 'has-error' : '' }}" style="--stagger: 2;">
                        <label for="inventory_file"><span>{{ __('panel.bulk.inventory_file') }} <em>*</em></span></label>
                        <input id="inventory_file" type="file" name="inventory_file" accept=".csv,.txt,.xlsx" class="{{ $errors->has('inventory_file') ? 'is-invalid' : '' }}">
                        <small class="file-meta">{{ __('panel.bulk.inventory_columns') }}</small>
                        @error('inventory_file')
                            <small class="field-error">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="form-actions" style="--stagger: 3;">
                    <a class="ghost-button" href="{{ route('admin.module.index', 'products') }}">{{ __('panel.common.cancel') }}</a>
                    <button class="primary-button" type="submit">{{ __('panel.bulk.upload_products') }}</button>
                </div>
            </form>
        </section>

        @if($summary)
            <section class="panel">
                <div class="page-block__header">
                    <div>
                        <h3>{{ __('panel.bulk.report_title') }}</h3>
                        <p>{{ __('panel.bulk.report_summary', ['imported' => $summary['imported_count'], 'created' => $summary['created_count'], 'updated' => $summary['updated_count'], 'errors' => $summary['error_count']]) }}</p>
                    </div>
                </div>

                @if(!empty($summary['errors']))
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>{{ __('panel.bulk.row') }}</th>
                                <th>{{ __('panel.bulk.field') }}</th>
                                <th>{{ __('panel.bulk.issue') }}</th>
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
                    <p class="subtle">{{ __('panel.bulk.all_products_success') }}</p>
                @endif
            </section>
        @endif
    </section>
@endsection
