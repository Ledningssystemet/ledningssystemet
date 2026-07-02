# AI Instructions for Ledningssystemet

## Purpose
This file defines project-specific instructions for AI agents working in this codebase.
The goal is consistent architecture, coding style, testing, and documentation quality.

## Core Principles
- Prefer editing existing files over creating new ones.
- Keep changes focused and minimal.
- Follow existing naming, structure, and style in the surrounding code.
- Never invent requirements; if unclear, inspect code and existing docs first.

## Documentation Rules

1. **Do not create documentation files automatically**
   - Unless explicitly requested by the team, do not generate new documentation files.
   - Documentation files should be created by the team.
   - If requested by the team, use the existing documentation as a starting point and follow the same structure and language.

## Testing Rules
1. **Create tests** for new features and bugs before implementing the fix.
2. Add tests in a new file under `tests/` with a clear, feature-focused name.
3. Prefer targeted tests first, then run broader suites as needed.

## Do Not

1. **Do not create example/demo files** unless explicitly requested.

## Do

1. **Create frontend code in the correct locations**
   ```
   resources/js/Components/crud/
   resources/js/hooks/
   resources/js/types/
   ```

2. **Follow project frontend conventions**
   - React functional components with TypeScript
   - Tailwind CSS for styling
   - Lucide React for icons
   - Axios for API calls

3. **Use correct encoding**
    - Use Unix line endings (`\n`).
    - Always output code and text using UTF-8 encoding without BOM. You must fully support and output Swedish characters (å, ä, ö) when translating to other languages.
    - Always use UTF-8 encoding for all files.

## Coding Standards

### React Components
```tsx
import React from 'react';

interface Props {
  config: CrudTableConfig;
}

export function ComponentName({ config }: Props) {
  // implementation
}
```

### TypeScript
```ts
// Good: explicit types
export interface MyInterface {
  field: string;
  value: number;
}

// Avoid
export interface MyInterface {
  field: any;
}
```

### Styling
```tsx
// Good: Tailwind utility classes
className="px-4 py-2 bg-blue-600 rounded-lg"

// Avoid: inline style for standard UI styling
style={{ padding: '16px 8px', backgroundColor: 'blue' }}
```

## AI Checklist

Before creating or changing anything:

- [ ] Does this already exist? (search in `resources/js/`, `app/`, and `doc/`)
- [ ] Is the file in the correct location?
- [ ] Does the change follow TypeScript/Tailwind/React conventions?
- [ ] Are relevant tests added or updated in `tests/`?

## BPMN Rules Used in This Project
We use only a subset of BPMN components.
Their semantics in this system are not identical to generic BPMN semantics.
We also enforce custom rules that are not part of the BPMN standard.

We do **not** execute BPMN models. We use process maps for:
- visualization
- extracting structured information used by the system

When extracting information from a process map, component names are significant.

### Supported BPMN Components and Semantics
- `startEvent`: Visual marker for process start only; no semantic meaning in the system.
- `endEvent`: Visual marker for process end only; no semantic meaning in the system.
- `task`: Represents a work task (`ProcessActivity` model); semantically significant.
- `exclusiveGateway`: Decision point. During "next activity" calculations, gateways are treated as transparent links between connected objects.
- `sequenceFlow`: Represents flow connections; semantically significant.
- `dataObjectReference`: Represents an information type (`InformationType` model). Created on publish if missing.
- `dataStoreReference`: Represents an information storage asset (`Asset` model). Created on publish if missing.
- `textAnnotation`: Visual text for users only.
- `subProcess`: Visual link to another process map. Name-based reference is used to link to an existing process.

### Allowed Associations
Only the following associations are allowed:
- `startEvent -> task`
- `task -> task`
- `task -> exclusiveGateway`
- `exclusiveGateway -> task`
- `task -> endEvent`
- `task -> dataObjectReference`
- `dataObjectReference -> dataStoreReference`
- `task -> subProcess`
- `textAnnotation` to all components

### Publish Validation Rules
A process map must not be published if it:
- has a `startEvent` without a following `task`
- has an `endEvent` without an associated `task`
- has a `dataObjectReference` without an associated `task`
- has a `dataStoreReference` without an associated `dataObjectReference`
- has a `subProcess` whose name does not match an existing process in the system
- has `dataObjectReference` entries not associated with any `dataStoreReference`

Note: Multiple `dataObjectReference` nodes may share the same name. It is sufficient that at least one of them is associated with a `dataStoreReference`.

## Workflow Guidance for AI Agents
- Start by inspecting existing implementation and tests.
- Propose the smallest viable change that satisfies the requirement.
- Update tests and docs together with code changes.
- Keep commits scoped to one concern when possible.
- If unsure, prefer asking for clarification over making assumptions.

## Support
If you are uncertain about how to proceed, please ask for clarification from the team before making changes. Always prefer linking to existing resources and documentation rather than creating new ones.

---

**Version:** 1.2  
**Last updated:** 2026-06-12 
**Status:** Active
