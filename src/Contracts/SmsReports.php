<?php

namespace Alizeinodin\SmsApi\Contracts;

use Alizeinodin\SmsApi\DTOs\SendResult;

interface SmsReports
{
    public function getName(): string;

    public function getMessage(int $messageId): SendResult;

    public function getPack(string $packId): SendResult;

    public function getPacks(int $pageNumber = 1, int $pageSize = 100): SendResult;

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult;

    public function getArchivedSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
        bool $sortByNewest = false,
    ): SendResult;
}
