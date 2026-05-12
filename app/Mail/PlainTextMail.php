<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PlainTextMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $body;

    public function __construct(string $subject, string $body)
    {
        $this->subject = $subject;
        $this->body = $body;
    }

    public function build(): self
    {
        return $this->text('emails.plain')->with(['body' => $this->body]);
    }
}
