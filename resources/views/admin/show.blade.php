@extends('layouts.admin')

@php
    use App\Support\AdminUi;

    $titleField = collect([
        'catalog_name',
        'business_name',
        'store_name',
        'title',
        'name',
        'order_number',
        'subject',
        'referral_code',
        'setting_key',
    ])->first(fn ($field) => isset($item->{$field}) && $item->{$field} !== null && $item->{$field} !== '');

    $pageTitle = $titleField ? (string) $item->{$titleField} : AdminUi::singularTitle($module);
@endphp

@section('page_action', 'show')
@section('title', __('panel.common.view') . ' ' . AdminUi::singularTitle($module))

@section('content')
    <section class="module-screen narrow">
        <div class="screen-head">
            <a class="back-link" href="{{ route('admin.module.index', $module) }}">&larr; {{ __('panel.common.back_to', ['title' => $config['title']]) }}</a>
            @if($config['editable'] ?? true)
                <a class="primary-button" href="{{ route('admin.module.edit', [$module, $item->id]) }}">{{ __('panel.common.edit') }} {{ AdminUi::singularTitle($module) }}</a>
            @endif
        </div>

        <section class="panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ $pageTitle }}</h3>
                    <p>{{ $subtitle ?? __('panel.pages.generic_subtitle') }}</p>
                </div>
                @if(isset($item->status))
                    <span class="status-chip {{ AdminUi::statusBadgeClass($item->status) }}">{{ AdminUi::statusLabel($item->status) }}</span>
                @endif
            </div>

            @if(!empty($item->image_url ?? null) && AdminUi::isImageReference($item->image_url))
                <div class="detail-hero-media">
                    <img src="{{ AdminUi::mediaUrl($item->image_url) }}" alt="{{ $pageTitle }}">
                </div>
            @endif

            <div class="detail-grid">
                @foreach((array) $item as $key => $value)
                    @continue(in_array($key, ['password_hash'], true))
                    <div>
                        <strong>{{ AdminUi::columnLabel($key) }}</strong>
                        @if($key === 'status')
                            <span class="status-chip {{ AdminUi::statusBadgeClass($value) }}">{{ AdminUi::statusLabel($value) }}</span>
                        @elseif(in_array($key, ['image_url', 'emoji', 'icon'], true) && AdminUi::isImageReference($value))
                            <div class="detail-media">
                                <img src="{{ AdminUi::mediaUrl($value) }}" alt="{{ AdminUi::columnLabel($key) }}">
                                <small>{{ $value }}</small>
                            </div>
                        @else
                            <span>{{ AdminUi::formatTableValue($key, $value) }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        @foreach($related as $block)
            <section class="panel">
                <div class="page-block__header">
                    <div>
                        <h3>{{ $block['title'] }}</h3>
                        <p>{{ $block['subtitle'] }}</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                        <tr>
                            @foreach($block['columns'] as $column)
                                <th>{{ AdminUi::columnLabel($column) }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($block['rows'] as $row)
                            <tr>
                                @foreach($block['columns'] as $column)
                                    @php $value = data_get($row, $column); @endphp
                                    <td>
                                        @if($column === 'status')
                                            <span class="status-chip {{ AdminUi::statusBadgeClass($value) }}">{{ AdminUi::statusLabel($value) }}</span>
                                        @else
                                            {{ AdminUi::formatTableValue($column, $value) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($block['columns']) }}" class="subtle">{{ __('panel.common.no_related_records') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </section>
@endsection
