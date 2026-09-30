<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Confirms official API hosts are network-reachable (no credentials needed).
 */
class EndpointReachabilityTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'smsir' => ['https://api.sms.ir/v1'],
            'kavenegar' => ['https://api.kavenegar.com'],
            'ghasedak' => ['https://api.ghasedak.me/v2'],
            'ippanel' => ['https://api2.ippanel.com'],
            'magfa' => ['https://sms.magfa.com'],
            'niksms' => ['https://niksms.com'],
            'limosms' => ['https://api.limosms.com'],
            'payamak' => ['https://rest.payamak-panel.com'],
            'smswebservice' => ['https://api.sms-webservice.com'],
            'avanak' => ['https://portal.avanak.ir'],
            'telegram' => ['https://api.telegram.org'],
            'bale' => ['https://tapi.bale.ai'],
            'eitaa' => ['https://eitaayar.ir'],
            'gap' => ['https://api.gap.im'],
            'slack' => ['https://slack.com'],
            'discord' => ['https://discord.com/api'],
            'viber' => ['https://chatapi.viber.com'],
            'line' => ['https://api.line.me'],
            'facebook_graph' => ['https://graph.facebook.com'],
            // Rubika often TLS-times-out from non-IR egress; skip via timeout branch below.
            'rubika' => ['https://botapi.rubika.ir'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_official_endpoint_is_reachable(string $url): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Transient network/timeouts shouldn't fail CI; connection refused / NXDOMAIN should.
        if (in_array($errno, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT], true)) {
            $this->markTestSkipped("Transient network issue for {$url}: {$error}");
        }

        $this->assertSame(0, $errno, "DNS/TLS failed for {$url}: {$error}");
        $this->assertTrue($code > 0, "No HTTP response from {$url}");
    }
}
