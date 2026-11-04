# Instruktioner för kodningsagenter

Instruktionerna gäller hela repot. Läs även [CONTRIBUTING.md](CONTRIBUTING.md)
och relevant dokumentation i [docs](docs) innan du ändrar ett område.

## Vad systemet är till för

Ledningssystemet är ett öppet ledningssystem för att dokumentera, organisera och
förbättra verksamhetens processer och kvalitetsarbete. Det omfattar bland annat
process- och dokumenthantering, projekt och aktiviteter, risker, avvikelser,
leverantörer, GDPR-behandlingsregister, behörigheter och aktivitetsloggning.
Systemet stödjer arbete med exempelvis ISO-standarder, GDPR och NIS2, men
användning av programvaran innebär inte i sig att en verksamhet uppfyller kraven.

Systemet är både en körbar Laravel-applikation och en återanvändbar bas för andra
applikationer. Backend distribueras som Composer-paketet
`ledningssystemet/ledningssystemet` och frontend som npm-paketet
`@ledningssystemet/ledningssystemet`. En ändring är inte färdig bara för att den
fungerar när detta repo körs fristående: den måste också vara användbar i
applikationer som installerar paketen som beroenden.

## Hitta rätt och följ befintliga mönster

- `app/`: modeller, controllers, policies, jobb och Artisan-kommandon.
- `src/LedningssystemetServiceProvider.php`: paketets ingång via Composers
  auto-discovery; registrerar paketets resurser och övriga providers.
- `src/Providers/RouteServiceProvider.php` och `routes/`: webb- och API-routing.
- `database/migrations/`: databasändringar som även ska laddas i värdapplikationer.
- `resources/views/`: Blade-vyer; `resources/js/`: frontend och React-komponenter.
- `config/` och `lang/`: konfiguration och översättningar.
- `docs/architecture.md`, `docs/api.md` och `docs/ui.md`: arkitektur, API och
  gemensamma UI-komponenter.

Undersök befintliga anrop, behörighetskontroller och liknande implementationer
innan du skriver ny kod. Återanvänd gemensamma komponenter och hjälpfunktioner.
Gör fokuserade ändringar och bevara orelaterade lokala ändringar. Behåll
behörighetskontroller, validering, översättningar och felhantering genom hela
flödet, inte bara i gränssnittet. Uppdatera direkt berörd dokumentation.

## Utveckla för både paketet och värdapplikationen

- Använd paketets namespace `Ledningssystemet\Ledningssystemet` enligt
  `composer.json`. Förutsätt inte att värdapplikationen har samma klasser,
  katalogstruktur eller bootstrap som detta repo.
- Exportera och koppla in ny backendfunktionalitet via
  `src/LedningssystemetServiceProvider.php`, direkt eller genom providers som den
  registrerar. En ändring enbart i lokal bootstrap eller lokal konfiguration
  räcker inte för Composer-konsumenter.
- Registrera nya Artisan-kommandon med `$this->commands(...)` i serviceprovidern.
  Ska ett kommando köras schemalagt måste även schemaläggningen registreras där,
  enligt befintligt mönster med `Schedule` efter att applikationen har bootat.
  Förlita dig inte på att värdapplikationen kör paketets `routes/console.php`
  eller en lokal Console Kernel. Undvik dubbelregistrering av schemalagda jobb.
- Kontrollera att nya routes, migrations, vyer, översättningar, konfiguration,
  events och andra resurser laddas även när paketet installeras via Composer.
  Bevara värdapplikationens möjlighet att överstyra konfiguration, vyer och
  översättningar. Registrera routes via befintlig route-provider, inte dubbelt.
- Bevara frontendpaketets publika ingångar i `package.json`: `./app`,
  `./loadstyles` och `./components/*`. Gör nya återanvändbara komponenter
  tillgängliga genom rätt exporter och, där det behövs, befintlig
  komponentregistrering. En lokal Vite-build är inte ensam bevis för att
  importer fungerar från npm-paketet i en värdapplikation.
- Skilj på paketets egna resurssökvägar och värdapplikationens sökvägar. Följ
  befintligt mönster för publicering av publika resurser. Hårdkoda inte
  installationssökvägar, kundspecifika värden eller hemligheter.
- Beakta bakåtkompatibilitet för API:er, komponenter, konfiguration och
  databasschema. Gör inga oavsiktliga brytande ändringar för konsumenterna.

## Testning och verifiering

Testfall får skapas och köras lokalt i `tests/`, men **testfallen ska inte
Git-hanteras eller ingå i commits eller pull requests i detta repo**. Katalogens
innehåll är ignorerat i `.gitignore`; behåll det så och använd inte `git add -f`
för att lägga till tester.

Den gemensamma regressionstestningen genomförs i ett separat projekt som
använder detta system som bas via både Composer och npm. Lägg därför inte till
en versionshanterad regressionssvit här. Beskriv ändrat beteende och relevanta
testscenarier så att de kan täckas i det separata projektet.

Verifiera ändrade flöden i en isolerad utvecklingsmiljö och välj relevanta
kontroller enligt `docs/development.md` och befintliga verktyg. För
paketkopplingar behöver även konsumtion från en värdapplikation verifieras,
exempelvis med `php artisan list` och `php artisan schedule:list` i
värdapplikationen för nya kommandon och scheman. Kör inte schemalagda jobb,
migreringar eller andra skrivande kontroller mot produktionsdata.

Redovisa vad som ändrats, vilka kontroller som faktiskt körts och vad som
återstår att verifiera i det separata regressionsprojektet. Påstå inte att
regressionstesterna är godkända om du inte har kört dem.
