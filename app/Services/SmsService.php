<?php

namespace App\Services;

use App\Exceptions\Sms\SmsException;
use App\Jobs\SendSmsJob;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Vonage\Client;
use Vonage\Client\Exception\Exception as VonageException;
use Vonage\Client\Exception\RequestException;
use Vonage\Client\Exception\ServerException;
use Vonage\Messages\Channel\SMS\SMSText;

class SmsService
{
    public function __construct(
        private readonly Client $vonage,
        private readonly PhoneNumberService $phoneNumbers,
    ) {}

    /**
     * Send an SMS immediately via the Vonage Messages API.
     *
     * @return array{message_id: string|null, to: string}
     */
    public function send(string $to, string $message, ?string $from = null): array
    {
        $to = $this->phoneNumbers->normalize($to);
        $from = $from ?? config('services.vonage.sms_from');

        $this->assertCanSend($to, $from, $message);

        try {
            $sms = new SMSText($to, $from, $message);
            $response = $this->vonage->messages()->send($sms);

            $messageId = $response['message_uuid'] ?? null;

            Log::info('SMS sent successfully', [
                'to' => $this->maskPhoneNumber($to),
                'message_id' => $messageId,
            ]);

            return [
                'message_id' => $messageId,
                'to' => $to,
            ];
        } catch (InvalidArgumentException $e) {
            Log::warning('SMS validation failed before send', [
                'to' => $this->maskPhoneNumber($to),
                'error' => $e->getMessage(),
            ]);

            throw new SmsException(
                'Invalid phone number format.',
                previous: $e,
            );
        } catch (RequestException|ServerException $e) {
            $providerCode = (string) $e->getCode();
            $entity = method_exists($e, 'getEntity') ? $e->getEntity() : null;

            Log::error('Vonage SMS request failed', [
                'to' => $this->maskPhoneNumber($to),
                'provider_code' => $providerCode,
                'error' => $e->getMessage(),
                'entity' => $entity,
            ]);

            throw new SmsException(
                $this->mapProviderError($providerCode, $e->getMessage()),
                providerCode: $providerCode,
                previous: $e,
            );
        } catch (VonageException $e) {
            Log::error('Vonage SMS error', [
                'to' => $this->maskPhoneNumber($to),
                'error' => $e->getMessage(),
            ]);

            throw new SmsException(
                'Failed to send SMS. Please try again later.',
                providerCode: (string) $e->getCode(),
                previous: $e,
            );
        }
    }

    /**
     * Queue an SMS for asynchronous delivery.
     */
    public function queue(string $to, string $message, ?string $from = null): void
    {
        $to = $this->phoneNumbers->normalize($to);
        $from = $from ?? config('services.vonage.sms_from');

        $this->assertCanSend($to, $from, $message);

        SendSmsJob::dispatch($to, $message, $from);
    }

    private function assertCanSend(string $to, ?string $from, string $message): void
    {
        if (! $this->phoneNumbers->isValid($to)) {
            throw new SmsException('Invalid phone number format.');
        }

        if (blank($from)) {
            throw new SmsException('SMS sender number is not configured.');
        }

        if (blank(config('services.vonage.api_key')) || blank(config('services.vonage.api_secret'))) {
            throw new SmsException('SMS service is not configured. Please contact support.');
        }

        if (blank(trim($message))) {
            throw new SmsException('SMS message cannot be empty.');
        }
    }

    private function maskPhoneNumber(string $phoneNumber): string
    {
        $digits = preg_replace('/\D/', '', $phoneNumber) ?? '';

        if (strlen($digits) <= 4) {
            return '****';
        }

        return str_repeat('*', max(strlen($digits) - 4, 0)).substr($digits, -4);
    }

    private function mapProviderError(string $code, string $fallback): string
    {
        return match ($code) {
            '1' => 'SMS delivery failed due to a temporary network issue. Please try again.',
            '2' => 'SMS delivery failed due to a temporary issue. Please try again.',
            '3' => 'Invalid phone number.',
            '4' => 'Invalid sender configuration. Please contact support.',
            '5' => 'SMS delivery failed due to a server error. Please try again later.',
            '6' => 'Invalid SMS message content.',
            '7' => 'SMS delivery to this number is not permitted.',
            '8' => 'SMS service is currently unavailable. Please try again later.',
            '9' => 'Daily SMS quota reached. Please try again tomorrow or contact support.',
            '10' => 'Account is not provisioned for SMS. Please contact support.',
            '15' => 'Invalid sender number. Check your Vonage SMS sender configuration.',
            '29' => 'SMS delivery to this destination is not allowed.',
            default => $fallback !== '' ? $fallback : 'Failed to send SMS. Please try again or contact support.',
        };
    }
}
