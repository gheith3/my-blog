<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Connect to the blog</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 px-4 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <div class="w-full max-w-sm rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-4 text-center">
            <p class="text-lg font-semibold">Blog</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $client->name }}</span> wants to connect to your blog
            </p>
        </div>

        <div class="mb-6 rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800">
            <p class="mb-2 font-medium text-gray-700 dark:text-gray-300">This will allow it to:</p>
            <ul class="list-disc space-y-1 ps-5 text-gray-600 dark:text-gray-400">
                @foreach ($scopes as $scope)
                    <li>
                        {{ match ($scope->id) {
                            'mcp:use' => 'Fully manage your posts and comments, including creating, editing, and deleting them',
                            'posts:read' => 'List and read posts, categories, and tags',
                            'posts:write' => 'Create, edit, and delete posts',
                            'comments:read' => 'List and read comments',
                            'comments:write' => 'Create, edit, approve, and delete comments',
                            default => $scope->description,
                        } }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="flex gap-3">
            <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                @csrf
                <input type="hidden" name="state" value="{{ request('state') }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="w-full rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                    Connect
                </button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ request('state') }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    Cancel
                </button>
            </form>
        </div>

        <p class="mt-4 text-center text-xs text-gray-400 dark:text-gray-500">
            Signed in as {{ $user->email }}
        </p>
    </div>
</body>
</html>
