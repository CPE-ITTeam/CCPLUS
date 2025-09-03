<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $reset_link;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($reset_link)
    {
        $this->reset_link = $reset_link;
    }

    /**
     * Build the message.
     *
     * @return $this->view()
     */
    public function build()
    {
        $subject = "CC-Plus password reset request";
        return $this->subject($subject)->view('email/forgotPassword');
    }
}
