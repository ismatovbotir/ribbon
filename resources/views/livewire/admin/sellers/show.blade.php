<div class="max-w-4xl">
    <x-page-header :title="$seller->name" subtitle="Seller application review.">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.sellers.index') }}" wire:navigate variant="ghost">
                ← All sellers
            </x-button>
            @if ($seller->status !== 'approved')
                <x-button variant="primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve">
                    Approve
                </x-button>
            @endif
            {{-- Reject is an initial-review decision (pending -> rejected)
                 only. An already-approved, active seller gets "Block"
                 instead — see Seller::suspend()'s docblock. --}}
            @if ($seller->status === 'pending')
                <x-button variant="danger-solid" wire:click="openRejectForm">
                    Reject
                </x-button>
            @elseif ($seller->status === 'approved')
                <x-button variant="danger-solid" wire:click="openSuspendForm">
                    Block
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- Status summary — understated, mirrors the Category details card treatment --}}
    <div class="mb-6 flex flex-wrap gap-x-8 gap-y-4 rounded-md bg-surface-subtle p-5">
        <div>
            <p class="text-xs font-medium tracking-wide text-text-muted uppercase">Status</p>
            <div class="mt-1.5">
                @php
                    $variant = match ($seller->status) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'suspended' => 'muted',
                        default => 'muted',
                    };
                @endphp
                <x-badge :variant="$variant" dot>{{ ucfirst($seller->status) }}</x-badge>
            </div>
        </div>

        <div>
            <p class="text-xs font-medium tracking-wide text-text-muted uppercase">Submitted</p>
            <p class="mt-1.5 text-sm text-text-primary">{{ $seller->created_at->format('M j, Y') }}</p>
        </div>

        @if ($seller->approved_at)
            <div>
                <p class="text-xs font-medium tracking-wide text-text-muted uppercase">
                    @if ($seller->status === 'rejected')
                        Rejected
                    @elseif ($seller->status === 'suspended')
                        Suspended
                    @else
                        Last reviewed
                    @endif
                </p>
                <p class="mt-1.5 text-sm text-text-primary">
                    {{ $seller->approved_at->format('M j, Y') }}
                    @if ($seller->approvedBy)
                        by {{ $seller->approvedBy->name }}
                    @endif
                </p>
            </div>
        @endif

        @if (in_array($seller->status, ['rejected', 'suspended']) && $seller->rejected_reason)
            <div class="w-full basis-full border-t border-border/60 pt-3">
                <p class="text-xs font-medium tracking-wide text-text-muted uppercase">
                    {{ $seller->status === 'suspended' ? 'Suspension reason' : 'Rejection reason' }}
                </p>
                <p class="mt-1.5 text-sm text-text-primary">{{ $seller->rejected_reason }}</p>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Company details --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border px-5 py-4">
                <h2 class="text-lg font-semibold text-text-primary">Company details</h2>
            </div>
            <dl class="divide-y divide-border px-5">
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-sm text-text-secondary">Name</dt>
                    <dd class="text-right text-sm font-medium text-text-primary">{{ $seller->name }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-sm text-text-secondary">Address</dt>
                    <dd class="max-w-xs text-right text-sm font-medium text-text-primary">{{ $seller->address }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-sm text-text-secondary">VAT number</dt>
                    <dd class="text-right font-mono text-sm font-medium text-text-primary">{{ $seller->vat_number }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-sm text-text-secondary">Phone</dt>
                    <dd class="text-right font-mono text-sm font-medium text-text-primary">{{ $seller->phone }}</dd>
                </div>
            </dl>
        </div>

        {{-- Owner --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border px-5 py-4">
                <h2 class="text-lg font-semibold text-text-primary">Owner</h2>
            </div>
            @if ($this->owner)
                <dl class="divide-y divide-border px-5">
                    <div class="flex items-start justify-between gap-4 py-3">
                        <dt class="shrink-0 text-sm text-text-secondary">Name</dt>
                        <dd class="text-right text-sm font-medium text-text-primary">{{ $this->owner->name }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3">
                        <dt class="shrink-0 text-sm text-text-secondary">Email</dt>
                        <dd class="text-right text-sm font-medium text-text-primary">{{ $this->owner->email }}</dd>
                    </div>
                </dl>
            @else
                <p class="px-5 py-6 text-sm text-text-secondary">No owner user found for this seller.</p>
            @endif
        </div>
    </div>

    {{-- Reject confirmation modal --}}
    @if ($showRejectForm)
        <div class="fixed inset-0 z-modal-backdrop bg-slate-900/40" wire:click="cancelReject"></div>
        <div class="fixed inset-0 z-modal flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface-overlay p-5 shadow-lg">
                <h2 class="text-lg font-semibold text-text-primary">Reject "{{ $seller->name }}"?</h2>
                <p class="mt-2 text-sm text-text-secondary">
                    Provide a reason — it's recorded on the application and can be shared with the applicant.
                </p>

                <form wire:submit="reject" class="mt-4">
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        Reason <span class="text-danger-600">*</span>
                    </label>
                    <textarea
                        wire:model.blur="rejectReason"
                        rows="3"
                        autofocus
                        class="block w-full rounded-sm border bg-surface px-3 py-2 text-base text-text-primary placeholder:text-text-muted focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none {{ $errors->has('rejectReason') ? 'border-danger-600' : 'border-border' }}"
                        placeholder="e.g. VAT number could not be verified"
                    ></textarea>
                    @error('rejectReason')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-5 flex items-center justify-end gap-2">
                        <x-button type="button" variant="ghost" wire:click="cancelReject">Cancel</x-button>
                        <x-button type="submit" variant="danger-solid" wire:loading.attr="disabled" wire:target="reject">
                            Reject application
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Block confirmation modal --}}
    @if ($showSuspendForm)
        <div class="fixed inset-0 z-modal-backdrop bg-slate-900/40" wire:click="cancelSuspend"></div>
        <div class="fixed inset-0 z-modal flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface-overlay p-5 shadow-lg">
                <h2 class="text-lg font-semibold text-text-primary">Block "{{ $seller->name }}"?</h2>
                <p class="mt-2 text-sm text-text-secondary">
                    This seller is currently active — blocking hides their listings and prevents them from logging in. Provide a reason — it's recorded on the application.
                </p>

                <form wire:submit="suspend" class="mt-4">
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        Reason <span class="text-danger-600">*</span>
                    </label>
                    <textarea
                        wire:model.blur="suspendReason"
                        rows="3"
                        autofocus
                        class="block w-full rounded-sm border bg-surface px-3 py-2 text-base text-text-primary placeholder:text-text-muted focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none {{ $errors->has('suspendReason') ? 'border-danger-600' : 'border-border' }}"
                        placeholder="e.g. Repeated buyer complaints about order fulfillment"
                    ></textarea>
                    @error('suspendReason')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-5 flex items-center justify-end gap-2">
                        <x-button type="button" variant="ghost" wire:click="cancelSuspend">Cancel</x-button>
                        <x-button type="submit" variant="danger-solid" wire:loading.attr="disabled" wire:target="suspend">
                            Block seller
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
