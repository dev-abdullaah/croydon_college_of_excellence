<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Application Received - Croydon College of Excellence</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
            color: #333333;
        }
        .container {
            width: 100%;
            max-width: 620px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }
        .header {
            background-color: #1e1e2d;
            padding: 30px 25px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 22px;
            margin: 0 0 8px 0;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            color: #00acff;
            font-size: 14px;
            margin: 0;
            font-weight: 500;
        }
        .content {
            padding: 35px 30px;
            line-height: 1.6;
        }
        .content h2 {
            font-size: 18px;
            color: #1e1e2d;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .highlight-box {
            background-color: #f8fafc;
            border-left: 4px solid #00acff;
            padding: 16px 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .highlight-box p {
            margin: 6px 0;
            font-size: 14px;
        }
        .highlight-box strong {
            color: #1e1e2d;
        }
        .badge {
            display: inline-block;
            background-color: #fff8dd;
            color: #f1416c;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .info-card {
            background-color: #e8fff3;
            border: 1px solid #c9f7f5;
            padding: 16px;
            border-radius: 6px;
            margin: 20px 0;
            font-size: 14px;
            color: #1e1e2d;
        }
        .footer {
            background-color: #f4f6f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #7e8299;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Croydon College of Excellence</h1>
            <p>Official Course Admission Application</p>
        </div>
        <div class="content">
            <h2>Dear {{ $student->name }},</h2>
            <p>Thank you for submitting your course admission application. We have received your request and your application is currently under review.</p>

            <div class="highlight-box">
                <p><strong>Course Applied:</strong> {{ $course->name }}</p>
                <p><strong>Fee:</strong> {{ $course->formattedPrice() }}</p>
                <p><strong>Application Status:</strong> <span class="badge">Awaiting Payment Verification</span></p>
                <p><strong>Contact Phone:</strong> {{ $admission->contact_phone }}</p>
                <p><strong>Payment Method Preference:</strong> {{ ucfirst(str_replace('_', ' ', $admission->payment_method)) }}</p>
            </div>

            <div class="info-card">
                <strong>What happens next?</strong><br>
                The college administrator will contact you shortly via phone or email to confirm your enrollment details and provide payment instructions directly.
            </div>

            <p>Please note that official course payment instructions are communicated exclusively by the administrator over the phone or official college email.</p>
            <p>Once your fee payment is verified, your course and mock test access will be activated immediately.</p>

            <p style="margin-top: 25px;">Kind regards,<br>
            <strong>Admissions Office</strong><br>
            Croydon College of Excellence</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Croydon College of Excellence. All rights reserved.<br>
            London, United Kingdom</p>
        </div>
    </div>
</body>
</html>
