@extends('layouts.admin')

@section('page_action', 'show')
@section('title', 'View Chat')

@section('content')
    <section class="module-screen narrow">
        <div class="screen-head">
            <a class="back-link" href="{{ route('admin.module.index', 'chats') }}">&larr; Back to Chats</a>
        </div>

        <section class="panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ $item->subject }}</h3>
                    <p>{{ $item->store_name }} with {{ $item->business_name }}</p>
                </div>
            </div>

            <div class="detail-grid">
                <div><strong>Buyer</strong><span>{{ $item->store_name }}</span></div>
                <div><strong>Supplier</strong><span>{{ $item->business_name }}</span></div>
                <div><strong>Buyer Unread</strong><span>{{ $item->buyer_unread_count }}</span></div>
                <div><strong>Supplier Unread</strong><span>{{ $item->supplier_unread_count }}</span></div>
                <div><strong>Last Message At</strong><span>{{ \App\Support\AdminUi::shortDate($item->last_message_at) }}</span></div>
                <div><strong>Last Message</strong><span>{{ $item->last_message }}</span></div>
            </div>

            <div class="section-space">
                <h4>Messages</h4>
                <div class="message-log">
                    @forelse($messages as $message)
                        <div class="message-log__row">
                            <div class="message-log__meta">
                                <strong>{{ $message->sender_name }}</strong>
                                <span>{{ ucfirst((string) $message->sender_type) }} - {{ date('d M Y H:i', strtotime((string) $message->created_at)) }}</span>
                            </div>
                            <p>{{ $message->message_body }}</p>
                        </div>
                    @empty
                        <div class="notice-box">
                            <p>No messages found in this thread yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="panel form-panel">
            <div class="page-block__header">
                <div>
                    <h3>Add Admin Message</h3>
                    <p>Use this for moderation notes or operational follow-up inside the thread.</p>
                </div>
            </div>
            <form method="post" action="{{ route('admin.chats.message', $item->id) }}" class="stack-form">
                @csrf
                <label>
                    <span>Message</span>
                    <textarea name="message_body" rows="4"></textarea>
                </label>
                <div class="form-actions">
                    <a class="ghost-button" href="{{ route('admin.module.index', 'chats') }}">Back</a>
                    <button class="primary-button" type="submit">Send Message</button>
                </div>
            </form>
        </section>
    </section>
@endsection
