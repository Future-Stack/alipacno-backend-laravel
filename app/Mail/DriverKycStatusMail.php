<?php

namespace App\Mail;

use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DriverKycStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public Driver $driver;
    public string $status;
    public ?string $rejectReason;
    public string $driverName;

    /**
     * Create a new message instance.
     */
    public function __construct(Driver $driver, string $status, ?string $rejectReason = null)
    {
        $this->driver = $driver;
        $this->status = $status;
        $this->rejectReason = $rejectReason;
        $this->driverName = $driver->name ?: ($driver->user ? $driver->user->name : 'Driver');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->status === 'approved'
            ? 'Congratulations! Your Driver Account & KYC Has Been Approved'
            : ($this->status === 'rejected'
                ? 'Action Required: Your Driver KYC Verification Was Rejected'
                : 'Driver KYC Status Update');

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.driver_kyc_status',
            with: [
                'driver' => $this->driver,
                'status' => $this->status,
                'rejectReason' => $this->rejectReason,
                'driverName' => $this->driverName,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
