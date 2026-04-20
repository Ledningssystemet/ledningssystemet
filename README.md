# Ledningssystemet.se

Detta projekt innehåller plattformen Ledningssystemet.se, ett stödsystem för ett modernt ledningssystem: ett generellt, modulärt och flexibelt verktyg för att bygga, dokumentera och förvalta verksamheters systematik för ständig förbättring, i enlighet med standardkrav och lagkrav.

Syftet är att ge organisationer en robust grund för systematiskt förbättringsarbete – utan låsningar mot specifika standarder eller proprietära mallar.

## 🎯 Vad systemet är

Kärnan tillhandahåller ett digitalt ramverk där organisationer bland annat kan:

- Strukturera **processer**, roller och arbetssätt
- Hantera **avvikelser**, förbättringar och korrigerande åtgärder
- Dokumentera och versionera **styrande och stödjande dokument**
- Sätta och följa upp **mål**, aktiviteter och nyckeltal
- Genomföra och dokumentera **uppföljningar**, som ledningens genomgång
- Skapa **spårbarhet och revisionsbarhet** i hela systemet
- Hantera **intressenter och leverantörer** strukturerat
- Sköta personaladministration avseende **kompetenser och kvalifikationer**
- Och mycket mer...

Systemet är standardoberoende men stödjer effektivt arbete enligt moderna ISO‑baserade ledningssystem och lagkrav som kräver systematik och uppföljning.

## 🧰 Kärnfunktionalitet (Open Source)

### Process- och strukturstöd
- Processkartläggning med automatisk tolkning och skapande av tillgångsförteckning, personuppgiftsbehandlingsregister och informationshanteringsplan
- Klassificering av information och informationstillgångar

### Avvikelse- och förbättringshantering
- Registrering av avvikelser och förbättringsförslag
- Stöd för rotorsaksanalys
- Spårbar kedja från upptäckt till åtgärd

### Mål och uppföljning
- Definiera mål och indikatorer
- Följa upp utveckling över tid

### Dokumenthantering
- Uppladdning, godkännande, versionering
- Rollbaserad åtkomst

### Strukturerade uppföljningar
- Stöd för exempelvis intern granskning och ledningens genomgång

### Leverantörs- och intressenthantering
- Enkel registrering och uppföljning

## 🌍 Varför släpps systemet fritt och öppet?

Ledningssystemet Sverige AB väljer att släppa programvaran helt öppen därför att **verksamheter bör lägga sina resurser på sin kärnverksamhet**, inte på onödigt komplexa eller dyra verktyg för förbättringsarbete.

Det finns en överhängande risk att många organisationer lägger stora resurser på att utveckla eller köpa lösningar som redan finns, vilket leder till dubbelarbete och förslösade medel. Detta har i öppna forum uttryckts som att många riskerar att "uppfinna hjulet på nytt", vilket bör motverkas genom att dela verktyg öppet.

Filosofin bakom detta är att verksamheter ska kunna arbeta systematiskt utan att tvingas in i dyra eller byråkratiska lösningar. Ett enkelt och kostnadseffektivt angreppssätt bör underlätta efterlevnad, inte belasta ekonomin.

Det är också offentligt framfört att vissa verksamheter – särskilt de som finansieras av skattemedel – bör kunna använda sådana verktyg utan licenskostnader under eget driftansvar, för att undvika onödiga utgifter och samtidigt stärka samhällsviktig verksamhet.

Genom att öppna källkoden möjliggör vi:

- **Fri anpassning** efter lokala behov
- **Gemensam vidareutveckling**, där förbättringar kommer alla till del
- **Transparens** som skapar förtroende
- **Fokus på verkligt värdeskapande**: analys, styrning och förbättringsarbete

**Kort sagt:**
Vi bygger detta öppet därför att det är bättre för samhället att vi tillsammans utvecklar ett bra, fritt och spårbart verktyg — och låter organisationer lägga sina resurser på sitt riktiga uppdrag.

## 🗂️ Sammanfattning

Den öppna kärnan av ledningssystemet är en flexibel och kostnadsfri plattform för att:

- bygga ett modernt och robust ledningssystem
- uppfylla standardkrav och lagkrav
- minska administration och kostnader
- skapa transparens och spårbarhet
- möjliggöra samarbete och gemensam utveckling

Systemet är skapat för att stödja verksamheter i att fokusera på det som verkligen spelar roll — **kvalitet, säkerhet, samhällsnytta och ständig förbättring**.

## 🧪 E2E-tester med Playwright

Projektet är konfigurerat för Playwright med tester i `tests/e2e`.
Testmiljön delas med PHPUnit via `.env.testing` (separat testdatabas).
Innan varje e2e-körning körs `php artisan --env=testing migrate:fresh --seed --force` automatiskt.

Installera beroenden och browser:

```bash
npm install
npm run e2e:install
```

Kör tester:

```bash
npm run e2e:prepare
npm run e2e
```

Användbara varianter:

```bash
npm run e2e:ui
npm run e2e:headed
```

Login-testet använder seedad standardanvändare:

```bash
email: test@example.com
password: password
```

Du kan fortfarande skriva över med miljövariabler vid behov:

```bash
E2E_USER_EMAIL=annan@example.com
E2E_USER_PASSWORD=hemligt
npm run e2e
```

Standardkonfigurationen startar automatiskt Laravel-servern (`php artisan serve`) vid testkörning.

## ✅ Testmiljö för PHPUnit + E2E

`phpunit.xml` sätter `APP_ENV=testing`, vilket gör att Laravel läser `.env.testing`.
Playwright startar också appen med `--env=testing`, så både `php artisan test` och `npm run e2e` använder samma testkonfiguration och testdatabas.

