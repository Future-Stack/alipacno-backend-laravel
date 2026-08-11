# Stripe Testing Testing Flow

###  On Windows, the easiest way to install the Stripe CLI is via package managers like winget or scoop. Once installed, you can authenticate with your Stripe account and start forwarding webhook events to your local Laravel app.

## Installation Options for Windows
1. Using Winget (Recommended)
```bash
winget install Stripe.StripeCLI
```

* Works on Windows 10/11 with Winget enabled.
* Automatically adds stripe to your PATH.

2. Using Scoop
```bash
scoop bucket add stripe https://github.com/stripe/scoop-stripe-cli.git
scoop install stripe

```
* Requires Scoop package manager.
* Good alternative if Winget isn’t available.

3. Using npm
```bash
npm install -g @stripe/cli
```
* Requires Node.js installed.
* Installs globally via npm.

## Authentication
After installation, log in to Stripe:
```bash
stripe login
```
* This opens a browser window to authenticate your account.
* You’ll get a pairing code to confirm.

## Testing Webhooks Locally
1. Start your Laravel server:
```bash
php artisan serve
```

2. Run Stripe listener:
```bash
stripe listen --forward-to http://localhost:8000/webhooks/stripe
```

3. Trigger test events:
```bash
stripe trigger payment_intent.succeeded
```

## Full Local Testing Flow

### You need 4 terminals running simultaneously:


### Terminal 1 — Laravel Server
```bashphp 
artisan serve
```

### Terminal 2 — Queue Worker
```bash
php artisan queue:work
```

### Terminal 3 — Booking Webhook Listener

```bash
stripe listen --forward-to http://localhost:8000/api/v1/booking/webhook-handle
Copy the whsec_ secret → update .env STRIPE_BOOKING_WEBHOOK_SECRET
```

### Terminal 4 — Cancel Webhook Listener
```bash
stripe listen --forward-to http://localhost:8000/api/v1/booking/cancel-webhook-handle
Copy the whsec_ secret → update .env STRIPE_CANCEL_WEBHOOK_SECRET
Then:
bashphp artisan config:clear
```

## Step by Step Test Flow
## Step 1 — Create Booking
```
POST /api/v1/booking/store
From response grab:
json"stripe": {
"id": "pi_xxxxxxxx"   ← copy this
}
```

## Step 2 — Confirm Payment (simulates frontend)
```bash
stripe payment_intents confirm pi_xxxxxxxx --payment-method pm_card_visa --return-url https://localhost
```
## Watch Terminal 3 — should show:
```
--> payment_intent.succeeded
<-- [200] POST http://localhost:8000/api/v1/booking/webhook-handle
Watch Terminal 2 — should show:
App\Notifications\AdminIconNotification ... DONE
✅ Check DB — inspection_payments.status should be paid
```

## Step 3 — Cancel Booking
```
POST /api/v1/booking/cancel
{
"booking_id": 1
}
```

## Watch Terminal 4 — should show:
```
--> charge.refunded
<-- [200] POST http://localhost:8000/api/v1/booking/cancel-webhook-handle
```

## Watch Terminal 2 — should show:
```
App\Notifications\AdminIconNotification ... DONE
✅ Check DB:

inspection_payments — new refund row with status = completed
inspection_bookings.status — should be cancelled


Quick DB Check Commands
bash# Check payment status
php artisan tinker
>>> InspectionPayment::latest()->get(['id','status','payment_type','stripe_id']);

# Check booking status
>>> InspectionBooking::latest()->get(['id','status']);
That's the complete local flow identical to live.
```
