<?php

return [
	'login' => [
		'title' => 'Sign in',
		'workplace_account' => 'Sign in with your workplace account',
		'email' => 'Email',
		'password' => 'Password',
		'remember_me' => 'Remember me',
		'use_otp' => 'Use OTP (MFA) for this sign in',
		'mfa_required' => 'MFA is required. An OTP code will be sent after password sign in.',
		'submit' => 'Sign in',
		'forgot_password' => 'Forgot password?',
	],

	'forgot_password' => [
		'title' => 'Reset password',
		'heading' => 'Reset password',
		'send_link' => 'Send reset link',
		'back_to_login' => 'Back to sign in',
	],

	'reset_password' => [
		'title' => 'New password',
		'heading' => 'Choose a new password',
		'new_password' => 'New password',
		'confirm_password' => 'Confirm password',
		'save' => 'Save new password',
	],

	'otp' => [
		'title' => 'Verify OTP',
		'heading' => 'Verify one-time code',
		'description' => 'Enter the six-digit code sent to your email. The code is valid for :minutes minutes.',
		'code' => 'OTP code',
		'verify' => 'Verify and sign in',
		'resend' => 'Send new code',
		'expired' => 'The one-time code has expired. Request a new code.',
		'invalid' => 'Invalid one-time code.',
		'resent' => 'A new one-time code has been sent.',
		'email_body' => 'Your one-time code is: :code. The code is valid for :minutes minutes.',
		'email_subject' => 'One-time code for sign in',
		'email_resend_body' => 'Your new one-time code is: :code. The code is valid for :minutes minutes.',
		'email_resend_subject' => 'New one-time code for sign in',
	],

	'errors' => [
		'invalid_credentials' => 'Invalid sign in credentials.',
		'login_failed' => 'Could not complete sign in.',
	],

	'oauth' => [
		'cancelled_or_denied' => 'OAuth login was cancelled or denied.',
		'login_success' => 'You are now logged in.',
		'invalid_user_id' => 'The OAuth provider did not return a valid user id.',
		'missing_email' => 'The OAuth provider did not return an email address.',
		'linked_to_other_provider' => 'This account is linked to another OAuth provider.',
		'linked_to_other_identity' => 'This account is already linked to another external identity.',
	],

	'session' => [
		'expired_title' => 'Your session has ended',
		'expired_message' => 'You appear to be signed out. Open sign in in a new tab and sign in there to avoid losing changes made in this tab.',
		'checking' => 'Checking...',
		'restored' => 'I have signed in',
		'open_login_tab' => 'Open sign in in a new tab',
	],
];

