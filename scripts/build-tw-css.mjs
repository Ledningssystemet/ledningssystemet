import { readFile, writeFile } from "node:fs/promises";
import postcss from "postcss";
import tailwindcss from "tailwindcss";

const source = "resources/css/tailwind.css";
const output = "resources/css/utilities.css";
const result = await postcss([tailwindcss({ config: "tailwind.config.ts" })])
  .process(await readFile(source, "utf8"), { from: source, to: output });

result.root.walkRules((rule) => {
  if (rule.parent.type === "atrule" && rule.parent.name.endsWith("keyframes")) return;
  rule.walkDecls((declaration) => { declaration.important = true; });
});

await writeFile(output, result.root.toString() + "\n");
