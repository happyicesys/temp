<?php

namespace App\Console\Commands;

use App\Services\Sensor\Jaalee\JaaleeAuthenticator;
use App\Services\Sensor\SensorApiException;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory as HttpFactory;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * Interactive one-off command that obtains a Jaalee Open API token.
 *
 * Flow:
 *   1. Triggers Jaalee to send a verification code to the account.
 *   2. Asks the operator to type the code they received.
 *   3. Exchanges {account, code, timeZone} for a permanent token.
 *   4. Offers to write the token straight into .env as JAALEE_API_TOKEN.
 *
 * Per Jaalee docs the issued token is permanently valid until the
 * account logs in again, so the standard workflow is: run this once,
 * answer "yes" to the .env write prompt, never run it again.
 */
class JaaleeLoginCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sensors:jaalee-login
        {account : The email or phone tied to the Jaalee account}
        {--timezone= : Time zone string sent to Jaalee (default: config(app.timezone))}
        {--no-write : Print the token but do not touch .env}';

    /**
     * The console command description.
     */
    protected $description = 'Obtain a Jaalee Open API token via the code + login flow and (optionally) save it to .env.';

    public function handle(): int
    {
        $account = (string) $this->argument('account');
        $timeZone = $this->resolveTimeZone();

        $authenticator = $this->makeAuthenticator();

        $this->line("Requesting verification code for <info>{$account}</info> …");

        try {
            $authenticator->requestCode($account);
        } catch (SensorApiException $e) {
            $this->error('Failed to request verification code: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('Code sent. Check the inbox / SMS for that account.');

        $code = text(
            label: 'Enter the verification code',
            placeholder: '1234',
            required: true,
            validate: fn (string $value): ?string => trim($value) === ''
                ? 'Verification code cannot be blank.'
                : null,
        );

        $this->line("Logging in with time zone <info>{$timeZone}</info> …");

        try {
            $token = $authenticator->login($account, trim($code), $timeZone);
        } catch (SensorApiException $e) {
            $this->error('Login failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Login successful. Token issued:');
        $this->line($token);

        if ($this->option('no-write')) {
            $this->warn('--no-write was set; .env was not modified. Paste the token above into JAALEE_API_TOKEN manually.');

            return self::SUCCESS;
        }

        $shouldWrite = confirm(
            label: 'Save this token to .env as JAALEE_API_TOKEN?',
            default: true,
        );

        if (! $shouldWrite) {
            $this->warn('Skipped. Paste the token above into JAALEE_API_TOKEN manually.');

            return self::SUCCESS;
        }

        $this->writeTokenToEnv($token);
        $this->info('JAALEE_API_TOKEN updated in .env. You may want to clear the config cache: php artisan config:clear');

        return self::SUCCESS;
    }

    protected function resolveTimeZone(): string
    {
        $override = $this->option('timezone');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $configured = (string) config('app.timezone', 'UTC');

        // Jaalee accepts both IANA names ("Asia/Singapore") and offset
        // shorthands ("GMT+08:00"). config('app.timezone') is always IANA,
        // which is the format their docs explicitly call out, so we just
        // pass it straight through.
        return $configured;
    }

    /**
     * Build the authenticator from config, without going through the
     * container. Keeps the command testable without a service binding.
     */
    protected function makeAuthenticator(): JaaleeAuthenticator
    {
        return new JaaleeAuthenticator(
            http: $this->laravel->make(HttpFactory::class),
            baseUrl: (string) config('services.jaalee.base_url', 'https://sensor.jaalee.com'),
            timeoutSeconds: (int) config('services.jaalee.timeout', 10),
        );
    }

    /**
     * Update JAALEE_API_TOKEN in .env in place, preserving every other line.
     *
     * - If the key already exists, replace just its value.
     * - If the key does not exist, append it.
     * - Quote the value so tokens that contain "=" or "/" don't confuse
     *   Dotenv's parser.
     */
    protected function writeTokenToEnv(string $token): void
    {
        $path = $this->laravel->environmentFilePath();

        $contents = file_exists($path) ? file_get_contents($path) : '';
        $quoted = '"'.addcslashes($token, "\"\\\n\r").'"';
        $line = 'JAALEE_API_TOKEN='.$quoted;

        if (preg_match('/^JAALEE_API_TOKEN=.*$/m', $contents)) {
            $contents = preg_replace('/^JAALEE_API_TOKEN=.*$/m', $line, $contents);
        } else {
            $contents = rtrim($contents, "\n")."\n".$line."\n";
        }

        file_put_contents($path, $contents);
    }
}
