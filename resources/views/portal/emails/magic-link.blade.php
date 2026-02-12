<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login to GYPSYLIVE Portal</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f5f7fa;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 15px 15px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">
                                <span style="font-size: 32px;">💎</span> GYPSYLIVE
                            </h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 14px;">Creator Redeem Portal</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #333; margin: 0 0 20px 0; font-size: 22px;">Hi {{ $user->fullname }},</h2>

                            <p style="color: #666; line-height: 1.6; margin: 0 0 25px 0;">
                                You requested a login link for the GYPSYLIVE Redeem Portal. Click the button below to securely access your account:
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="text-align: center; padding: 20px 0;">
                                        <a href="{{ $link }}" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 15px 40px; border-radius: 30px; font-weight: 600; font-size: 16px;">
                                            🔐 Login to Portal
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #999; font-size: 13px; line-height: 1.6; margin: 25px 0 0 0;">
                                ⏰ This link will expire in <strong>30 minutes</strong> for security reasons.
                            </p>

                            <p style="color: #999; font-size: 13px; line-height: 1.6; margin: 15px 0 0 0;">
                                If you didn't request this login link, you can safely ignore this email.
                            </p>

                            <!-- Divider -->
                            <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

                            <p style="color: #999; font-size: 12px; line-height: 1.6; margin: 0;">
                                If the button doesn't work, copy and paste this link into your browser:<br>
                                <a href="{{ $link }}" style="color: #667eea; word-break: break-all;">{{ $link }}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-radius: 0 0 15px 15px;">
                            <p style="color: #999; font-size: 12px; margin: 0;">
                                © {{ date('Y') }} GYPSYLIVE. All rights reserved.<br>
                                This is an automated message, please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
