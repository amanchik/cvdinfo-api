<?php

namespace App\Mail;

use App\Models\WebContact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactForm extends Mailable
{
    use Queueable, SerializesModels;
    public $web_contact;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(WebContact $web_contact)
    {
        //
        $this->web_contact = $web_contact;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('team@cvdinfo.org')->replyTo($this->web_contact->email)->subject("RE: ".$this->web_contact->name." replied to your post: ")->view('mail.contacted');
    }
}
