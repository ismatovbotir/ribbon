<div>
    <x-page-header :title="__('sellers.employees.index.title')" :subtitle="__('sellers.employees.index.subtitle')">
        <x-slot:actions>
            <x-button variant="primary" wire:click="toggleAddForm">
                <x-icon name="add" class="size-4" />
                {{ __('sellers.employees.index.add_button') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($showAddForm)
        <x-modal kind="form" :title="__('sellers.employees.add.title')" close="toggleAddForm">
            <form id="add-employee" wire:submit="addEmployee" class="space-y-4">
                <x-field :label="__('sellers.full_name_label')" for="name" :required="true" :error="$errors->first('name')">
                    <x-input id="name" type="text" wire:model.blur="name" :error="$errors->has('name')" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" placeholder="{{ __('sellers.full_name_placeholder') }}" autocomplete="name" autofocus />
                </x-field>
                <x-field :label="__('sellers.email_label')" for="email" :required="true" :error="$errors->first('email')">
                    <x-input id="email" type="email" wire:model.blur="email" :error="$errors->has('email')" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" inputmode="email" autocomplete="email" placeholder="{{ __('sellers.email_placeholder') }}" />
                </x-field>
                <x-field :label="__('sellers.password_label')" for="password" :required="true" :error="$errors->first('password')">
                    <x-input id="password" type="password" wire:model.blur="password" :error="$errors->has('password')" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" autocomplete="new-password" placeholder="{{ __('sellers.password_placeholder') }}" />
                </x-field>
                <x-field :label="__('sellers.confirm_password_label')" for="passwordConfirmation" :required="true" :error="$errors->first('passwordConfirmation')">
                    <x-input id="passwordConfirmation" type="password" wire:model.blur="passwordConfirmation" :error="$errors->has('passwordConfirmation')" aria-invalid="{{ $errors->has('passwordConfirmation') ? 'true' : 'false' }}" autocomplete="new-password" />
                </x-field>
                <x-field :label="__('sellers.language_label')" for="locale" :required="true" :error="$errors->first('locale')">
                    <x-language-select id="locale" wire:model="locale" :error="$errors->has('locale')" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button type="button" variant="ghost" wire:click="toggleAddForm">{{ __('sellers.employees.add.cancel_button') }}</x-button>
                <x-button type="submit" form="add-employee" variant="primary" wire:loading.attr="disabled" wire:target="addEmployee">{{ __('sellers.employees.add.submit_button') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

    <x-table>
        <x-slot:head>
            <x-table.th>{{ __('sellers.employees.index.table.name') }}</x-table.th>
            <x-table.th>{{ __('sellers.employees.index.table.role') }}</x-table.th>
            <x-table.th>{{ __('sellers.employees.index.table.email') }}</x-table.th>
            <x-table.th :tier="3">{{ __('sellers.employees.index.table.language') }}</x-table.th>
            <x-table.th><span class="sr-only">{{ __('sellers.employees.index.table.actions') }}</span></x-table.th>
        </x-slot:head>

        <tbody role="rowgroup" class="block divide-y divide-border md:table-row-group">
            @foreach ($members as $member)
                @php($isOwnerRow = (int) $member->pivot->role_id === $ownerRoleId)
                <x-table.row wire:key="member-{{ $member->id }}">
                    <x-table.cell role="primary">{{ $member->name }}</x-table.cell>
                    <x-table.cell role="status">
                        <x-badge :variant="$isOwnerRow ? 'info' : 'muted'">{{ __('sellers.employees.role.'.($isOwnerRow ? 'owner' : 'employee')) }}</x-badge>
                    </x-table.cell>
                    <x-table.cell role="meta" :label="__('sellers.employees.index.table.email')">{{ $member->email }}</x-table.cell>
                    <x-table.cell role="meta" :tier="3">{{ __('sellers.language_option.'.$member->locale) }}</x-table.cell>
                    <x-table.cell role="actions">
                        @if (! $isOwnerRow)
                            <x-menu :title="$member->name" :label="__('sellers.employees.index.table.actions')">
                                <x-menu.item icon="user-remove" wire:click="confirmRemove({{ $member->id }})" :destructive="true">{{ __('sellers.employees.remove_action') }}</x-menu.item>
                            </x-menu>
                        @endif
                    </x-table.cell>
                </x-table.row>
            @endforeach
        </tbody>
    </x-table>

    @if ($showRemoveConfirm)
        <x-modal kind="confirm" :title="__('sellers.employees.remove_confirm_title', ['name' => $this->removingUser?->name])" close="cancelRemove">
            <p>{{ __('sellers.employees.remove_confirm_body') }}</p>
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelRemove">{{ __('sellers.employees.remove_cancel') }}</x-button>
                <x-button variant="danger-solid" wire:click="removeEmployee" wire:loading.attr="disabled" wire:target="removeEmployee">{{ __('sellers.employees.remove_action') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
