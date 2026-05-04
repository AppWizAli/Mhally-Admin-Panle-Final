@extends('layouts.admin')

@section('page_action', 'show')
@section('title', __('panel.chat.view_title'))

@section('content')
    <section class="module-screen narrow">
        <div class="screen-head">
            <a class="back-link" href="{{ route('admin.module.index', 'chats') }}">&larr; {{ __('panel.common.back_to', ['title' => __('panel.nav.chats')]) }}</a>
        </div>

        <section class="panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ $item->subject }}</h3>
                    <p>{{ __('panel.chat.participant_subtitle', ['buyer' => $item->store_name, 'supplier' => $item->business_name]) }}</p>
                </div>
            </div>

            <div class="detail-grid">
                <div><strong>{{ \App\Support\AdminUi::columnLabel('buyer_name') }}</strong><span>{{ $item->store_name }}</span></div>
                <div><strong>{{ \App\Support\AdminUi::columnLabel('supplier_name') }}</strong><span>{{ $item->business_name }}</span></div>
                <div><strong>{{ \App\Support\AdminUi::columnLabel('buyer_unread_count') }}</strong><span>{{ $item->buyer_unread_count }}</span></div>
                <div><strong>{{ \App\Support\AdminUi::columnLabel('supplier_unread_count') }}</strong><span>{{ $item->supplier_unread_count }}</span></div>
                <div><strong>{{ \App\Support\AdminUi::columnLabel('last_message_at') }}</strong><span>{{ \App\Support\AdminUi::shortDate($item->last_message_at) }}</span></div>
                <div><strong>{{ \App\Support\AdminUi::columnLabel('last_message') }}</strong><span>{{ $item->last_message }}</span></div>
            </div>

            <div class="section-space">
                <h4>{{ __('panel.chat.messages') }}</h4>
                <div class="message-log">
                    @forelse($messages as $message)
                        <div class="message-log__row">
                            <div class="message-log__meta">
                                <strong>{{ $message->sender_name }}</strong>
                                <span>{{ \App\Support\AdminUi::statusLabel($message->sender_type) }} - {{ date('d M Y H:i', strtotime((string) $message->created_at)) }}</span>
                            </div>
                            <p>{{ $message->message_body }}</p>
                        </div>
                    @empty
                        <div class="notice-box">
                            <p>{{ __('panel.chat.no_messages') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="panel form-panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ __('panel.chat.add_admin_message') }}</h3>
                    <p>{{ __('panel.chat.add_admin_message_subtitle') }}</p>
                </div>
            </div>
            <form method="post" action="{{ route('admin.chats.message', $item->id) }}" class="stack-form">
                @csrf
                <label>
                    <span>{{ __('panel.chat.message') }}</span>
                    <textarea name="message_body" rows="4"></textarea>
                </label>
                <div class="form-actions">
                    <a class="ghost-button" href="{{ route('admin.module.index', 'chats') }}">{{ __('panel.common.back') }}</a>
                    <button class="primary-button" type="submit">{{ __('panel.common.save_message') }}</button>
                </div>
            </form>
        </section>
    </section>
@endsection
