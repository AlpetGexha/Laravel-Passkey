<section>
    <header>
        <h2 id="managePasskeys" class="text-lg font-medium text-gray-900">
            {{ __('Manage Passkeys') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Passkeys allow for a more secure, seamless authentication experience on supported devices.') }}
        </p>
    </header>

    <form name="createPasskey" method="post" action="/" class="mt-6 space-y-6" id="passkey">
        @csrf

        <div>
            <x-input-label for="create_passkey_passkey_name" :value="__('Passkey Name')"/>
            <x-text-input id="create_passkey_passkey_name" name="name" autofocus class="mt-1 block w-full"/>
            <x-input-error :messages="$errors->createPasskey->get('name')" class="mt-2"/>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Create Passkey') }}</x-primary-button>
        </div>
    </form>

    <div class="mt-6">
        <h3 class="font-medium text-gray-900">{{ __('Your Passkeys') }}</h3>
        <ul class="mt-2">
            @forelse($user->passkeys as $passkey)

                <li class="px-2 py-2 flex justify-between items-center">
                    <div class="flex flex-col">
                        <span class="font-semibold">{{$passkey->name}}</span>
                        <span
                            class="font-thin text-sm text-gray-600">Added {{$passkey->created_at->diffForHumans()}}</span>
                    </div>

                    <form method="post" action="{{ route('passkey.destroy', ['passkey' => $passkey])  }}">
                        @csrf
                        @method('DELETE')

                        <x-danger-button class="">Remove</x-danger-button>
                    </form>

            @empty
                <li class="px-2 py-2 flex justify-between items-center">
                    <div class="flex flex-col">
                        <span class="font-semibold">No Passkeys</span>
                        <span class="font-thin text-sm text-gray-600">You have not created any passkeys yet.</span>
                    </div>
                    @endforelse

                </li>
        </ul>
    </div>
</section>
