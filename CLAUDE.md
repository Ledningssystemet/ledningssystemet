# AI Instruktioner för CRUD Table Module

## Syfte
Den här filen innehåller instruktioner för AI-agenter som arbetar med CRUD Table-modulen.
Den säkerställer konsistent kodstandard, struktur och dokumentation.

## ✅ Regler för Dokumentation

1. **ALLA dokumentationsfiler** ska lagras i `doc/` katalogen
   - Aldrig i projektroten (root)
   - Undantag: README.md om det är projektets huvudmeny

2. **Dokumentationsnamn:**
   - Använd UPPERCASE med underscores: `FILE_NAME.md`
   - Vara deskriptiv: `INTEGRATION_GUIDE.md` inte `guide.md`
   - Svenska filnamn är OK: `INSTALLATION_GUIDE.md`

3. **Innehåll i dokumentation:**
   - Använd tydliga rubriker (#, ##, ###)
   - Inkludera praktiska kodexempel
   - Länka till relaterad dokumentation
   - Dokumentera snabbstart tidigt

## Regler för testfall
1. **Skapa testfall** för nya funktioner och buggar innan buggar fixas
2. **Testa** funktioner och buggar i en ny fil i `tests/` katalogen

## 🚫 Vad AI INTE Ska Göra

1. **SKAPA INTE exempelfiler** såsom:
   - Demo-filer eller test-filer utan explicit instruktion

2. **SKAPA INTE dokumentation i root:**
   - Alla .md filer ska gå i `doc/` katalogen
   - Lägg INTE filer direkt i `/`

3. **UNDVIK redundans:**
   - Uppdatera befintlig dokumentation istället för att skapa ny
   - Länka mellan dokument istället för att upprepa information
   - Kontrollera vad som redan existerar innan du skapar nytt

4. ** Alla strängar skall översättas:**
   - Alla texter skall använda översättningsfunktionen och skall finnas på engelska under resources/lang/
   - Strängar som inte behöver pluraliseringsinställningar behöver inte läggas in som referens utan kan läggas in direkt men ska ändå använda översättningsfunktionen

## ✅ Vad AI Ska Göra

1. **Skapa komponenter i rätt plats:**
   ```
   resources/js/Components/crud/
   resources/js/hooks/
   resources/js/types/
   ```

2. **Uppdatera befintlig dokumentation:**
   - Lägg till i `doc/CRUD_TABLE_DOCUMENTATION.md`
   - Uppdatera `doc/QUICK_REFERENCE.md` med ny syntax
   - Lägg till exempel i `doc/INTEGRATION_GUIDE.md`

3. **Länka mellan dokumentation:**
   - Se `doc/QUICK_REFERENCE.md` för snabbstart
   - Se `doc/INTEGRATION_GUIDE.md` för detaljerade steg
   - Se `doc/CRUD_TABLE_DOCUMENTATION.md` för full referens

4. **Använd samma kodstil:**
   - React: Functional components med TypeScript
   - Tailwind CSS för styling
   - Lucide React för ikoner
   - Axios för API-anrop


## 📝 Dokumentationsstruktur

Varje dokumentationsfile ska ha:

```markdown
# Titel

Kort introduktion (2-3 rader)

## Innehållsförteckning (om >500 rader)

## Avsnitt 1
...

## Avsnitt 2
...

## Troubleshooting
...
```

## 🔍 Befintlig Dokumentation

### `doc/QUICK_REFERENCE.md`
- Snabb referens-kort
- Använd för enkla exempel
- Max 300 rader

### `doc/CRUD_TABLE_README.md`
- Snabbstart (5 min)
- Features overview
- Basisk konfiguration

### `doc/INTEGRATION_GUIDE.md`
- Steg-för-steg integration
- Code examples (PHP + TSX)
- Troubleshooting

### `doc/CRUD_TABLE_DOCUMENTATION.md`
- Fullständig API-referens
- Alla interfaces
- Advanced usage
- Best practices

### `doc/ROUTING_AND_INERTIA_EXAMPLES.md`
- Inertia.js integration
- Routing examples
- Laravel setup

### `doc/IMPLEMENTATION_SUMMARY.md`
- Overview av implementering
- Features checklist
- Nästa steg

### `doc/FILE_OVERVIEW.md`
- Filstruktur
- Statistik
- Quick reference

## 🛠️ Kodstandard

### React Components
```tsx
// ✅ Korrekt
import React from 'react';

interface Props {
  config: CrudTableConfig;
}

export function ComponentName({ config }: Props) {
  // kod
}
```

### TypeScript
```ts
// ✅ Korrekt - explicittyper
export interface MyInterface {
  field: string;
  value: number;
}

// ❌ Undvik
export interface MyInterface {
  field: any;
}
```

### Styling
```tsx
// ✅ Korrekt - Tailwind classes
className="px-4 py-2 bg-blue-600 rounded-lg"

// ❌ Undvik
style={{ padding: '16px 8px', backgroundColor: 'blue' }}
```

## 📋 Checklista för AI

Innan du skapar något:

- [ ] Existerar detta redan? (sök i `resources/js/`)
- [ ] Var ska filen placeras? (komponenter → `Components/`, docs → `doc/`)
- [ ] Är det en dokumentationsfil? (ska det gå i `doc/`?)
- [ ] Följer det kodstilen? (TypeScript, Tailwind, etc)
- [ ] Finns relevant dokumentation redan? (länka istället för att upprepa)

## Information om BPMN-formatet i våra processer
Vi använder endast ett subset av de olika komponenter som finns i BPMN i våra processer.
Dessutom har de olika semantisk betydelse i vårt system än i det generella BPMN-formatet.
Vi har dessutom lite regler som inte finns i BPMN.
Vi exekverar inte heller BPMN utan använder det bara för att kunna visualisera våra processkartor och för att kunna extrahera information från processerna som används i systemet.
När vi tolkar information i en processkarta så använder vi namnet på komponenterna för att kunna extrahera information.

De komponenter vi använder är:
- startEvent: Denna används bara som visuell markör för var en process börjar. Den har ingen semantisk betydelse i vårt system.
- endEvent: Denna används bara som visuell markör för var en process slutar. Den har ingen semantisk betydelse i vårt system.
- task: Denna används för att representera en arbetsuppgift i processen (modell ProcessActivity). Den har semantisk betydelse i vårt system och representerar en aktivitet som ska utföras.
- exclusiveGateway: Denna används för att representera en beslutspunkt i processen där endast en av flera möjliga vägar kan väljas. När vi räknar ut vilken arbetsuppgift som följer på en annan så behandlar vi en gateway som att den inte fanns, utan att alla objekt som ingår i gatewayen var sammankopplade med varandra.
- sequenceFlow: Denna används för att representera flödet mellan de olika komponenterna i processen. Den har semantisk betydelse i vårt system och representerar flödet mellan de olika komponenterna i processen.
- dataObjectReference: Detta representerar en informationstyp (modell InformationType). När processen publiceras så kommer informationstypen skapas om den inte redan finns.
- dataStoreReference: Detta representerar en lagringsplats för information, en tillgång (modell Asset). När processen publiceras så kommer tillgången skapas om den inte redan finns.
- textAnnotation: Denna används bara visuellt för att kunna visa textinformation i processkartan för användare.
- subProcess: Denna används bara visuellt för att kunna länka till andra processer i processkartan. Den har ingen semantisk betydelse i vårt system men vi håller koll på kopplingen för att kunna uppdatera namn på processer i associerade processkartor. För att kunna använda subProcess så är det namnet som används för att länka till en annan processkarta.


Vi tillåter bara följande associationer i våra processer, och följer alltså inte BPMN standard:
- startEvent → task
- task → task
- task → exclusiveGateway
- exclusiveGateway → task
- task → endEvent
- task → dataObjectReference
- dataObjectReference → dataStoreReference
- task  → subProcess
- textAnnotations till alla komponenter


Man får inte publicera en processkarta som:
- Har en startEvent som inte har en task som efterföljare
- Har en endEvent som inte har en associerad task
- Har dataObjectReference som inte har en associerad task
- Har dataStoreReference som inte har en associerad dataObjectReference
- Har en subProcess med ett namn som inte överensstämmer med en process som finns i systemet
- Där dataObjectReference inte är associerad med en dataStoreReference. Det får finnas flera dataObjectReference med samma namn, och det räcker att en av dem är associerad med en dataStoreReference.


## 🚀 Framtida Utvidgningar

Om CRUD Table ska expanderas, lägg till:

1. **Ny komponent** → `resources/js/Components/crud/`
2. **Ny hook** → `resources/js/hooks/`
3. **Ny typ** → `resources/js/types/crud.ts` (uppdatera befintlig)
4. **Dokumentation** → `doc/CRUD_TABLE_DOCUMENTATION.md` (uppdatera befintlig)
5. **Exempel** → `doc/INTEGRATION_GUIDE.md` eller `doc/QUICK_REFERENCE.md`

**SKAPA INTE nya dokumentfiler** för varje feature - uppdatera befintliga istället!

## 📞 Support

Om du är osäker:
1. Kontrollera befintlig dokumentation i `doc/`
2. Länka istället för att upprepa
3. Lägg till i befintlig fil istället för ny fil

---

**Version:** 1.0  
**Senast uppdaterad:** 2026-04-05  
**Status:** Active
