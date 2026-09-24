
export default function customTranslate(template, replacements) {
   replacements = replacements || {};
   
   // Translate
   template = translations[template] || template;
   
   // replace
   return template.replace(/{([^}]+)}/g, function(_, key) {
      return replacements[key] || '{' + key + '}';
   });
}

// Insert any translations to be made here
var translations = {
}
