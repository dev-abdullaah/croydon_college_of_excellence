<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Message</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
        }

        .header {
            background-color: #00acff;
            color: #ffffff;
            padding: 15px;
            text-align: center;
            border-radius: 7px;
            font-size: 18px;
        }

        .content {
            padding: 20px;
        }

        .content p {
            font-size: 16px;
            line-height: 1.6;
            margin: 10px 0;
            word-wrap: break-word;
        }

        .content strong {
            color: #00acff;
        }

        .footer {
            text-align: center;
            font-size: 14px;
            color: #888888;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            margin-top: 15px;
        }

        /* Responsive Styles */
        @media screen and (max-width: 768px) {
            .container {
                width: 98%;
                padding: 15px;
                border-radius: 0;
                box-shadow: none;
            }

            .header {
                padding: 12px;
                font-size: 16px;
            }

            .content p {
                font-size: 14px;
            }

            .footer {
                font-size: 11px;
            }
        }

        @media screen and (max-width: 480px) {
            .container {
                width: 100%;
                padding: 10px;
            }

            .header {
                padding: 10px;
                font-size: 14px;
            }

            .content {
                padding: 15px;
            }

            .content p {
                font-size: 13px;
            }

            .footer {
                font-size: 10px;
                padding-top: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Contact Form Message</h2>
        </div>
        <div class="content">
            <p><strong>Name:</strong> {{ $data['name'] }}</p>
            <p><strong>Email:</strong> {{ $data['email'] }}</p>
            <p><strong>Phone:</strong> {{ $data['phone'] }}</p>
            <p><strong>Subject:</strong> {{ $data['subject'] }}</p>
            <p><strong>Message:</strong></p>
            <p>{{ $data['message'] }}</p>
        </div>
        <div class="footer">
            <p>This message was sent from the Contact Us Form on your website.</p>
        </div>
    </div>
</body>
</html>
