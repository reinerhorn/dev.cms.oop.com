<?php
declare(strict_types=1);

namespace CMS\Application\LoginTest;

final class TestLoginHandler
{
    public function handle(array $data, array $pageMeta): array
    {
        error_log('✅ TEST LOGIN HANDLER REACHED');
        error_log('POST=' . json_encode($data));
        error_log('PAGE_META=' . json_encode($pageMeta));

        return [
            'status'   => 'ok',
            'message'  => 'Test-Formular erfolgreich abgeschickt',
            'redirect' => null,
            'errors'   => [],
        ];
    }
}
