<?php

declare(strict_types=1);

namespace Octopus\App\Storage\Interfaces;

interface StorageInterface
{
    public function add(array $conditions, string $event, int $priority): void;

    public function get(array $conditions): string|null;

    public function truncate(): void;
}