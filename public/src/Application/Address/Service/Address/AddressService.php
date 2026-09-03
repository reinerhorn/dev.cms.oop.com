<?php

declare(strict_types=1);

namespace CMS\Application\Address;

use CMS\Application\FormData\Repository\Address\AddressRepository;

final class AddressService
{
    public function __construct(
        private AddressRepository $repository
    ) {}

    public function create(array $data): string
    {
        return $this->repository->insert($data);
    }

    public function update(string $id, array $data): bool
    {
        return $this->repository->update($id, $data);
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function find(string $id): array
    {
        return $this->repository->find($id);
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }
}