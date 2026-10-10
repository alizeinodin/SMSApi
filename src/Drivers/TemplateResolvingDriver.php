<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Drivers\Concerns\DelegatesSmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Templates\TemplateRegistry;

/**
 * Resolves named templates to provider-specific IDs before send.
 */
class TemplateResolvingDriver implements SmsDriver
{
    use DelegatesSmsDriver;

    public function __construct(
        private MessageDriver $driver,
        private TemplateRegistry $templates,
    ) {}

    protected function delegate(): MessageDriver
    {
        return $this->driver;
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $sms = $this->smsDriver('sendTemplate');
        $resolved = $this->resolveTemplateId($templateId);

        if (is_string($templateId) && $this->templates->has($templateId)) {
            $options['template'] = $options['template'] ?? $templateId;
        }

        return $sms->sendTemplate($mobile, $resolved, $parameters, $options);
    }

    /**
     * Resolve named templates by connection name, then by provider type (smsir, …).
     */
    private function resolveTemplateId(int|string $templateId): int|string
    {
        if (! is_string($templateId) || ! $this->templates->has($templateId)) {
            return $templateId;
        }

        $definition = $this->templates->get($templateId);

        foreach ($this->templateDriverCandidates() as $candidate) {
            if ($definition->hasDriver($candidate)) {
                return $definition->idFor($candidate);
            }
        }

        return $this->templates->id($templateId, $this->driver->getName());
    }

    /**
     * @return list<string>
     */
    private function templateDriverCandidates(): array
    {
        $candidates = [$this->driver->getName()];
        $provider = $this->driver->getProvider();

        if ($provider !== '' && ! in_array($provider, $candidates, true)) {
            $candidates[] = $provider;
        }

        return $candidates;
    }
}
