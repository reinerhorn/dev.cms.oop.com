<?php
declare(strict_types=1);


namespace CMS\Application\Interface;
interface CrudHandlerInterface

{

    public function load(string $id): array;

    public function save(array $data): string;

    public function update(string $id, array $data): bool;

    public function delete(string $id): bool;

}