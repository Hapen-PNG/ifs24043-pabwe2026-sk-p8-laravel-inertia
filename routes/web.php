<?php

use App\Features\Admin\Controllers\AdminUserController;
use App\Features\Auth\Controllers\AuthController;
use App\Features\Post\Controllers\AdminPostController;
use App\Features\Post\Controllers\PostController;
use App\Features\Post\Controllers\PublicPostController;
use App\Features\Profile\Controllers\ProfileController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// ---- Tingkat 1: Publik (guest) ----
Route::get('/', [PublicPostController::class, 'index'])->name('home');
Route::get('/articles', [PublicPostController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [PublicPostController::class, 'show'])->name('articles.show');

// TEMPORARY: Remove this diagnostic endpoint after resolving the Vercel 500.
Route::get('/__health', function () {
    $appKey = (string) config('app.key');
    $connection = config('database.connections.'.config('database.default'), []);
    $databaseError = null;
    $databaseConnected = false;

    try {
        DB::connection()->getPdo();
        $databaseConnected = true;
    } catch (\Throwable $exception) {
        $message = $exception->getMessage();
        $sensitiveValues = [
            $appKey,
            $connection['url'] ?? null,
            $connection['username'] ?? null,
            $connection['password'] ?? null,
            $connection['host'] ?? null,
            $connection['database'] ?? null,
        ];
        $redactions = [];

        foreach ($sensitiveValues as $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $redactions[] = $value;
            $redactions[] = rawurlencode($value);
            $redactions[] = urlencode($value);
        }

        if ($redactions !== []) {
            $message = str_replace(array_unique($redactions), '[redacted]', $message);
        }

        $databaseError = preg_replace(
            '/(password|passwd|username|user|host|database)\s*[:=]\s*["\']?[^;\s,"\']+/i',
            '$1=[redacted]',
            $message,
        ) ?? 'Database connection failed.';
    }

    return response()->json([
        'php_version' => PHP_VERSION,
        'extensions' => [
            'pdo_pgsql' => extension_loaded('pdo_pgsql'),
            'pgsql' => extension_loaded('pgsql'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
        ],
        'app_key_configured' => $appKey !== '',
        'app_key_base64_format' => str_starts_with($appKey, 'base64:'),
        'database' => [
            'connected' => $databaseConnected,
            'error' => $databaseError,
        ],
    ], $databaseConnected ? 200 : 503);
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// ---- Tingkat 2: Anggota (penulis) ----
Route::middleware(['auth', 'role:member'])->prefix('dashboard/posts')->name('dashboard.posts.')->group(function () {
    Route::get('/', [PostController::class, 'index'])->name('index');
    Route::post('/', [PostController::class, 'store'])->name('store');

    // Rute statis harus sebelum rute {post}
    Route::get('/export', [PostController::class, 'export'])->name('export');
    Route::post('/import', [PostController::class, 'import'])->name('import');
    Route::post('/ai-generate', [PostController::class, 'aiGenerate'])->name('ai-generate');
    Route::post('/ai-save', [PostController::class, 'aiSave'])->name('ai-save');

    Route::middleware('post.owner')->group(function () {
        Route::get('/{post}', [PostController::class, 'show'])->name('show');
        Route::put('/{post}', [PostController::class, 'update'])->name('update');
        Route::delete('/{post}', [PostController::class, 'destroy'])->name('destroy');
        Route::patch('/{post}/publish', [PostController::class, 'publish'])->name('publish');
        Route::post('/{post}/cover', [PostController::class, 'uploadCover'])->name('cover.upload');
        Route::delete('/{post}/cover', [PostController::class, 'removeCover'])->name('cover.remove');
    });
});

// ---- Tingkat 3: Admin ----
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}/posts', [AdminUserController::class, 'posts'])->name('users.posts');
    Route::get('/users/{user}/posts/export', [AdminUserController::class, 'exportPosts'])->name('users.posts.export');
    Route::get('/posts', [AdminPostController::class, 'index'])->name('posts.index');
    Route::get('/posts/export', [AdminPostController::class, 'export'])->name('posts.export');
});
