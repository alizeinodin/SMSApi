<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Drivers\Concerns\FlattensTemplateParameters;
use Alizeinodin\SmsApi\Drivers\Concerns\HasProviderIdentity;
use Alizeinodin\SmsApi\Drivers\Concerns\ResolvesSenderLine;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Shared Payamak Panel REST API (ملی‌پیامک، فراپیامک، پیامیتو، …).
 *
 * @see https://www.payamak-panel.com/rest.html
 */
abstract class PayamakPanelDriver extends AbstractDriver
{
    use FlattensTemplateParameters;
    use HasProviderIdentity;
    use ResolvesSenderLine;

    protected const LOCATION_INBOX = 1;

    protected const LOCATION_SENT = 2;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makePayamakClient($config));
    }

    protected function driverConfig(): array
    {
        return $this->config;
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toPayamakResponse($this->payamakRequest('POST', 'SendSMS/SendSMS', [
            'to' => implode(',', $mobiles),
            'from' => $this->resolveSenderLine($options, 'from/line_number'),
            'text' => $message,
            'isflash' => false,
        ]));
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        return $this->send($mobiles, $message, $options);
    }

    /**
     * SendMultipleSMS pairs each text with the recipient at the same index; it is the one
     * REST method that wants a JSON body instead of a form post.
     */
    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        return $this->toPayamakResponse($this->payamakRequest('POST', 'SendSMS/SendMultipleSMS', [
            'to' => array_values($this->normalizeMobiles($mobiles)),
            'from' => $this->resolveSenderLine($options, 'from/line_number'),
            'text' => array_values($messages),
        ], asJson: true));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $mobile = $this->normalizeMobiles($mobile)[0] ?? $mobile;

        return $this->toPayamakResponse($this->payamakRequest('POST', 'SendSMS/SendByBaseNumber2', [
            'to' => $mobile,
            'bodyId' => (int) $templateId,
            'args' => $this->flattenTemplateParameters($parameters),
        ]));
    }

    public function getCredit(): SendResult
    {
        return $this->toPayamakResponse($this->payamakRequest('POST', 'SendSMS/GetCredit', []));
    }

    public function getLines(): SendResult
    {
        return $this->toPayamakResponse($this->payamakRequest('POST', 'SendSMS/GetUserNumbers', []));
    }

    /**
     * GetDeliveries2 answers with a Value array holding one status per recipient of the pack.
     */
    public function getMessage(int $messageId): SendResult
    {
        return $this->toPayamakResponse($this->payamakRequest('POST', 'SendSMS/GetDeliveries2', [
            'recId' => (string) $messageId,
        ]));
    }

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toPayamakResponse($this->getPanelMessages(self::LOCATION_SENT, $pageNumber, $pageSize));
    }

    /**
     * GetMessages has no date filter, so archived sends read the same sent box as live sends.
     */
    public function getArchivedSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toPayamakResponse($this->getPanelMessages(self::LOCATION_SENT, $pageNumber, $pageSize));
    }

    public function getLatestReceived(int $count = 100): SendResult
    {
        return $this->toPayamakResponse($this->getPanelMessages(self::LOCATION_INBOX, 1, $count));
    }

    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toPayamakResponse($this->getPanelMessages(self::LOCATION_INBOX, $pageNumber, $pageSize));
    }

    public function getArchivedReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->toPayamakResponse($this->getPanelMessages(self::LOCATION_INBOX, $pageNumber, $pageSize));
    }

    /**
     * RemoveSchedule is only published on the SOAP service (Schedule.asmx); the REST API has
     * no scheduling controller, so cancelling stays unsupported here.
     */
    public function cancelScheduled(string $packId): SendResult
    {
        throw new UnsupportedDriverMethodException($this->getName(), __FUNCTION__);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function makePayamakClient(array $config): Client
    {
        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://rest.payamak-panel.com/api'), '/').'/',
            'timeout' => self::requestTimeout($config),
            'connect_timeout' => self::connectTimeout($config),
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /**
     * GetMessages pages through the sent box (location 2) or the inbox (location 1).
     *
     * @return array<string, mixed>
     */
    protected function getPanelMessages(int $location, int $pageNumber, int $pageSize): array
    {
        return $this->payamakRequest('POST', 'SendSMS/GetMessages', array_filter([
            'location' => $location,
            'index' => max(0, ($pageNumber - 1) * $pageSize),
            'count' => $pageSize,
            'from' => $this->lineNumber !== null ? (string) $this->lineNumber : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function payamakRequest(string $method, string $uri, array $payload, bool $asJson = false): array
    {
        $username = (string) $this->config('username', '');
        $password = (string) $this->config('password', '');

        if ($username === '' || $password === '') {
            throw new SmsApiException(sprintf('[%s] username/password are not configured.', $this->getName()));
        }

        $form = array_merge([
            'username' => $username,
            'password' => $password,
        ], $payload);

        if (! $asJson && isset($form['args']) && is_array($form['args'])) {
            $form['args'] = json_encode(array_values($form['args']), JSON_UNESCAPED_UNICODE);
        }

        try {
            $response = $this->client->request(
                $method,
                ltrim($uri, '/'),
                $asJson ? ['json' => $form] : ['form_params' => $form],
            );
        } catch (GuzzleException $e) {
            throw $this->transportException($e);
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            $decoded = ['Value' => trim($body), 'RetStatus' => $response->getStatusCode() < 400 ? 1 : 0];
        }

        $retStatus = (int) ($decoded['RetStatus'] ?? 0);
        $value = $decoded['Value'] ?? null;
        // Panel APIs often return RetStatus=1 with a negative Value for business errors.
        // Report endpoints (GetDeliveries2, GetMessages) answer with a Value array instead.
        $negativeValue = is_scalar($value) && is_numeric($value) && (int) $value < 0;

        if ($response->getStatusCode() >= 400 || $retStatus === 0 || $negativeValue) {
            throw new SmsApiException(
                (string) ($decoded['StrRetStatus'] ?? $decoded['Message'] ?? 'Payamak panel request failed.'),
                $negativeValue ? (int) $value : ($retStatus !== 0 ? $retStatus : (is_scalar($value) ? (int) $value : 0)),
                $decoded,
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function toPayamakResponse(array $raw): SendResult
    {
        return new SendResult(
            success: true,
            message: (string) ($raw['StrRetStatus'] ?? 'OK'),
            data: $raw,
            driver: $this->getName(),
            raw: $raw,
            statusCode: (int) ($raw['RetStatus'] ?? 1),
        );
    }
}
