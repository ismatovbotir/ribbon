<div class="mx-auto w-full max-w-4xl">
    <x-page-header :title="$seller->name" :subtitle="__('admin.ui.seller_application_review')" :back-url="route('admin.sellers.index')" :back-label="__('admin.ui.all_sellers')">
        <x-slot:meta>
            <x-status-badge :status="$seller->status" />
            <span class="font-mono text-xs text-text-muted">#{{ $seller->id }}</span>
            <span class="font-mono text-xs text-text-muted">{{ $seller->created_at->translatedFormat('d M Y') }}</span>
        </x-slot:meta>
    </x-page-header>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($seller->status === 'suspended')
        <x-alert variant="warning">{{ __('admin.ui.this_seller_is_blocked_their_listings_are_hidden_and_they_ca') }}</x-alert>
    @endif

    <div class="space-y-6">
        <x-section :title="__('admin.ui.moderation')">
            <dl>
                <x-fact :label="__('admin.ui.status')"><x-status-badge :status="$seller->status" /></x-fact>
                <x-fact :label="__('admin.ui.submitted')" :mono="true">{{ $seller->created_at->translatedFormat('d M Y H:i') }}</x-fact>
                @if ($seller->approved_at)
                    <x-fact :label="$seller->status === 'rejected' ? __('admin.ui.rejected_at') : ($seller->status === 'suspended' ? __('admin.ui.suspended_at') : __('admin.ui.last_reviewed'))">
                        <span class="font-mono">{{ $seller->approved_at->translatedFormat('d M Y H:i') }}</span>
                        @if ($seller->approvedBy) {{ __('admin.ui.by_name', ['name' => $seller->approvedBy->name]) }} @endif
                    </x-fact>
                @endif
                @if (in_array($seller->status, ['rejected', 'suspended']) && $seller->rejected_reason)
                    <x-fact :label="$seller->status === 'suspended' ? __('admin.ui.suspension_reason') : __('admin.ui.rejection_reason')">
                        <blockquote class="border-l-[3px] border-border-strong pl-3 text-text-secondary">{{ $seller->rejected_reason }}</blockquote>
                    </x-fact>
                @endif
            </dl>
        </x-section>

        <x-section :title="__('admin.ui.company_details')">
            <dl>
                <x-fact :label="__('admin.ui.name')">{{ $seller->name }}</x-fact>
                <x-fact :label="__('admin.ui.address')">{{ $seller->address }}</x-fact>
                <x-fact :label="__('admin.ui.vat_number')" :mono="true">{{ $seller->vat_number }}</x-fact>
                <x-fact :label="__('admin.ui.phone')" :mono="true">{{ $seller->phone }}</x-fact>
                <x-fact :label="__('storefront.seller.vat_payer')">{{ $seller->is_vat_payer === null ? '—' : ($seller->is_vat_payer ? __('storefront.seller.vat_payer_yes') : __('storefront.seller.vat_payer_no')) }}</x-fact>
                <x-fact :label="__('storefront.seller.payment_methods')">{{ collect($seller->payment_methods ?? [])->map(fn ($method) => __('storefront.seller.payment_method.'.$method))->implode(', ') ?: '—' }}</x-fact>
                <x-fact :label="__('storefront.seller.warranty')"><span class="whitespace-pre-line">{{ $seller->warranty_info ?: '—' }}</span></x-fact>
            </dl>
        </x-section>

        <x-section :title="__('admin.ui.owner')">
            @if ($this->owner)
                <dl>
                    <x-fact :label="__('admin.ui.name')">{{ $this->owner->name }}</x-fact>
                    <x-fact :label="__('admin.ui.email')">{{ $this->owner->email }}</x-fact>
                </dl>
            @else
                <p class="text-sm text-text-secondary">{{ __('admin.ui.no_owner_user_found_for_this_seller') }}</p>
            @endif
        </x-section>

        @can('manage-subscriptions')
            @php($sub = $this->subscription)
            @php($subVariant = ['none' => 'muted', 'active' => 'success', 'grace' => 'warning', 'lapsed' => 'danger'][$sub['status']] ?? 'muted')
            <x-section :title="__('admin.subscription.title')" id="subscription">
                <dl>
                    <x-fact :label="__('admin.subscription.title')"><x-badge :variant="$subVariant" :dot="true">{{ __('admin.subscription.status.'.$sub['status']) }}</x-badge></x-fact>
                    <x-fact :label="__('admin.subscription.paid_until')" :mono="true">{{ $sub['paid_until']?->translatedFormat('d M Y') ?? __('admin.subscription.never') }}</x-fact>
                    @if ($sub['days_left'] !== null && $sub['status'] === 'active')
                        <x-fact :label="__('admin.subscription.days_left')" :mono="true">{{ __('admin.subscription.days_left_value', ['count' => $sub['days_left']]) }}</x-fact>
                    @endif
                    @if ($sub['status'] === 'grace')
                        <x-fact :label="__('admin.subscription.grace_ends')" :mono="true">{{ $sub['grace_ends_at']->translatedFormat('d M Y') }}</x-fact>
                    @endif
                    <x-fact :label="__('admin.subscription.hidden_products')"><span class="font-mono">{{ $sub['hidden_products_count'] }}</span> <span class="text-xs text-text-muted">{{ __('admin.subscription.hidden_hint') }}</span></x-fact>
                </dl>

                <div class="mt-4 flex flex-col gap-2 md:flex-row">
                    <x-button variant="primary" wire:click="openSubscriptionDrawer('payment')">{{ __('admin.subscription.record_payment') }}</x-button>
                    <x-button variant="secondary" wire:click="openSubscriptionDrawer('free')">{{ __('admin.subscription.grant_free') }}</x-button>
                    <x-button variant="secondary" wire:click="openSubscriptionDrawer('correct')">{{ __('admin.subscription.correct_date') }}</x-button>
                </div>

                <h3 class="mt-6 mb-2 text-sm font-semibold text-text-primary">{{ __('admin.subscription.payments') }}</h3>
                @if ($this->subscriptionPayments->isEmpty())
                    <p class="text-sm text-text-secondary">{{ __('admin.subscription.payments_empty') }}</p>
                @else
                    <x-table>
                        <x-slot:head>
                            <x-table.th>{{ __('admin.subscription.col_date') }}</x-table.th>
                            <x-table.th>{{ __('admin.subscription.col_period') }}</x-table.th>
                            <x-table.th align="right">{{ __('admin.subscription.col_amount') }}</x-table.th>
                            <x-table.th align="right">{{ __('admin.subscription.col_months') }}</x-table.th>
                            <x-table.th>{{ __('admin.subscription.col_by') }}</x-table.th>
                            <x-table.th>{{ __('admin.subscription.col_note') }}</x-table.th>
                        </x-slot:head>
                        <tbody role="rowgroup" class="block divide-y divide-border md:table-row-group">
                            @foreach ($this->subscriptionPayments as $payment)
                                <x-table.row wire:key="sub-payment-{{ $payment->id }}">
                                    <x-table.cell role="meta" :label="__('admin.subscription.col_date')" :mono="true" class="md:whitespace-nowrap">{{ $payment->created_at->translatedFormat('d M Y') }}</x-table.cell>
                                    <x-table.cell role="meta" :label="__('admin.subscription.col_period')" :mono="true" class="md:whitespace-nowrap">{{ $payment->period_start->translatedFormat('d M Y') }} – {{ $payment->period_end->translatedFormat('d M Y') }}</x-table.cell>
                                    <x-table.cell role="meta" :label="__('admin.subscription.col_amount')" :mono="true" align="right" class="md:whitespace-nowrap">
                                        {{ number_format($payment->amount, 0, '.', ' ') }}
                                        @if ($payment->amount === 0)<x-badge variant="info">{{ __('admin.subscription.free') }}</x-badge>@endif
                                    </x-table.cell>
                                    <x-table.cell role="meta" :label="__('admin.subscription.col_months')" :mono="true" align="right">{{ $payment->months }}</x-table.cell>
                                    <x-table.cell role="meta" :label="__('admin.subscription.col_by')">{{ $payment->recordedBy?->name ?? '—' }}</x-table.cell>
                                    <x-table.cell role="meta" :label="__('admin.subscription.col_note')">{{ $payment->note ?? '—' }}</x-table.cell>
                                </x-table.row>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif

                <h3 class="mt-6 mb-2 text-sm font-semibold text-text-primary">{{ __('admin.subscription.audit') }}</h3>
                @if ($this->subscriptionAudits->isEmpty())
                    <p class="text-sm text-text-secondary">{{ __('admin.subscription.audit_empty') }}</p>
                @else
                    <ul class="divide-y divide-border border border-border-strong">
                        @foreach ($this->subscriptionAudits as $audit)
                            <li wire:key="sub-audit-{{ $audit->id }}" class="px-3 py-2 text-sm">
                                <p class="text-text-primary">
                                    {{ __('admin.subscription.audit.'.$audit->action) }}
                                    <span class="font-mono text-xs text-text-secondary">{{ __('admin.subscription.audit_change', ['old' => $audit->old_paid_until?->format('Y-m-d') ?? '—', 'new' => $audit->new_paid_until?->format('Y-m-d') ?? '—']) }}</span>
                                </p>
                                <p class="text-xs text-text-muted">
                                    <span class="font-mono">{{ $audit->created_at?->translatedFormat('d M Y H:i') }}</span> · {{ $audit->user?->name ?? '—' }}
                                    @if ($audit->note) · {{ $audit->note }} @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-section>
        @endcan
    </div>

    {{-- Decision bar. Reject is an initial-review decision only; an active seller gets "Block" instead (see Seller::suspend()). --}}
    @if ($seller->status === 'pending')
        <x-alert variant="info" class="mt-6">{{ __('admin.sellers.approval_check') }}</x-alert>
    @endif

    <x-action-bar>
        @if ($seller->status !== 'approved')
            <x-button variant="primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve">{{ __('admin.ui.approve') }}</x-button>
        @else
            <span class="self-center text-sm text-text-muted">{{ __('admin.ui.already_approved') }}</span>
        @endif
        @if (in_array($seller->status, ['pending', 'approved'], true))
            <x-menu :title="__('admin.ui.more_decisions')" :label="__('admin.ui.more')">
                <x-slot:trigger><x-button variant="secondary" aria-haspopup="menu">{{ __('admin.ui.more') }}</x-button></x-slot:trigger>
                @if ($seller->status === 'pending')
                    <x-menu.item icon="reject" wire:click="openRejectForm" :destructive="true">{{ __('admin.ui.reject') }}</x-menu.item>
                @else
                    <x-menu.item icon="block" wire:click="openSuspendForm" :destructive="true">{{ __('admin.ui.block') }}</x-menu.item>
                @endif
            </x-menu>
        @endif
    </x-action-bar>

    @if ($showRejectForm)
        <x-modal kind="confirm" :title="__('admin.ui.reject_this_application')" close="cancelReject">
            <p>{{ __('admin.ui.provide_a_reason_it_is_recorded_on_the_application_and_can_b') }}</p>
            <form id="reject-form" wire:submit="reject" class="mt-4">
                <x-field :label="__('admin.ui.reason')" for="rejectReason" :required="true" :error="$errors->first('rejectReason')">
                    <x-textarea id="rejectReason" wire:model.blur="rejectReason" rows="3" autofocus :error="$errors->has('rejectReason')" :placeholder="__('admin.ui.e_g_vat_number_could_not_be_verified')" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelReject">{{ __('admin.ui.cancel') }}</x-button>
                <x-button type="submit" form="reject-form" variant="danger-solid" wire:loading.attr="disabled" wire:target="reject">{{ __('admin.ui.reject_application') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

    @if ($showSuspendForm)
        <x-modal kind="confirm" :title="__('admin.ui.block_this_seller')" close="cancelSuspend">
            <p>{{ __('admin.ui.this_seller_is_currently_active_blocking_hides_their_listing') }}</p>
            <form id="suspend-form" wire:submit="suspend" class="mt-4">
                <x-field :label="__('admin.ui.reason')" for="suspendReason" :required="true" :error="$errors->first('suspendReason')">
                    <x-textarea id="suspendReason" wire:model.blur="suspendReason" rows="3" autofocus :error="$errors->has('suspendReason')" :placeholder="__('admin.ui.e_g_repeated_buyer_complaints')" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelSuspend">{{ __('admin.ui.cancel') }}</x-button>
                <x-button type="submit" form="suspend-form" variant="danger-solid" wire:loading.attr="disabled" wire:target="suspend">{{ __('admin.ui.block_seller') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

    @can('manage-subscriptions')
        @if ($subscriptionDrawer === 'payment')
            <x-drawer :title="__('admin.subscription.record_payment')" close="closeSubscriptionDrawer">
                <form id="payment-form" wire:submit="recordPayment" class="space-y-4">
                    <x-field :label="__('admin.subscription.amount')" for="paymentAmount" :required="true" :error="$errors->first('paymentAmount')">
                        <x-input id="paymentAmount" type="number" inputmode="numeric" min="0" step="1" class="font-mono" wire:model="paymentAmount" :error="$errors->has('paymentAmount')" />
                    </x-field>
                    <x-field :label="__('admin.subscription.months')" for="paymentMonths" :required="true" :error="$errors->first('paymentMonths')">
                        <x-input id="paymentMonths" type="number" inputmode="numeric" min="1" max="12" step="1" class="font-mono" wire:model="paymentMonths" :error="$errors->has('paymentMonths')" />
                    </x-field>
                    <x-field :label="__('admin.subscription.note')" for="paymentNote" :error="$errors->first('paymentNote')">
                        <x-textarea id="paymentNote" rows="3" wire:model="paymentNote" :error="$errors->has('paymentNote')" />
                    </x-field>
                </form>
                <x-slot:footer>
                    <x-button variant="ghost" wire:click="closeSubscriptionDrawer">{{ __('admin.subscription.cancel') }}</x-button>
                    <x-button type="submit" form="payment-form" variant="primary" wire:loading.attr="disabled" wire:target="recordPayment">{{ __('admin.subscription.save') }}</x-button>
                </x-slot:footer>
            </x-drawer>
        @elseif ($subscriptionDrawer === 'free')
            <x-drawer :title="__('admin.subscription.grant_free')" close="closeSubscriptionDrawer">
                <form id="free-form" wire:submit="grantFreeMonths" class="space-y-4">
                    <x-field :label="__('admin.subscription.months')" for="freeMonths" :required="true" :error="$errors->first('freeMonths')">
                        <x-input id="freeMonths" type="number" inputmode="numeric" min="1" max="12" step="1" class="font-mono" wire:model="freeMonths" :error="$errors->has('freeMonths')" />
                    </x-field>
                    <x-field :label="__('admin.subscription.note')" for="freeNote" :error="$errors->first('freeNote')">
                        <x-textarea id="freeNote" rows="3" wire:model="freeNote" :error="$errors->has('freeNote')" />
                    </x-field>
                </form>
                <x-slot:footer>
                    <x-button variant="ghost" wire:click="closeSubscriptionDrawer">{{ __('admin.subscription.cancel') }}</x-button>
                    <x-button type="submit" form="free-form" variant="primary" wire:loading.attr="disabled" wire:target="grantFreeMonths">{{ __('admin.subscription.save') }}</x-button>
                </x-slot:footer>
            </x-drawer>
        @elseif ($subscriptionDrawer === 'correct')
            <x-drawer :title="__('admin.subscription.correct_date')" close="closeSubscriptionDrawer">
                <form id="correct-form" wire:submit="correctPaidUntil" class="space-y-4">
                    <x-field :label="__('admin.subscription.new_date')" for="correctDate" :hint="__('admin.subscription.date_hint')" :error="$errors->first('correctDate')">
                        <x-input id="correctDate" type="date" class="font-mono" wire:model="correctDate" :error="$errors->has('correctDate')" />
                    </x-field>
                    <x-field :label="__('admin.subscription.reason')" for="correctReason" :required="true" :error="$errors->first('correctReason')">
                        <x-textarea id="correctReason" rows="3" wire:model="correctReason" :error="$errors->has('correctReason')" :placeholder="__('admin.subscription.reason_placeholder')" />
                    </x-field>
                </form>
                <x-slot:footer>
                    <x-button variant="ghost" wire:click="closeSubscriptionDrawer">{{ __('admin.subscription.cancel') }}</x-button>
                    <x-button type="submit" form="correct-form" variant="primary" wire:loading.attr="disabled" wire:target="correctPaidUntil">{{ __('admin.subscription.save') }}</x-button>
                </x-slot:footer>
            </x-drawer>
        @endif
    @endcan
</div>
