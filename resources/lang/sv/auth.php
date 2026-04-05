<?php

return [
	'login' => [
		'title' => 'Logga in',
		'workplace_account' => 'Logga in med ditt arbetsplatskonto',
		'email' => 'E-post',
		'password' => 'Losenord',
		'remember_me' => 'Kom ihag mig',
		'use_otp' => 'Anvand OTP (MFA) for denna inloggning',
		'mfa_required' => 'MFA ar obligatoriskt. En OTP-kod skickas efter losenordsinloggning.',
		'submit' => 'Logga in',
		'forgot_password' => 'Glomt losenord?',
	],

	'forgot_password' => [
		'title' => 'Losenordsaterstallning',
		'heading' => 'Aterstall losenord',
		'send_link' => 'Skicka aterstallningslank',
		'back_to_login' => 'Tillbaka till inloggning',
	],

	'reset_password' => [
		'title' => 'Nytt losenord',
		'heading' => 'Valj nytt losenord',
		'new_password' => 'Nytt losenord',
		'confirm_password' => 'Bekrafta losenord',
		'save' => 'Spara nytt losenord',
	],

	'otp' => [
		'title' => 'Verifiera OTP',
		'heading' => 'Verifiera engangskod',
		'description' => 'Ange den sexsiffriga kod som skickats till din e-post. Koden galler i :minutes minuter.',
		'code' => 'OTP-kod',
		'verify' => 'Verifiera och logga in',
		'resend' => 'Skicka ny kod',
		'expired' => 'Engangskoden har gatt ut. Begar en ny kod.',
		'invalid' => 'Ogiltig engangskod.',
		'resent' => 'En ny engangskod har skickats.',
		'email_body' => 'Din engangskod ar: :code. Koden ar giltig i :minutes minuter.',
		'email_subject' => 'Engangskod for inloggning',
		'email_resend_body' => 'Din nya engangskod ar: :code. Koden ar giltig i :minutes minuter.',
		'email_resend_subject' => 'Ny engangskod for inloggning',
	],

	'errors' => [
		'invalid_credentials' => 'Felaktiga inloggningsuppgifter.',
		'login_failed' => 'Kunde inte slutfora inloggningen.',
	],

	'session' => [
		'expired_title' => 'Din session har avslutats',
		'expired_message' => 'Du verkar vara utloggad. Oppna inloggningen i en ny flik och logga in dar for att undvika att forlora andringar du har gjort i den har fliken.',
		'checking' => 'Kontrollerar...',
		'restored' => 'Jag har loggat in',
		'open_login_tab' => 'Oppna inloggning i ny flik',
	],
];

