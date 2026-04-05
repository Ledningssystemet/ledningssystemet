#!/usr/bin/env python3
import json
import re

# Read the old pageRegistry.tsx file
with open('resources/js/app/pageRegistry.tsx', 'r', encoding='utf-8') as f:
    content = f.read()

# Extract the pageContentRegistry object
match = re.search(r'const pageContentRegistry.*?= \{(.*?)\n\};', content, re.DOTALL)
if not match:
    print("Could not find pageContentRegistry")
    exit(1)

# Manual page key mapping to PascalCase filenames
page_mapping = {
    'help': ('HelpPage', 'Help'),
    'min-profil': ('MinProfilPage', 'Min profil'),
    'mina-uppgifter': ('MinaUppgifterPage', 'Mina uppgifter'),
    'mina-dokument': ('MinaDokumentPage', 'Mina dokument'),
    'processer': ('ProcesserPage', 'Processer'),
    'dokumentarkiv': ('DokumentarkivPage', 'Dokumentarkiv'),
    'kunder': ('KunderPage', 'Kunder'),
    'leverantorer': ('LeverantorPage', 'Leverantörer'),
    'avtal': ('AvtalPage', 'Avtal'),
    'tillgangar': ('TillgangarPage', 'Tillgångar'),
    'informationstyper': ('InformationstypPage', 'Informationstyper'),
    'kemikalier': ('KemikalierPage', 'Kemikalier'),
    'kravkallor': ('KravkallorPage', 'Kravkällor'),
    'kontroller': ('KontrollerPage', 'Kontroller'),
    'personuppgifter': ('PersonuppgifterPage', 'Personuppgifter'),
    'hallbarhet': ('HallbarhetPage', 'Hållbarhet'),
    'riskhantering': ('RiskhanteringPage', 'Riskhantering'),
    'avvikelser': ('AvvikelsePage', 'Avvikelser'),
    'revisioner': ('RevisionerPage', 'Revisioner'),
}

print(f"Found {len(page_mapping)} pages to generate")

# Parse each page from the registry and create template
registry_content = match.group(1)
page_blocks = re.findall(r"'([^']+)':\s*\{(.*?)\n\s*\},", registry_content, re.DOTALL)

for page_key, page_data in page_blocks:
    if page_key not in page_mapping:
        continue

    filename, label = page_mapping[page_key]
    print(f"Would generate: {filename}.tsx from '{page_key}'")

print("\nTo complete, use the Python script on a system with Python installed.")

