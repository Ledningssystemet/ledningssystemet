# Ledningssystemet.se

Detta projekt innehåller plattformen Ledningssystemet.se, ett stödsystem för ett modernt ledningssystem: ett generellt, modulärt och flexibelt verktyg för att bygga, dokumentera och förvalta verksamheters systematik för ständig förbättring, i enlighet med standardkrav och lagkrav.

## Kör med Docker Compose

Projektet innehåller en grundfil för Compose i `compose.yaml`, en utvecklingsöverskrivning i `compose.override.yaml` och en produktionsöverskrivning i `compose.prod.yaml`.

### Utveckling

`compose.override.yaml` läses automatiskt av Docker Compose.

```bash
docker compose up --build
```

Appen blir då tillgänglig på `http://localhost:8080` och Vite på `http://localhost:5173`.

### Produktion

```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
```

Anpassa gärna `.env` eller exportera egna miljövariabler innan du kör produktionsprofilen.

## 🎯 Vad är Ledningssystemet.se?

## 🌍 Varför släpps systemet fritt och öppet?

Ledningssystemet Sverige AB väljer att släppa programvaran helt öppen därför att **verksamheter bör lägga sina resurser på sin kärnverksamhet**, inte på onödigt komplexa eller dyra verktyg för förbättringsarbete.

Genom att öppna källkoden möjliggör vi:

- **Fri anpassning** efter lokala behov
- **Gemensam vidareutveckling**, där förbättringar kommer alla till del
- **Transparens** som skapar förtroende

**Kort sagt:**
Vi bygger detta öppet därför att det är bättre för samhället att vi tillsammans utvecklar ett bra, fritt och spårbart verktyg — och låter organisationer lägga sina resurser på sitt riktiga uppdrag.

## Vad innebär licensmodellen AGPLv3 som gäller för Ledningssystemet.se?

📜 Licens – GNU Affero General Public License v3 (AGPLv3)

Detta projekt är licensierat under AGPLv3, en stark copyleft‑licens för öppen källkod.
Vad innebär det för dig?

✅ Du får

    Använda programvaran fritt, både privat och kommersiellt
    Studera, modifiera och förbättra koden
    Distribuera originalet eller egna varianter

⚠️ Du måste

    Licensiera alla ändringar och vidareutvecklingar under AGPLv3
    Tillhandahålla fullständig källkod till användare som interagerar med mjukvaran
    Inkludera licenstext och upphovsrättsnotiser

🌐 Viktigt för moln- och webbtjänster (SaaS)

    Om du kör denna mjukvara som en webbtjänst (även utan att distribuera den som program)
    → måste du göra källkoden tillgänglig för tjänstens användare, inklusive dina ändringar.

🚫 Vad du inte får göra

    Ta koden, modifiera den och erbjuda den som en sluten eller proprietär tjänst
    Bygga vidare på koden och sälja den utan att samtidigt dela källkoden på samma villkor

Sammanfattning

AGPLv3 säkerställer att förbättringar förblir öppna även i moln- och SaaS‑miljöer.
Om du bygger vidare på denna kod ska även dina användare få samma friheter som du fått.

Se filen LICENSE för fullständig licenstext.
