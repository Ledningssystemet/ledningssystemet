export type Item = Record<string, any> & { id: string };
export type ViewMode = "master-detail" | "table" | "list";
export type Tone = "success" | "info" | "warning" | "danger" | "secondary";

/** Ett valalternativ: etikett i stället för internt värde, kan grupperas, inaktiveras och ha beskrivning. */
export interface OptionDef {
  value: string;
  label?: string;
  group?: string;
  disabled?: boolean;
  description?: string;
}
export type OptionInput = string | OptionDef;

export type FieldType = "text" | "textarea" | "number" | "date" | "datetime" | "file" | "select";

export interface ColumnConfig {
  key: string;
  /** FK-06: eget postberoende innehåll (text, länkar, knappar …). */
  render?: (item: Item) => import("react").ReactNode;
  label: string;
  type?: FieldType;
  options?: OptionInput[];
  /** Render a select field as a multi-value selector. */
  multiple?: boolean;
  /** Beroende val: alternativen räknas fram från formulärets aktuella värden. */
  dependsOn?: string;
  optionsFor?: (draft: Record<string, any>) => OptionInput[];
  sortable?: boolean;
  /** Dölj fältet i listan/detaljerna (finns ändå i formuläret om `form` tillåter). */
  listHidden?: boolean;
  /** I vilka formulär fältet ingår. "readonly-edit" = visas som ej redigerbar info vid redigering. */
  form?: "both" | "create" | "edit" | "none" | "readonly-edit";
  tab?: string;
  help?: string;
  required?: boolean;
  minLength?: number;
  maxLength?: number;
  pattern?: string;
  patternMessage?: string;
  min?: number;
  max?: number;
  step?: number;
  /** Tillåtna filtyper, t.ex. ".pdf,.docx" */
  accept?: string;
}
export interface DetailSection {
  key: string;
  label: string;
  type: "textarea" | "properties" | "tags" | "table";
  /** Kolumner för enkel nästlad tabell (type: "table" utan childConfig). */
  columns?: { key: string; label: string }[];
  /** Egen fullständig tabellkonfiguration för underordnad tabell (kan själv ha underordnade tabeller). */
  childConfig?: ModuleConfig;
  tab?: string;
}
export interface FilterConfig {
  key: string;
  label: string;
  options?: OptionInput[];
  /** "single" = one value, "multi" = checkbox multiselect, "tags" = multiselect with tagging */
  mode?: "single" | "multi" | "tags";
}
export interface Permissions {
  create?: boolean;
  update?: boolean | ((item: Item) => boolean);
  delete?: boolean | ((item: Item) => boolean);
}
export interface ModuleEvents {
  onCreated?: (item: Item) => void;
  onUpdated?: (item: Item, previous: Item) => void;
  /** Körs innan posten tas bort ur listan, så att den fortfarande kan identifieras. */
  onDeleted?: (item: Item) => void;
}
export interface ModuleConfig {
  /** API-läge: data hämtas och sparas via CRUD-API:et (GET, POST, PATCH, DELETE). */
  dataSource?: import("./api").DataSource;
  /** Anropas vid HTTP 401. */
  onUnauthorized?: () => void;
  listTitle: string;
  titleKey: string;
  subtitleKeys: string[];
  defaultView: ViewMode;
  /** Aktiverade vyer; utelämnade värden är aktiverade. Minst en vy måste vara aktiverad. */
  viewModes?: { masterDetail?: boolean; table?: boolean; list?: boolean };
  editMode: "inline" | "popup";
  perPage: number;
  /** Storlekar användaren kan välja i pagineringen, t.ex. [10, 25, 50, 100, 250] */
  pageSizeOptions?: number[];
  /** Lokala fält att söka i; i API-läge används filter[field_or_field]. */
  searchKeys: string[];
  columns: ColumnConfig[];
  tableColumns: string[];
  /** Aktiverar kolumnval och anger vilka tabellkolumner som visas från början. */
  defaultTableColumns?: string[];
  detailSections: DetailSection[];
  filters: FilterConfig[];
  badges?: Record<string, Record<string, Tone>>;
  statusDots?: Record<string, Record<string, Tone>>;
  newItemDefaults: Record<string, any>;
  /** Flikar i formuläret, i ordning. Fält utan flik hamnar på första fliken. */
  formTabs?: string[];
  permissions?: Permissions;
  events?: ModuleEvents;
  /** Spara vy och sidstorlek i kakor (standard: ja). Underordnade tabeller sparar inte. */
  persistState?: boolean;
  /** Behåll API-svarets nya objekt lokalt i tabellen i stället för att läsa om listan. */
  retainCreatedItem?: boolean;
  /** FK-15: identifierare för länkar jtId[tableId] och jtFilter[tableId][filter]. */
  tableId?: string;
  rowCommands?: RowCommand[];
  /** FK-07: statusikon, nivå och meddelanden per post. */
  status?: (item: Item) => ItemStatus | null;
  /** FK-07: dölj fält för en viss post. */
  hiddenFields?: (item: Item) => string[];
  /** FK-30: anpassa formuläret utifrån aktuella värden. */
  formState?: (draft: Item, ctx: { isNew: boolean }) => FormState;
  /** FK-36: flytta rader. onMoved får posten och den som nu ligger före (null = först). */
  reorder?: { canReorder?: (item: Item) => boolean; onMoved?: (item: Item, previous: Item | null) => void };
  customProperties?: CustomProperty[];
  /** Alternativ för referenstyper i anpassade egenskaper. */
  references?: Partial<Record<CustomProperty["type"], OptionInput[]>>;
  /** Visa sökfält och filter (standard: ja). */
  showToolbarSearch?: boolean;
}
export interface RowCommand {
  label: string;
  onClick?: (item: Item) => void;
  /** Modellspecifik serveråtgärd, t.ex. "archive" → POST /{model}/{id}/archive. Listan laddas om efteråt. */
  serverAction?: { name: string; method?: "POST" | "PATCH" | "DELETE"; confirm?: string };
  visible?: (item: Item) => boolean;
  /** Visar t.ex. antal kommentarer intill kommandot. */
  badge?: (item: Item) => string | number | undefined;
}
export interface ItemStatus { tone: Tone; label: string; messages?: string[] }
export interface FormState { hidden?: string[]; disabled?: string[]; required?: string[]; values?: Record<string, any> }
/** FK-38: dynamisk anpassad egenskap. */
export interface CustomProperty {
  key: string;
  label: string;
  type: "text" | "textarea" | "boolean" | "user" | "department" | "supplier" | "customer" | "asset" | "process";
  visible?: boolean;
  editable?: boolean;
  required?: boolean;
  filterable?: boolean;
}
export interface ModuleController {
  getItems: () => Item[];
  reload: (items?: Item[], done?: () => void) => void;
  updateItem: (id: string, patch: Partial<Item>, done?: () => void) => void;
  updateStatus: (id: string, status: Partial<Item>, done?: () => void) => void;
}
export interface SortState {
  key: string | null;
  dir: "asc" | "desc";
}
