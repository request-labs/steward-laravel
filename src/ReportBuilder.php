<?php

namespace Request\StewardLaravel;

use Composer\InstalledVersions;

/**
 * Builds the schema 1 report: PHP, the installed version of every package the application
 * requires directly, drift where what is installed differs from composer.lock, and the
 * deployed commit when the application is a git checkout.
 */
class ReportBuilder
{
    public const VERSION = '1.1.0';

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
        $required = array_keys($this->readJson('composer.json')['require'] ?? []);
        $locked = collect($this->readJson('composer.lock')['packages'] ?? [])->pluck('version', 'name')->all();

        $components = [];
        $drift = [];

        foreach ($required as $package) {
            if ($package === 'php' || str_starts_with($package, 'ext-') || ! str_contains($package, '/')) {
                continue;
            }

            $installed = ($this->installedVersion)($package);

            if ($installed === null) {
                continue;
            }

            $components[] = ['kind' => 'composer', 'slug' => $package, 'name' => $package, 'version' => $installed, 'active' => true];

            if (isset($locked[$package]) && $this->normalise($locked[$package]) !== $this->normalise($installed)) {
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
        $git = rtrim($this->basePath, '/').'/.git';

        if (! is_file("{$git}/HEAD")) {
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
