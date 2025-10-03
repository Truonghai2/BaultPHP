<?php

namespace Modules\User\Console;

use Carbon\Carbon;
use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\User\Infrastructure\Models\OAuth\AccessToken;
use Modules\User\Infrastructure\Models\OAuth\AuthCode;
use Modules\User\Infrastructure\Models\OAuth\RefreshToken;

class OAuthTokenPruneCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * The name and signature of the console command.
     *
     * @return string
     */
    public function signature(): string
    {
        return 'oauth:prune-tokens';
    }

    /**
     * The console command description.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Prune expired and revoked OAuth tokens from the database.';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->io->title('Starting to prune expired and revoked OAuth tokens...');

        $now = Carbon::now();
        $count = 0;

        // 1. Dọn dẹp Access Tokens
        $deletedAccessTokens = AccessToken::where('expires_at', '<', $now)
            ->orWhere('revoked', '=', true)
            ->delete();
        $count += $deletedAccessTokens;
        $this->io->writeln("Pruned {$deletedAccessTokens} expired/revoked access tokens.");

        // 2. Dọn dẹp Refresh Tokens
        $deletedRefreshTokens = RefreshToken::where('expires_at', '<', $now)
            ->orWhere('revoked', '=', true)
            ->delete();
        $count += $deletedRefreshTokens;
        $this->io->writeln("Pruned {$deletedRefreshTokens} expired/revoked refresh tokens.");

        // 3. Dọn dẹp Authorization Codes
        $deletedAuthCodes = AuthCode::where('expires_at', '<', $now)
            ->orWhere('revoked', '=', true)
            ->delete();
        $count += $deletedAuthCodes;
        $this->io->writeln("Pruned {$deletedAuthCodes} expired/revoked authorization codes.");

        $this->io->success("Pruning complete. Total of {$count} records removed.");

        return self::SUCCESS;
    }
}
