<?php
namespace CMS\Infrastructure\FormAction;

use CMS\Application\FormAction\FormActionInterface;
use RuntimeException;

final class FormActionRegistry
{
    private array $map = [];

    public function register(string $intent, FormActionInterface $action): void
    {
        $this->map[$intent] = $action;
    }

    public function resolve(string $intent): FormActionInterface
    {
        if (!isset($this->map[$intent])) {
            throw new RuntimeException("Unknown intent: $intent");
        }

        return $this->map[$intent];
    }
}