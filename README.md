# ledningssystemet
Ledningssystemet.se

## Authentication

Applikationen har nu ett enhetligt inloggningsflode med:

- Losenordsinloggning
- MFA via OTP (valfritt eller tvingat)
- OAuth via **ett** arbetsplatskonto (Google **eller** Microsoft Entra)

### Login-lagen

Styrs av `AUTH_LOGIN_MODE`:

- `hybrid`: losenord + eventuell OAuth-knapp
- `password`: endast losenord
- `oauth`: endast OAuth (inloggningssidan omdirigerar direkt till OAuth)

### OAuth (generisk konfiguration)

Konfigurera i `.env`:

```dotenv
AUTH_OAUTH_ENABLED=true
AUTH_OAUTH_PROVIDER=google
AUTH_OAUTH_CLIENT_ID=
AUTH_OAUTH_CLIENT_SECRET=
AUTH_OAUTH_REDIRECT_URI="${APP_URL}/oauth/workplace/callback"
AUTH_OAUTH_TENANT_ID=common
AUTH_OAUTH_WORKSPACE_DOMAIN=
```

- `AUTH_OAUTH_PROVIDER=google` for Google Workspace
- `AUTH_OAUTH_PROVIDER=microsoft` for Microsoft Entra

OAuth-knappen visas bara nar OAuth ar korrekt konfigurerat.

OAuth-routes:

- `/oauth/workplace/redirect`
- `/oauth/workplace/callback`

### MFA via OTP

Konfigurera i `.env`:

```dotenv
AUTH_MFA_ENABLED=false
AUTH_MFA_ENFORCE=false
AUTH_MFA_OTP_TTL=10
```

- Nar `AUTH_MFA_ENABLED=true` kan anvandaren valja OTP vid inloggning.
- Nar `AUTH_MFA_ENFORCE=true` maste OTP alltid verifieras.

### Auth-sidor

Skapade under `resources/views/auth/`:

- `login.blade.php`
- `forgot-password.blade.php`
- `reset-password.blade.php`
- `otp-challenge.blade.php`

Gaster omdirigeras till `/login` om de forsoker na skyddade routes.
