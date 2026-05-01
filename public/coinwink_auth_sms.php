<?php

// Coinwink SMS gateway settings (Brevo).
//
// Twilio was removed in favour of Brevo's Transactional SMS API. The keys here
// are the per-environment numbers Coinwink rotates between for sender-ID
// purposes; the Brevo API key itself lives in coinwink_auth_brevo.php.

$from_nr = "Coinwink";
$from_nr_2 = "Coinwink";

// Last-4-digit suffixes of recipient numbers that should use $from_nr_2
// instead of $from_nr (legacy Coinwink behaviour, preserved as-is).
$to_nrs = [];
