<?php

namespace Request\StewardLaravel;

use Composer\InstalledVersions;

/**
 * Builds the schema 1 report: PHP, the installed version of every package in composer.lock
 * (each marked direct when the application requires it itself, so Steward lists only those
 * but checks every one for vulnerabilities), drift where what is installed differs from
 * composer.lock for a package required directly, and the deployed commit when the
 * application is a git checkout.
 */
class ReportBuilder
{
    public const VERSION = '1.3.0';

    /**
     * @param  callable(string): ?string  $installedVersion  package name => installed version
     */
    public function __construct(
        protected string $basePath,
        protected $installedVersion = null,
    ) {
        $this->installedVersion ??= static fn (string $package): ?string => InstalledVersions::isInstalled($package)
            ? InstalledVersions::getPrettyVersion($package)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $required = array_filter(
            array_keys($this->readJson('composer.json')['require'] ?? []),
            fn (string $package): bool => str_contains($package, '/'),
        );
        $locked = collect($this->readJson('composer.lock')['packages'] ?? [])->pluck('version', 'name')->all();

        $components = [];
        $drift = [];

        foreach (array_unique([...$required, ...array_keys($locked)]) as $package) {
            $installed = ($this->installedVersion)($package);

            if ($installed === null) {
                continue;
            }

            $direct = in_array($package, $required, true);
            $components[] = ['kind' => 'composer', 'slug' => $package, 'name' => $package, 'version' => $installed, 'active' => true, 'direct' => $direct];

            if ($direct && isset($locked[$package]) && $this->normalise($locked[$package]) !== $this->normalise($installed)) {
                $drift[] = ['package' => $package, 'locked' => $locked[$package], 'installed' => $installed];
            }
        }

        return array_filter([
            'schema' => 1,
            'agent' => ['name' => 'steward-laravel', 'version' => self::VERSION],
            'runtime' => ['php' => PHP_VERSION],
            'components' => $components,
            'drift' => $drift,
            'deployment' => $this->gitHead(),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The commit checked out in the application's .git, read from the files so it works
     * without the git binary. A detached HEAD has no branch.
     *
     * @return array{commit: string, branch: ?string}|null
     */
    protected function gitHead(): ?array
    {
        $git = $this->gitDirectory();

        if ($git === null) {
            return null;
        }

        $head = trim((string) file_get_contents("{$git}/HEAD"));

        if ($this->isCommit($head)) {
            return ['commit' => $head, 'branch' => null];
        }

        if (! preg_match('#^ref: (refs/heads/(.+))$#', $head, $ref)) {
            return null;
        }

        $commit = is_file("{$git}/{$ref[1]}") ? trim((string) file_get_contents("{$git}/{$ref[1]}")) : null;

        if ($commit === null && is_file("{$git}/packed-refs")) {
            foreach (file("{$git}/packed-refs", FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (str_ends_with($line, " {$ref[1]}")) {
                    $commit = substr($line, 0, 40);
                    break;
                }
            }
        }

        return $commit !== null && $this->isCommit($commit) ? ['commit' => $commit, 'branch' => $ref[2]] : null;
    }

    /**
     * The nearest .git at or above the application, so an app inside a monorepo reports
     * the commit of the repository it was deployed from.
     */
    protected function gitDirectory(): ?string
    {
        $directory = rtrim($this->basePath, '/');

        while (true) {
            if (is_file("{$directory}/.git/HEAD")) {
                return "{$directory}/.git";
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                return null;
            }

            $directory = $parent;
        }
    }

    protected function isCommit(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{40}$/', $value);
    }

    protected function normalise(string $version): string
    {
        return ltrim($version, 'vV');
    }

    /**
     * @return array<string, mixed>
     */
    protected function readJson(string $file): array
    {
        $path = rtrim($this->basePath, '/').'/'.$file;

        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }
}
