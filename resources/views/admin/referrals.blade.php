@extends('layouts.admin')

@php
    use App\Support\AdminUi;
@endphp

@section('title', __('panel.referrals.title'))

@section('content')
    <section class="split-screen">
        <article class="stack-column">
            <section class="panel">
                <div class="panel__header">
                    <div>
                        <span class="eyebrow">{{ __('panel.referrals.settings_eyebrow') }}</span>
                        <h3>{{ __('panel.referrals.settings_title') }}</h3>
                    </div>
                </div>
                <form method="post" action="{{ route('admin.settings.app') }}" class="stack-form">
                    @csrf
                    @foreach($settings as $setting)
                        <label>
                            <span>{{ AdminUi::settingLabel($setting->setting_key, $setting->label) }}</span>
                            <input type="text" name="setting_{{ $setting->setting_key }}" value="{{ old('setting_' . $setting->setting_key, $setting->setting_value) }}">
                        </label>
                    @endforeach
                    <button class="primary-button full-width" type="submit">{{ __('panel.referrals.save_settings') }}</button>
                </form>
            </section>

            <section class="panel">
                <div class="page-block__header">
                    <div>
                        <h3>{{ __('panel.referrals.codes_title') }}</h3>
                        <p>{{ __('panel.referrals.codes_subtitle', ['count' => $codes->count()]) }}</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>{{ AdminUi::columnLabel('referral_code') }}</th>
                            <th>{{ AdminUi::columnLabel('store_name') }}</th>
                            <th>{{ AdminUi::columnLabel('buyer_name') }}</th>
                            <th>{{ AdminUi::columnLabel('city') }}</th>
                            <th>{{ AdminUi::columnLabel('updated_at') }}</th>
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
                                <td colspan="5" class="subtle">{{ __('panel.referrals.no_codes') }}</td>
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
                        <h3>{{ __('panel.referrals.claims_title') }}</h3>
                        <p>{{ __('panel.referrals.claims_subtitle', ['count' => $claims->count()]) }}</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>{{ AdminUi::columnLabel('referrer_store_name') }}</th>
                            <th>{{ AdminUi::columnLabel('referred_store_name') }}</th>
                            <th>{{ AdminUi::columnLabel('referral_code') }}</th>
                            <th>{{ AdminUi::columnLabel('reward_amount') }}</th>
                            <th>{{ AdminUi::columnLabel('status') }}</th>
                            <th>{{ AdminUi::columnLabel('created_at') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($claims as $item)
                            <tr>
                                <td>{{ $item->referrer_store_name }}</td>
                                <td>{{ $item->referred_store_name }}</td>
                                <td>{{ $item->referral_code }}</td>
                                <td>{{ AdminUi::money($item->reward_amount) }} / {{ AdminUi::money($item->referee_reward_amount) }}</td>
                                <td><span class="status-chip {{ AdminUi::statusBadgeClass($item->status) }}">{{ AdminUi::statusLabel($item->status) }}</span></td>
                                <td>{{ AdminUi::shortDate($item->created_at) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="subtle">{{ __('panel.referrals.no_claims') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </article>
    </section>
@endsection
