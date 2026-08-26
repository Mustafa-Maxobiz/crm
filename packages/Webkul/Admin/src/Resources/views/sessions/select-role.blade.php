<x-admin::layouts.anonymous>
    <x-slot:title>
        Select Role
    </x-slot>

    <div class="flex h-[100vh] items-center justify-center">
        <div class="flex flex-col items-center gap-5">
            @if ($logo = core()->getConfigData('general.design.admin_logo.logo_image'))
                <img
                    class="h-10 w-[110px]"
                    src="{{ Storage::url($logo) }}"
                    alt="{{ config('app.name') }}"
                />
            @else
                <img
                    class="w-60"
                    src="{{ request()->cookie('dark_mode') ? vite()->asset('images/dark-logo.svg') : vite()->asset('images/logo.svg') }}"
                    alt="{{ config('app.name') }}"
                />
            @endif

            <div class="flex min-w-[400px] flex-col rounded-md bg-white box-shadow dark:bg-gray-900">
                <div class="border-b p-4 dark:border-gray-800">
                    <p class="text-xl font-bold text-gray-800 dark:text-white">
                        Choose how to continue
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ $user->name }} — select an assigned role
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.session.role.store') }}"
                    class="p-4"
                >
                    @csrf

                    @if ($errors->any())
                        <div class="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="flex flex-col gap-2">
                        @foreach ($roles as $role)
                            <label class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 px-3 py-3 hover:border-brandColor dark:border-gray-700">
                                <input
                                    type="radio"
                                    name="role_id"
                                    value="{{ $role->id }}"
                                    class="text-brandColor"
                                    @checked(old('role_id') == $role->id)
                                    required
                                />
                                <span class="text-sm font-medium text-gray-800 dark:text-white">
                                    {{ $user->name }} — {{ $role->name }}
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <button
                        type="submit"
                        class="primary-button mt-4 w-full"
                    >
                        Continue
                    </button>
                </form>

                <div class="border-t px-4 py-3 dark:border-gray-800">
                    <x-admin::form
                        method="DELETE"
                        action="{{ route('admin.session.destroy') }}"
                        id="roleSelectLogout"
                    />
                    <a
                        href="{{ route('admin.session.destroy') }}"
                        class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
                        onclick="event.preventDefault(); document.getElementById('roleSelectLogout').submit();"
                    >
                        Sign out
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-admin::layouts.anonymous>
