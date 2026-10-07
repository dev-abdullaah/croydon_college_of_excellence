<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Approved - Croydon College of Excellence</title>
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
            background-color: #50cd89;
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
            color: #ffffff;
            font-size: 14px;
            margin: 0;
            font-weight: 500;
            opacity: 0.95;
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
        .success-box {
            background-color: #e8fff3;
            border-left: 4px solid #50cd89;
            padding: 16px 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .success-box p {
            margin: 6px 0;
            font-size: 14px;
        }
        .success-box strong {
            color: #1e1e2d;
        }
        .badge {
            display: inline-block;
            background-color: #50cd89;
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .btn-wrapper {
            text-align: center;
            margin: 30px 0;
        }
        .btn-primary {
            display: inline-block;
            background-color: #00acff;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 30px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: 0.5px;
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
            <p>Course Admission Approved &bull; Access Activated</p>
        </div>
        <div class="content">
            <h2>Congratulations, {{ $student->name }}!</h2>
            <p>Your admission application has been approved by the college administrator. Your payment has been verified, and your course access is now fully active.</p>

            <div class="success-box">
                <p><strong>Enrolled Course:</strong> {{ $course->name }}</p>
                <p><strong>Admission Status:</strong> <span class="badge">Admitted & Active</span></p>
                <p><strong>Access Duration:</strong> Lifetime Access</p>
                <p><strong>Admitted On:</strong> {{ now()->format('d M Y') }}</p>
            </div>

            <p>You can now immediately access all course study modules, reading cards, and examination practice mock test papers directly from your student dashboard.</p>

            <div class="btn-wrapper">
                <a href="{{ route('dashboard') }}" class="btn-primary">Go to Student Dashboard & Start Learning</a>
            </div>

            <p style="margin-top: 25px;">We wish you every success in your studies.<br><br>
            Kind regards,<br>
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
