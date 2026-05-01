<?php

// Brevo (formerly Sendinblue) credentials for the standalone SMS cron scripts.
// The Laravel app reads these values from .env / config/services.php instead.

$brevo_api_key = '';
$brevo_sms_sender = 'Coinwink';
$brevo_sms_endpoint = 'https://api.brevo.com/v3/transactionalSMS/sms';
