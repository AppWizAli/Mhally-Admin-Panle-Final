@extends('layouts.admin')

@php
    use App\Support\AdminUi;
@endphp

@section('title', 'Settings & Profile')

@section('content')
    <section class="split-screen">
        <article class="stack-column">
            <section class="panel">
                <div class="panel__header">
                    <div>
                        <span class="eyebrow">Admin profile</span>
                        <h3>Personal information</h3>
                    </div>
                </div>
                <form method="post" action="{{ route('admin.settings.profile') }}" class="stack-form">
                    @csrf

                    <div class="two-field">
                        <label>
                            <span>Full name</span>
                            <input type="text" name="full_name" value="{{ old('full_name', data_get($admin, 'full_name')) }}" required>
                        </label>
                        <label>
                            <span>Role</span>
                            <input type="text" name="role" value="{{ old('role', data_get($admin, 'role', 'Super Admin')) }}">
                        </label>
                    </div>

                    <div class="two-field">
                        <label>
                            <span>Email</span>
                            <input type="email" name="email" value="{{ old('email', data_get($admin, 'email')) }}" required>
                        </label>
                        <label>
                            <span>Phone</span>
                            <input type="text" name="phone" value="{{ old('phone', data_get($admin, 'phone')) }}">
                        </label>
                    </div>

                    <label>
                        <span>Location</span>
                        <input type="text" name="location" value="{{ old('location', data_get($admin, 'location')) }}">
                    </label>

                    <label>
                        <span>Bio</span>
                        <textarea name="bio" rows="4">{{ old('bio', data_get($admin, 'bio')) }}</textarea>
                    </label>

                    <button class="primary-button full-width" type="submit">Save profile</button>
                </form>
            </section>

            <section class="panel">
                <div class="panel__header">
                    <div>
                        <span class="eyebrow">Security</span>
                        <h3>Change password</h3>
                    </div>
                </div>
                <form method="post" action="{{ route('admin.settings.password') }}" class="stack-form">
                    @csrf
                    <label>
                        <span>Current password</span>
                        <input type="password" name="current_password" required>
                    </label>
                    <div class="two-field">
                        <label>
                            <span>New password</span>
                            <input type="password" name="new_password" required>
                        </label>
                        <label>
                            <span>Confirm password</span>
                            <input type="password" name="confirm_password" required>
                        </label>
                    </div>
                    <button class="primary-button full-width" type="submit">Update password</button>
                </form>
            </section>
        </article>

        <article class="stack-column">
            <section class="panel">
                <div class="panel__header">
                    <div>
                        <span class="eyebrow">Commission settings</span>
                        <h3>Admin order commission</h3>
                    </div>
                </div>
                <form method="post" action="{{ route('admin.settings.commission') }}" class="stack-form">
                    @csrf
                    <label>
                        <span>Commission percentage</span>
                        <input
                            type="number"
                            name="commission_percentage"
                            min="0"
                            max="100"
                            step="0.01"
                            value="{{ old('commission_percentage', $commissionPercentage) }}"
                            required
                        >
                    </label>
                    <label>
                        <span>Apply this percentage to</span>
                        <select name="commission_apply_scope">
                            <option value="new_only" {{ old('commission_apply_scope', 'new_only') === 'new_only' ? 'selected' : '' }}>New orders only</option>
                            <option value="all_orders" {{ old('commission_apply_scope') === 'all_orders' ? 'selected' : '' }}>All previous and new orders</option>
                        </select>
                    </label>
                    <p class="field-help">Use <strong>New orders only</strong> to keep old orders unchanged. Use <strong>All previous and new orders</strong> to recalculate stored commission percentage and commission amount on existing orders too.</p>
                    <button class="primary-button full-width" type="submit">Save commission settings</button>
                </form>
            </section>

            <section class="panel">
                <div class="panel__header">
                    <div>
                        <span class="eyebrow">Public app settings</span>
                        <h3>Values exposed to buyer and supplier apps</h3>
                    </div>
                </div>
                <form method="post" action="{{ route('admin.settings.app') }}" class="stack-form">
                    @csrf
                    @foreach($publicSettings as $setting)
                        @php
                            $settingKey = (string) $setting->setting_key;
                            $fieldName = 'setting_' . $settingKey;
                            $fieldValue = old($fieldName, $setting->setting_value);
                        @endphp
                        <label>
                            <span>{{ $setting->label }}</span>
                            @if(in_array($settingKey, $booleanSettingKeys, true))
                                <select name="{{ $fieldName }}">
                                    <option value="1" {{ (string) $fieldValue === '1' ? 'selected' : '' }}>Enabled</option>
                                    <option value="0" {{ (string) $fieldValue === '0' ? 'selected' : '' }}>Disabled</option>
                                </select>
                            @elseif(in_array($settingKey, $multilineSettingKeys, true))
                                <textarea name="{{ $fieldName }}" rows="3">{{ $fieldValue }}</textarea>
                            @else
                                <input type="text" name="{{ $fieldName }}" value="{{ $fieldValue }}">
                            @endif
                        </label>
                    @endforeach
                    <button class="primary-button full-width" type="submit">Save app settings</button>
                </form>
            </section>

            <section class="panel">
                <div class="panel__header">
                    <div>
                        <span class="eyebrow">System settings</span>
                        <h3>Operational defaults</h3>
                    </div>
                </div>
                <form method="post" action="{{ route('admin.settings.app') }}" class="stack-form">
                    @csrf
                    @foreach($systemSettings as $setting)
                        @php
                            $settingKey = (string) $setting->setting_key;
                            $fieldName = 'setting_' . $settingKey;
                            $fieldValue = old($fieldName, $setting->setting_value);
                        @endphp
                        <label>
                            <span>{{ $setting->label }}</span>
                            @if(in_array($settingKey, $booleanSettingKeys, true))
                                <select name="{{ $fieldName }}">
                                    <option value="1" {{ (string) $fieldValue === '1' ? 'selected' : '' }}>Enabled</option>
                                    <option value="0" {{ (string) $fieldValue === '0' ? 'selected' : '' }}>Disabled</option>
                                </select>
                            @elseif(in_array($settingKey, $multilineSettingKeys, true))
                                <textarea name="{{ $fieldName }}" rows="3">{{ $fieldValue }}</textarea>
                            @else
                                <input type="text" name="{{ $fieldName }}" value="{{ $fieldValue }}">
                            @endif
                        </label>
                    @endforeach
                    <button class="primary-button full-width" type="submit">Save system settings</button>
                </form>
            </section>

            <section class="panel">
                <div class="panel__header">
                    <div>
                        <span class="eyebrow">API reference</span>
                        <h3>Endpoints ready for Android integration</h3>
                    </div>
                </div>
                <div class="api-guide">
                    <article>
                        <strong>Authentication</strong>
                        <code>POST {{ url('api/index.php?endpoint=auth/buyer/login') }}</code>
                        <code>POST {{ url('api/index.php?endpoint=auth/buyer/register') }}</code>
                        <code>POST {{ url('api/index.php?endpoint=auth/supplier/login') }}</code>
                        <code>POST {{ url('api/index.php?endpoint=auth/supplier/register') }}</code>
                        <code>POST {{ url('api/index.php?endpoint=auth/request-otp') }}</code>
                        <code>POST {{ url('api/index.php?endpoint=auth/verify-otp') }}</code>
                    </article>
                    <article>
                        <strong>Buyer app</strong>
                        <code>GET {{ url('api/index.php?endpoint=buyer/home') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=buyer/categories') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=buyer/suppliers') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=buyer/products') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=buyer/offers') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=buyer/notifications') }}</code>
                        <code>POST {{ url('api/index.php?endpoint=buyer/notifications/register-device') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=buyer/referrals') }}</code>
                        <code>POST {{ url('api/index.php?endpoint=buyer/referrals/apply') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=buyer/orders') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=buyer/chats') }}</code>
                    </article>
                    <article>
                        <strong>Supplier app</strong>
                        <code>GET {{ url('api/index.php?endpoint=supplier/dashboard') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=supplier/catalog') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=supplier/products') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=supplier/orders') }}</code>
                        <code>GET {{ url('api/index.php?endpoint=supplier/earnings') }}</code>
                    </article>
                </div>
            </section>
        </article>
    </section>
@endsection
