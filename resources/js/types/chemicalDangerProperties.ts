export interface ChemicalDangerPropertyDefinition {
    key: string;
    code: string;
    bit: number;
    imageUrl: string;
}

// The backend stores selected danger properties as a bitmask in ohs_danger_properties.
export const CHEMICAL_DANGER_PROPERTIES: ChemicalDangerPropertyDefinition[] = [
    { key: 'ghs01', code: 'GHS01', bit: 1, imageUrl: '/images/ghs/ghs01.svg' },
    { key: 'ghs02', code: 'GHS02', bit: 2, imageUrl: '/images/ghs/ghs02.svg' },
    { key: 'ghs03', code: 'GHS03', bit: 4, imageUrl: '/images/ghs/ghs03.svg' },
    { key: 'ghs04', code: 'GHS04', bit: 8, imageUrl: '/images/ghs/ghs04.svg' },
    { key: 'ghs05', code: 'GHS05', bit: 16, imageUrl: '/images/ghs/ghs05.svg' },
    { key: 'ghs06', code: 'GHS06', bit: 32, imageUrl: '/images/ghs/ghs06.svg' },
    { key: 'ghs07', code: 'GHS07', bit: 64, imageUrl: '/images/ghs/ghs07.svg' },
    { key: 'ghs08', code: 'GHS08', bit: 128, imageUrl: '/images/ghs/ghs08.svg' },
    { key: 'ghs09', code: 'GHS09', bit: 256, imageUrl: '/images/ghs/ghs09.svg' },
];



