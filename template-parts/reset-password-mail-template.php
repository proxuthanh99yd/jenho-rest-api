<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Reset Your Password'); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: none;
            -ms-text-size-adjust: none;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin: 0 auto;
            max-width: 600px;
            background-color: #fff;
            border: 1px solid #ddd;
        }

        td {
            padding: 20px;
            text-align: left;
        }

        .reset-btn {
            background-color: #0073aa;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
        }

        .reset-btn:hover {
            background-color: #005177;
        }
    </style>
</head>

<body>
    <table>
        <tr>
            <td>
                <h1><?php _e('Reset Your Password'); ?></h1>
                <p><?php _e('You have requested to reset your password. Click the button below to reset it:'); ?></p>
                <p>
                    <a href="<?php echo esc_url($reset_url); ?>" class="reset-btn"><?php _e('Reset Password'); ?></a>
                </p>
                <p><?php _e('If you did not request a password reset, please ignore this email.'); ?></p>
                <p><?php _e('Thank you!'); ?></p>
            </td>
        </tr>
    </table>
</body>

</html>