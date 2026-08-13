<?php

namespace App\Agent\Tools;

use App\Agent\Contracts\Tool;
use App\Models\Agent;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Katalog tool yang dapat dipakai agent. Runtime hanya mengenal tool lewat
 * registry ini, sehingga penambahan kemampuan baru cukup dengan mendaftarkan
 * satu kelas — tanpa menyentuh planner, executor, maupun policy.
 */
class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /** @param array<int, class-string<Tool>> $toolClasses */
    public function __construct(private readonly Container $container, array $toolClasses = [])
    {
        foreach ($toolClasses as $class) {
            $this->register($this->container->make($class));
        }
    }

    public function register(Tool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): Tool
    {
        return $this->tools[$name] ?? throw new RuntimeException("Tool '{$name}' tidak terdaftar.");
    }

    /** @return array<string, Tool> */
    public function all(): array
    {
        return $this->tools;
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Nama tool yang boleh dipakai satu agent tertentu (kemampuan agent bisa
     * dipersempit, dan konfigurasi dapat memblokir tool secara global).
     *
     * @return array<int, string>
     */
    public function namesFor(Agent $agent): array
    {
        $blocked = (array) config('agent.policy.blocked_tools', []);

        return array_values(array_filter(
            $this->names(),
            static fn (string $name) => $agent->allowsTool($name) && ! in_array($name, $blocked, true),
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function definitions(?Agent $agent = null): array
    {
        $names = $agent ? $this->namesFor($agent) : $this->names();

        return array_values(array_map(
            fn (string $name) => $this->get($name)->describe()->toArray(),
            $names,
        ));
    }
}
