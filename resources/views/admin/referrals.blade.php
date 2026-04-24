@extends('layouts.admin')

@php
    use App\Support\AdminUi;
@endphp

@section('title', 'Referral Program')

@section('content')
    <section class="split-screen">
        <article class="stack-column">
            <section class="panel">
                <div class="panel__header">
                    <div>
                        <span class="eyebrow">Referral settings</span>
                        <h3>Buyer reward configuration</h3>
                    </div>
                </div>
                <form method="post" action="{{ route('admin.settings.app') }}" class="stack-form">
                    @csrf
                    @foreach($settings as $setting)
                        <label>
                            <span>{{ $setting->label }}</span>
                            <input type="text" name="setting_{{ $setting->setting_key }}" value="{{ old('setting_' . $setting->setting_key, $setting->setting_value) }}">
                        </label>
                    @endforeach
                    <button class="primary-button full-width" type="submit">Save referral settings</button>
                </form>
            </section>

            <section class="panel">
                <div class="page-block__header">
                    <div>
                        <h3>Buyer Referral Codes</h3>
                        <p>{{ $codes->count() }} buyer referral codes have been generated so far.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Code</th>
                            <th>Store</th>
                            <th>Buyer</th>
                            <th>City</th>
                            <th>Updated</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($codes as $item)
                            <tr>
                                <td><strong>{{ $item->referral_code }}</strong></td>
                                <td>{{ $item->store_name }}</td>
                                <td>{{ $item->buyer_name }}</td>
                                <td>{{ $item->city }}</td>
                                <td>{{ AdminUi::shortDate($item->updated_at) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="subtle">No referral codes found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </article>

        <article class="stack-column">
            <section class="panel">
                <div class="page-block__header">
                    <div>
                        <h3>Referral Claims</h3>
                        <p>{{ $claims->count() }} referral relationships have been recorded.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Referrer</th>
                            <th>Referred Buyer</th>
                            <th>Code</th>
                            <th>Reward</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($claims as $item)
                            <tr>
                                <td>{{ $item->referrer_store_name }}</td>
                                <td>{{ $item->referred_store_name }}</td>
                                <td>{{ $item->referral_code }}</td>
                                <td>{{ AdminUi::money($item->reward_amount) }} / {{ AdminUi::money($item->referee_reward_amount) }}</td>
                                <td><span class="status-chip {{ AdminUi::statusBadgeClass($item->status) }}">{{ ucfirst((string) $item->status) }}</span></td>
                                <td>{{ AdminUi::shortDate($item->created_at) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="subtle">No referral claims found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </article>
    </section>
@endsection
