<?php

namespace Core\Contracts\Mail;

interface Mailable
{
    /**
     * Set the recipient of the email.
     *
     * @param object|array|string $address
     * @param string|null $name
     * @return static
     */
    public function to(object|array|string $address, ?string $name = null): static;

    /**
     * Set the sender of the email.
     *
     * @param object|array|string $address
     * @param string|null $name
     * @return static
     */
    public function build(): static;

    /**
     * Send the email using the specified mailer.
     *
     * @param Mailer $mailer
     * @return void
     */
    public function send(Mailer $mailer): void;
}
