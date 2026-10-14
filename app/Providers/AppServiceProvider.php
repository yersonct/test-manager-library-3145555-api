<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Library\Application\Ports\AccessTokenIssuer;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\Clock;
use Library\Application\Ports\CoverStorage;
use Library\Application\Ports\LibraryRepository;
use Library\Application\Ports\PasswordHasher;
use Library\Application\Ports\ReportReader;
use Library\Adapters\Persistence\EloquentLibraryRepository;
use Library\Adapters\Persistence\EloquentReportReader;
use Library\Adapters\Security\BcryptPasswordHasher;
use Library\Adapters\Security\JwtAccessTokenIssuer;
use Library\Adapters\Storage\LocalCoverStorage;
use Library\Adapters\System\SystemClock;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(PasswordHasher::class, BcryptPasswordHasher::class);
        $this->app->bind(AccessTokenIssuer::class, JwtAccessTokenIssuer::class);
        $this->app->bind(CoverStorage::class, LocalCoverStorage::class);
        $this->app->bind(LibraryRepository::class, EloquentLibraryRepository::class);
        $this->app->bind(BookRepository::class, EloquentLibraryRepository::class);
        $this->app->bind(ReportReader::class, EloquentReportReader::class);
    }

    public function boot(LibraryRepository $library, PasswordHasher $hasher): void
    {
        if (app()->environment('production') && (bool) config('app.debug')) {
            throw new \LogicException('APP_DEBUG must be disabled in production.');
        }
        if (app()->environment('testing')) {
            return;
        }
        if (app()->runningInConsole()) {
            return;
        }
        $table = DB::selectOne('SELECT to_regclass(\'library."user"\') IS NOT NULL AS table_present');
        if (! filter_var($table->table_present, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $username = trim((string) config('library.admin_username'));
        $password = (string) config('library.admin_password');
        if ($username === '' || mb_strlen($username) > 120 || $password === '') {
            throw new \LogicException('Set ADMIN_USERNAME and ADMIN_PASSWORD before starting the API.');
        }
        $existing = $library->userByUsername($username);
        if ($existing && $existing->role !== 'admin') {
            throw new \LogicException('ADMIN_USERNAME already belongs to a user who is not an administrator.');
        }
        if (! $existing) {
            $library->provisionAdmin($username, $hasher->hash($password));
        }
    }
}
