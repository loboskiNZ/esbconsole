<?php

namespace App\Mail\Transport;

use App\Services\MicrosoftGraph\GraphAccessTokenProvider;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class GraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly GraphAccessTokenProvider $tokens,
        private readonly string $sendAsMailbox,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->sendAsMailbox === '') {
            throw new TransportException('Microsoft Graph send-as mailbox is not configured.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $payload = $this->buildPayload($email, $message->getEnvelope());

        $response = Http::withToken($this->tokens->getAccessToken())
            ->timeout(20)
            ->post(
                'https://graph.microsoft.com/v1.0/users/'.rawurlencode($this->sendAsMailbox).'/sendMail',
                $payload,
            );

        if ($response->status() !== 202 && ! $response->successful()) {
            throw new TransportException('Microsoft Graph sendMail request failed.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Email $email, Envelope $envelope): array
    {
        $htmlBody = $email->getHtmlBody();
        $textBody = $email->getTextBody();
        $contentType = $htmlBody !== null ? 'HTML' : 'Text';
        $content = $htmlBody ?? $textBody ?? '';

        return [
            'message' => [
                'subject' => (string) $email->getSubject(),
                'body' => [
                    'contentType' => $contentType,
                    'content' => $content,
                ],
                'from' => [
                    'emailAddress' => [
                        'address' => $this->sendAsMailbox,
                        'name' => (string) config('mail.from.name', ''),
                    ],
                ],
                'toRecipients' => $this->mapAddresses($this->primaryRecipients($email, $envelope)),
                'ccRecipients' => $this->mapAddresses($email->getCc()),
                'bccRecipients' => $this->mapAddresses($email->getBcc()),
            ],
            'saveToSentItems' => false,
        ];
    }

    /**
     * @return list<Address>
     */
    private function primaryRecipients(Email $email, Envelope $envelope): array
    {
        return array_values(array_filter(
            $envelope->getRecipients(),
            fn (Address $address) => ! in_array($address, array_merge($email->getCc(), $email->getBcc()), true),
        ));
    }

    /**
     * @param  list<Address>  $addresses
     * @return list<array<string, array<string, string>>>
     */
    private function mapAddresses(array $addresses): array
    {
        return array_map(
            fn (Address $address) => [
                'emailAddress' => [
                    'address' => $address->getAddress(),
                    'name' => $address->getName() ?? '',
                ],
            ],
            $addresses,
        );
    }

    public function __toString(): string
    {
        return 'graph';
    }
}
