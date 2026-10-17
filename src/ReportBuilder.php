<?php

namespace Request\StewardLaravel;

use Composer\InstalledVersions;

/**
 * Builds the schema 1 report: PHP, the installed version of every package the application
 * requires directly, and drift where what is installed differs from composer.lock.
 */
class ReportBuilder
{
    public const VERSION = '1.0.0';

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

        return [
            'schema' => 1,
            'agent' => ['name' => 'steward-laravel', 'version' => self::VERSION],
            'runtime' => ['php' => PHP_VERSION],
            'components' => $components,
            'drift' => $drift,
        ];
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
