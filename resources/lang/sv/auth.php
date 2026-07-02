<?php

return [
	'login' => [
		'title' => 'Logga in',
		'workplace_account' => 'Logga in med ditt arbetskonto',
		'email' => 'E-post',
		'password' => 'Lösenord',
		'remember_me' => 'Kom ihåg mig',
		'use_otp' => 'Använd OTP (MFA) för denna inloggning',
		'mfa_required' => 'MFA krävs. En OTP-kod skickas efter inloggning med lösenord.',
		'submit' => 'Logga in',
		'forgot_password' => 'Glömt lösenord?',
	],

	'forgot_password' => [
		'title' => 'Återställ lösenord',
		'heading' => 'Återställ lösenord',
		'send_link' => 'Skicka återställningslänk',
		'back_to_login' => 'Tillbaka till inloggning',
	],

	'reset_password' => [
		'title' => 'Nytt lösenord',
		'heading' => 'Välj ett nytt lösenord',
		'new_password' => 'Nytt lösenord',
		'confirm_password' => 'Bekräfta lösenord',
		'save' => 'Spara nytt lösenord',
	],

	'otp' => [
		'title' => 'Verifiera OTP',
		'heading' => 'Verifiera engångskod',
		'description' => 'Ange den sexsiffriga kod som skickades till din e-post. Koden är giltig i :minutes minuter.',
		'code' => 'OTP-kod',
		'verify' => 'Verifiera och logga in',
		'resend' => 'Skicka ny kod',
		'expired' => 'Engångskoden har gått ut. Begär en ny kod.',
		'invalid' => 'Ogiltig engångskod.',
		'resent' => 'En ny engångskod har skickats.',
		'email_body' => 'Din engångskod är: :code. Koden är giltig i :minutes minuter.',
		'email_subject' => 'Engångskod för inloggning',
		'email_resend_body' => 'Din nya engångskod är: :code. Koden är giltig i :minutes minuter.',
		'email_resend_subject' => 'Ny engångskod för inloggning',
	],

	'errors' => [
		'invalid_credentials' => 'Ogiltiga inloggningsuppgifter.',
		'login_failed' => 'Kunde inte slutföra inloggningen.',
	],

	'oauth' => [
		'cancelled_or_denied' => 'OAuth-inloggning avbröts eller nekades.',
		'login_success' => 'Du är nu inloggad.',
		'invalid_user_id' => 'OAuth-leverantören returnerade inget giltigt användar-id.',
		'missing_email' => 'OAuth-leverantören returnerade ingen e-postadress.',
		'linked_to_other_provider' => 'Detta konto är kopplat till en annan OAuth-leverantör.',
		'linked_to_other_identity' => 'Detta konto är redan kopplat till en annan extern identitet.',
	],

	'session' => [
		'expired_title' => 'Din session har avslutats',
		'expired_message' => 'Det verkar som att du har loggats ut. Öppna inloggningen i en ny flik och logga in där för att undvika att förlora ändringar i denna flik.',
		'checking' => 'Kontrollerar...',
		'restored' => 'Jag har loggat in',
		'open_login_tab' => 'Öppna inloggning i en ny flik',
	],
];

