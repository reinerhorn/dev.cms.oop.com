<?php
namespace CMS\Application\FormAction;

interface FormActionInterface
{
    public function handle(array $data): array;
}
