<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CallLog;
use App\Models\User;
use App\Services\TwilioService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwilioWebhookController extends Controller
{
    /**
     * Handle incoming Twilio voice webhook.
     */
    public function voice(Request $request, TwilioService $twilioService)
    {
        $callSid = $request->input('CallSid');
        $from = $request->input('From', 'Anonymous');
        $to = $request->input('To');
        $callerZip = $request->input('CallerZip');
        $callerCity = $request->input('CallerCity');

        Log::info('Twilio Incoming Voice Webhook', [
            'call_sid' => $callSid,
            'from' => $from,
            'to' => $to,
        ]);

        // Resolve branch from incoming 'To' number or fallback to primary branch
        $branch = Branch::where('phone', $to)->first() ?? Branch::first();
        $branchId = $branch?->id ?? 1;

        // Resolve customer by phone
        $user = User::where('phone', $from)->orWhere('phone', str_replace('+', '', $from))->first();
        $customerName = $user?->name ?? 'Incoming Caller';
        $postcode = $callerZip ?: ($user?->defaultAddress?->postcode ?? $user?->addresses?->first()?->postcode ?? null);

        // Record incoming call in call_logs table
        if (!empty($callSid)) {
            CallLog::updateOrCreate(
                ['call_sid' => $callSid],
                [
                    'branch_id' => $branchId,
                    'user_id' => $user?->id,
                    'customer_name' => $customerName,
                    'phone' => $from,
                    'postcode' => $postcode,
                    'call_type' => 'incoming',
                    'call_status' => 'answered',
                    'call_duration' => 0,
                    'started_at' => now(),
                    'notes' => 'Incoming call via Twilio number: ' . $to . ($callerCity ? " ({$callerCity})" : ''),
                ]
            );
        }

        $callbackUrl = url('/api/v1/twilio/status-callback');
        $forwardTo = config('services.twilio.forward_to') ?: ($branch?->phone ?? null);

        $twiml = $twilioService->generateForwardTwiML($callbackUrl, $forwardTo);

        return response($twiml, 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Handle Twilio call status callback (when call ends / changes status).
     */
    public function statusCallback(Request $request)
    {
        $callSid = $request->input('CallSid') ?? $request->input('DialCallSid');
        $callStatus = strtolower($request->input('CallStatus', ''));
        $dialCallStatus = strtolower($request->input('DialCallStatus', ''));
        $duration = (int) ($request->input('DialCallDuration') ?? $request->input('CallDuration') ?? $request->input('Duration') ?? 0);
        $recordingUrl = $request->input('RecordingUrl');

        Log::info('Twilio Status Callback', [
            'call_sid' => $callSid,
            'call_status' => $callStatus,
            'dial_status' => $dialCallStatus,
            'duration' => $duration,
            'recording_url' => $recordingUrl,
        ]);

        if (empty($callSid)) {
            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                ->header('Content-Type', 'text/xml');
        }

        $callLog = CallLog::where('call_sid', $callSid)->first();

        // Map Twilio statuses to our internal call_status
        $finalStatus = 'answered';
        $finalOutcome = null;

        $effectiveStatus = $dialCallStatus ?: $callStatus;

        if (in_array($effectiveStatus, ['no-answer', 'busy', 'failed', 'canceled'])) {
            $finalStatus = match ($effectiveStatus) {
                'busy' => 'busy',
                'canceled' => 'cancelled',
                default => 'missed',
            };
            $finalOutcome = 'callback';
        } elseif (in_array($effectiveStatus, ['completed', 'answered', 'in-progress'])) {
            $finalStatus = 'answered';
        }

        $updates = [
            'call_status' => $finalStatus,
            'call_duration' => $duration,
            'ended_at' => now(),
        ];

        if (!empty($recordingUrl)) {
            $updates['recording_url'] = $recordingUrl;
        }

        if ($finalOutcome && (!$callLog || empty($callLog->call_outcome))) {
            $updates['call_outcome'] = $finalOutcome;
        }

        if ($callLog) {
            $callLog->update($updates);
        } else {
            CallLog::create(array_merge($updates, [
                'branch_id' => Branch::value('id') ?? 1,
                'call_sid' => $callSid,
                'phone' => $request->input('From', 'Unknown'),
                'started_at' => now()->subSeconds($duration),
            ]));
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Handle Twilio call recording ready callback.
     */
    public function recordingCallback(Request $request)
    {
        $callSid = $request->input('CallSid');
        $recordingUrl = $request->input('RecordingUrl');

        if (!empty($callSid) && !empty($recordingUrl)) {
            CallLog::where('call_sid', $callSid)->update([
                'recording_url' => $recordingUrl,
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Initiate an Outgoing Call / Callback from dashboard.
     */
    public function makeCall(Request $request, TwilioService $twilioService)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'branch_id' => 'nullable|exists:branches,id',
            'customer_name' => 'nullable|string|max:255',
            'call_log_id' => 'nullable|exists:call_logs,id',
        ]);

        $forwardTo = config('services.twilio.forward_to');
        if (empty($forwardTo)) {
            return response()->json([
                'success' => false,
                'message' => 'TWILIO_FORWARD_TO_NUMBER (your mobile or restaurant phone) is not configured in .env.',
            ], 422);
        }

        $statusCallbackUrl = url('/api/v1/twilio/status-callback');
        $twiml = $twilioService->generateForwardTwiML($statusCallbackUrl, $validated['phone']);

        $result = $twilioService->makeCall($forwardTo, $twiml);

        if ($result['success']) {
            $branchId = $validated['branch_id'] ?? (Branch::value('id') ?? 1);

            $callLog = CallLog::create([
                'branch_id' => $branchId,
                'call_sid' => $result['call_sid'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'phone' => $validated['phone'],
                'call_type' => 'outgoing',
                'call_status' => 'answered',
                'call_duration' => 0,
                'call_outcome' => 'callback',
                'started_at' => now(),
                'notes' => 'Outgoing call initiated from dashboard.',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call initiated successfully. Your phone will ring first, then connect to the customer.',
                'call_log' => $callLog,
                'twilio_response' => $result,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Could not initiate call.',
            'error' => $result,
        ], 500);
    }
}
