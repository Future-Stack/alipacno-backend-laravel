<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Driver KYC Status Update</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #eeeeee;
        }
        .header h1 {
            color: #111827;
            font-size: 24px;
            margin: 0;
        }
        .content {
            padding: 28px 0;
            color: #374151;
            font-size: 15px;
            line-height: 1.6;
        }
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
        }
        .badge-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .reason-box {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            border-radius: 6px;
            padding: 16px 20px;
            margin: 20px 0;
        }
        .reason-title {
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .reason-text {
            color: #7f1d1d;
            font-size: 15px;
            white-space: pre-line;
        }
        .info-card {
            background: #f9fafb;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 18px 0;
            border: 1px solid #e5e7eb;
        }
        .info-card p {
            margin: 6px 0;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 15px;
        }
        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
            border-top: 1px solid #eeeeee;
            padding-top: 20px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Pacino Delivery Network</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $driverName }}</strong>,</p>

            @if($status === 'approved')
                <div>
                    <span class="badge badge-approved">KYC Approved</span>
                </div>
                <p>We are excited to let you know that your Driver KYC verification has been <strong>approved</strong>!</p>
                <p>Your driver account is now fully active. You can go online in the application and start accepting delivery orders right away.</p>
            @elseif($status === 'rejected')
                <div>
                    <span class="badge badge-rejected">KYC Rejected</span>
                </div>
                <p>We regret to inform you that your Driver KYC verification was <strong>not approved</strong> at this time.</p>
                
                @if($rejectReason)
                    <div class="reason-box">
                        <div class="reason-title">Reason for Rejection:</div>
                        <div class="reason-text">{{ $rejectReason }}</div>
                    </div>
                @endif

                <p>Please log in to your account, update your driver details / KYC documents with the correct information as requested, and resubmit for verification.</p>
            @else
                <p>Your Driver KYC verification status has been updated to: <strong>{{ ucfirst($status) }}</strong>.</p>
            @endif

            <div class="info-card">
                <p><strong>Vehicle:</strong> {{ $driver->vehicle_type ?? 'N/A' }}</p>
                <p><strong>License Number:</strong> {{ $driver->license_number ?? 'N/A' }}</p>
                <p><strong>Phone:</strong> {{ $driver->phone ?? 'N/A' }}</p>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Alipacno. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
